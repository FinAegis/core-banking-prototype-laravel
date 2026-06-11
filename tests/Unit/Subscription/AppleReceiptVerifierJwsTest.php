<?php

/**
 * Unit tests for AppleReceiptVerifier::verifyJwsChain().
 *
 * We can't ship real Apple-issued certs in tests (they're tied to Apple's
 * private keys), so each test builds a synthetic 3-cert chain:
 *
 *   Test Root CA (self-signed, ES256/P-256)
 *     → Test Intermediate (signed by root)
 *       → Test Leaf (signed by intermediate)
 *
 * We then pin the test root's SHA-256 fingerprint via config override and
 * sign a JWS payload with the leaf key — exactly the wire shape Apple emits.
 *
 * To exercise the production code path (not the local/testing bypass) we
 * pretend to be in 'production' env via `$this->app['env']`. The bypass flag
 * stays unset (default false) so the chain validator runs.
 */

declare(strict_types=1);

use App\Domain\Subscription\Iap\AppleReceiptVerifier;
use App\Domain\Subscription\Iap\IapVerificationException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Generate a 3-cert chain + leaf private key for use in tests. Returns:
 *
 *   [
 *     'rootDer' => string,
 *     'intermediateDer' => string,
 *     'leafDer' => string,
 *     'leafKey' => OpenSSLAsymmetricKey,
 *     'rootFingerprint' => string (uppercase hex, no separators),
 *   ]
 *
 * Each cert is valid for 1 day starting 1 hour ago (default), or for a
 * caller-specified window per cert.
 *
 * @param array{
 *   leaf?: array{not_before_offset?: int, days?: int},
 *   intermediate?: array{not_before_offset?: int, days?: int},
 *   root?: array{not_before_offset?: int, days?: int}
 * } $options
 *
 * @return array{
 *   rootDer: string,
 *   intermediateDer: string,
 *   leafDer: string,
 *   leafKey: OpenSSLAsymmetricKey,
 *   rootFingerprint: string
 * }
 */
function buildSyntheticAppleChain(array $options = []): array
{
    $configArgs = [
        'digest_alg'       => 'sha256',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name'       => 'prime256v1',
    ];

    // Root: self-signed.
    $rootKey = openssl_pkey_new($configArgs);
    if ($rootKey === false) {
        throw new RuntimeException('Failed to generate root key.');
    }

    $rootCsr = openssl_csr_new(['commonName' => 'Test Apple Root CA G3'], $rootKey, ['digest_alg' => 'sha256']);
    if (! $rootCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Failed to generate root CSR.');
    }

    $rootDays = $options['root']['days'] ?? 365;
    $rootCert = openssl_csr_sign($rootCsr, null, $rootKey, $rootDays, ['digest_alg' => 'sha256']);
    if ($rootCert === false) {
        throw new RuntimeException('Failed to sign root cert.');
    }

    // Intermediate: signed by root.
    $intKey = openssl_pkey_new($configArgs);
    if ($intKey === false) {
        throw new RuntimeException('Failed to generate intermediate key.');
    }
    $intCsr = openssl_csr_new(['commonName' => 'Test Apple WWDR Intermediate'], $intKey, ['digest_alg' => 'sha256']);
    if (! $intCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Failed to generate intermediate CSR.');
    }
    $intDays = $options['intermediate']['days'] ?? 365;
    $intCert = openssl_csr_sign($intCsr, $rootCert, $rootKey, $intDays, ['digest_alg' => 'sha256']);
    if ($intCert === false) {
        throw new RuntimeException('Failed to sign intermediate cert.');
    }

    // Leaf: signed by intermediate. Caller can request expired leaf via days<0.
    $leafKey = openssl_pkey_new($configArgs);
    if ($leafKey === false) {
        throw new RuntimeException('Failed to generate leaf key.');
    }
    $leafCsr = openssl_csr_new(['commonName' => 'Test Apple Leaf'], $leafKey, ['digest_alg' => 'sha256']);
    if (! $leafCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Failed to generate leaf CSR.');
    }
    $leafDays = $options['leaf']['days'] ?? 30;
    $leafCert = openssl_csr_sign($leafCsr, $intCert, $intKey, $leafDays, ['digest_alg' => 'sha256']);
    if ($leafCert === false) {
        throw new RuntimeException('Failed to sign leaf cert.');
    }

    // Export to PEM, convert to DER bytes.
    $rootDer = pemCertToDer(exportPem($rootCert));
    $intDer = pemCertToDer(exportPem($intCert));
    $leafDer = pemCertToDer(exportPem($leafCert));

    return [
        'rootDer'         => $rootDer,
        'intermediateDer' => $intDer,
        'leafDer'         => $leafDer,
        'leafKey'         => $leafKey,
        'rootFingerprint' => strtoupper(hash('sha256', $rootDer)),
    ];
}

function exportPem(OpenSSLCertificate $cert): string
{
    $pem = '';
    if (! openssl_x509_export($cert, $pem)) {
        throw new RuntimeException('openssl_x509_export failed.');
    }

    return $pem;
}

function pemCertToDer(string $pem): string
{
    if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m) !== 1) {
        throw new RuntimeException('PEM did not contain a certificate block.');
    }
    $b64 = (string) preg_replace('/\s+/', '', $m[1]);
    $der = base64_decode($b64, true);
    if ($der === false) {
        throw new RuntimeException('PEM cert body could not be base64-decoded.');
    }

    return $der;
}

function jwsB64Url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * Build a complete StoreKit-2-shape JWS string signed by the test chain.
 *
 * @param array<string, mixed> $payloadOverrides
 * @param array{
 *   rootDer: string,
 *   intermediateDer: string,
 *   leafDer: string,
 *   leafKey: OpenSSLAsymmetricKey,
 *   rootFingerprint: string
 * } $chain
 * @param array{
 *   alg?: string,
 *   x5c_count?: int,
 *   tamper_payload?: bool,
 *   tamper_signature?: bool,
 *   swap_root?: ?string
 * } $headerOverrides
 */
function buildSignedJws(array $chain, array $payloadOverrides = [], array $headerOverrides = []): string
{
    $x5c = [
        base64_encode($chain['leafDer']),
        base64_encode($chain['intermediateDer']),
        base64_encode($chain['rootDer']),
    ];

    if (isset($headerOverrides['swap_root']) && is_string($headerOverrides['swap_root'])) {
        $x5c[2] = base64_encode($headerOverrides['swap_root']);
    }

    if (isset($headerOverrides['x5c_count'])) {
        $x5c = array_slice($x5c, 0, (int) $headerOverrides['x5c_count']);
    }

    $header = [
        'alg' => $headerOverrides['alg'] ?? 'ES256',
        'x5c' => $x5c,
    ];

    $payload = array_merge([
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'tx-001',
        'transactionId'         => 'tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'environment'           => 'Sandbox',
        'inAppOwnershipType'    => 'PURCHASED',
    ], $payloadOverrides);

    $headerB64 = jwsB64Url((string) json_encode($header));
    $payloadB64 = jwsB64Url((string) json_encode($payload));

    if (! empty($headerOverrides['tamper_payload'])) {
        // Flip one character in the b64 payload — signature must mismatch.
        $payloadB64 = substr_replace($payloadB64, $payloadB64[0] === 'A' ? 'B' : 'A', 0, 1);
    }

    $signed = $headerB64 . '.' . $payloadB64;
    $derSig = '';
    if (! openssl_sign($signed, $derSig, $chain['leafKey'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('openssl_sign failed for test JWS.');
    }

    $rawSig = derEcdsaToRaw($derSig);

    if (! empty($headerOverrides['tamper_signature'])) {
        $rawSig[0] = chr(ord($rawSig[0]) ^ 0xFF);
    }

    return $signed . '.' . jwsB64Url($rawSig);
}

/**
 * Convert a DER ECDSA signature (SEQUENCE { INTEGER r, INTEGER s }) to raw
 * r||s (64 bytes for P-256). Mirror of AppleReceiptVerifier::ecdsaRawToDer.
 */
function derEcdsaToRaw(string $der): string
{
    if (ord($der[0]) !== 0x30) {
        throw new RuntimeException('Test helper: DER signature missing SEQUENCE tag.');
    }
    $pos = 2;
    if ((ord($der[1]) & 0x80) !== 0) {
        $pos = 2 + (ord($der[1]) & 0x7F);
    }
    if (ord($der[$pos]) !== 0x02) {
        throw new RuntimeException('Test helper: DER signature missing r INTEGER.');
    }
    $rLen = ord($der[$pos + 1]);
    $r = substr($der, $pos + 2, $rLen);
    $pos += 2 + $rLen;
    if (ord($der[$pos]) !== 0x02) {
        throw new RuntimeException('Test helper: DER signature missing s INTEGER.');
    }
    $sLen = ord($der[$pos + 1]);
    $s = substr($der, $pos + 2, $sLen);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

/**
 * @param array{
 *   rootDer: string,
 *   intermediateDer: string,
 *   leafDer: string,
 *   leafKey: OpenSSLAsymmetricKey,
 *   rootFingerprint: string
 * } $chain
 */
function pinTestRoot(array $chain): void
{
    config([
        'subscription.iap.apple.root_ca_path'            => null,
        'subscription.iap.apple.root_ca_fingerprints'    => [$chain['rootFingerprint']],
        'subscription.iap.apple.bundle_id'               => 'app.zelta',
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);
}

beforeEach(function (): void {
    $this->app['env'] = 'production';
});

it('accepts a JWS signed by a properly-chained test root', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    $jws = buildSignedJws($chain);

    $verifier = new AppleReceiptVerifier();
    $tx = $verifier->verify($jws, 'tx-001');

    expect($tx->originalTransactionId)->toBe('tx-001');
    expect($tx->productId)->toBe('zelta_pro_monthly');
});

it('rejects a JWS whose payload has been tampered with (signature mismatch)', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    $jws = buildSignedJws($chain, [], ['tamper_payload' => true]);

    $verifier = new AppleReceiptVerifier();

    expect(fn () => $verifier->verify($jws, ''))->toThrow(IapVerificationException::class);
});

it('rejects a JWS with a flipped signature byte', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    $jws = buildSignedJws($chain, [], ['tamper_signature' => true]);

    $verifier = new AppleReceiptVerifier();

    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );
});

it('rejects a JWS rooted at a different (non-pinned) root', function (): void {
    $chainA = buildSyntheticAppleChain();
    $chainB = buildSyntheticAppleChain();

    // Pin only chainA's root.
    pinTestRoot($chainA);

    // But sign with chainB.
    $jws = buildSignedJws($chainB);

    $verifier = new AppleReceiptVerifier();

    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );
});

it('rejects a JWS where x5c[2] is swapped for a foreign (self-signed) cert that matches the pin', function (): void {
    // Attacker substitutes a different cert at position [2] hoping chain
    // validation lets it slide. Even if the fingerprint matched (it won't,
    // because we pin chainA's root), the intermediate signature check fails.
    $chainA = buildSyntheticAppleChain();
    $chainB = buildSyntheticAppleChain();
    pinTestRoot($chainA);

    // Swap chainA's root for chainB's root — fingerprint mismatch.
    $jws = buildSignedJws($chainA, [], ['swap_root' => $chainB['rootDer']]);

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(IapVerificationException::class);
});

it('rejects a JWS with an alg header other than ES256', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    $jws = buildSignedJws($chain, [], ['alg' => 'HS256']);

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );
});

it('rejects a JWS whose x5c is missing the root cert (only 2 entries)', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    $jws = buildSignedJws($chain, [], ['x5c_count' => 2]);

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(IapVerificationException::class);
});

it('rejects a JWS whose leaf cert has expired', function (): void {
    // openssl_csr_sign signs with `days` from now. We can't easily back-date
    // certs through PHP's OpenSSL API, but we CAN sign with days=1 then
    // travel time forward in the test — Carbon::setTestNow respects the
    // production env switch.
    $chain = buildSyntheticAppleChain(['leaf' => ['days' => 1]]);
    pinTestRoot($chain);

    $jws = buildSignedJws($chain);

    // Move 2 days into the future — leaf NotAfter is now in the past.
    Illuminate\Support\Carbon::setTestNow(now()->addDays(2));

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );

    Illuminate\Support\Carbon::setTestNow();
});

it('rejects a JWS whose ES256 signature decodes to the wrong length', function (): void {
    $chain = buildSyntheticAppleChain();
    pinTestRoot($chain);

    // Build a valid JWS, then truncate the signature segment.
    $jws = buildSignedJws($chain);
    [$h, $p] = explode('.', $jws);
    $badJws = $h . '.' . $p . '.' . jwsB64Url(str_repeat("\x01", 32)); // wrong length

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($badJws, ''))->toThrow(IapVerificationException::class);
});

it('fails when no root pins are configured at all', function (): void {
    $chain = buildSyntheticAppleChain();
    config([
        'subscription.iap.apple.root_ca_path'            => null,
        'subscription.iap.apple.root_ca_fingerprints'    => [],
        'subscription.iap.apple.bundle_id'               => 'app.zelta',
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);

    $jws = buildSignedJws($chain);

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );
});

it('loads the bundled Apple Root CA G3 cert from disk and pins by computed fingerprint', function (): void {
    // The bundled cert is the real Apple Root CA G3. The chain in this test
    // is synthetic and will NOT chain to it — so verification fails at the
    // root pin step. That's exactly what we want to assert: the verifier
    // computes the bundled root's fingerprint at runtime (no manual pin
    // configured) and rejects chains rooted elsewhere.
    $chain = buildSyntheticAppleChain();
    config([
        'subscription.iap.apple.root_ca_path'            => storage_path('app/apple/AppleRootCA-G3.cer'),
        'subscription.iap.apple.root_ca_fingerprints'    => [],
        'subscription.iap.apple.bundle_id'               => 'app.zelta',
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);

    $jws = buildSignedJws($chain);

    $verifier = new AppleReceiptVerifier();
    expect(fn () => $verifier->verify($jws, ''))->toThrow(
        IapVerificationException::class,
        'Apple JWS chain validation failed'
    );
});

it('respects APPLE_JWS_VERIFICATION_BYPASS=true (operator escape hatch)', function (): void {
    // No pins configured — but bypass flag is set, so we never reach the
    // chain validator. This is the documented staging escape hatch: it is
    // honoured only OUTSIDE production (the file-wide beforeEach pins env to
    // production, where the verifier rightly refuses the bypass) and outside
    // the local/testing early-return.
    $this->app['env'] = 'staging';
    $chain = buildSyntheticAppleChain();
    config([
        'subscription.iap.apple.root_ca_path'            => null,
        'subscription.iap.apple.root_ca_fingerprints'    => [],
        'subscription.iap.apple.bundle_id'               => 'app.zelta',
        'subscription.iap.apple_jws_verification_bypass' => true,
    ]);

    $jws = buildSignedJws($chain);

    $verifier = new AppleReceiptVerifier();
    $tx = $verifier->verify($jws, '');
    expect($tx->originalTransactionId)->toBe('tx-001');
});
