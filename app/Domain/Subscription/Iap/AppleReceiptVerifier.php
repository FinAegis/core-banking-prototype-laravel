<?php

/**
 * AppleReceiptVerifier — StoreKit 2 JWS local verification.
 *
 * Mobile-dev confirmed: expo-iap 4.2.3 + Expo SDK 54 + iOS 15.1+ → StoreKit 2
 * is always used. Mobile sends a JWS-signed transaction string; we decode the
 * payload (and the optional x5c cert chain) locally rather than calling the
 * deprecated `verifyReceipt` endpoint.
 *
 * For local/testing with no Apple cert chain configured we follow the
 * CLAUDE.md webhook-auth-bypass pattern: gated explicitly on env+empty key,
 * never `return true` unconditionally.
 *
 * Production verification:
 *   1. Parse the JWS protected header (alg=ES256 + 3-cert x5c chain)
 *   2. Decode the DER certificates, walk leaf → intermediate → root
 *   3. Pin Apple Root CA G3 by SHA-256 fingerprint (bundled at
 *      storage/app/apple/AppleRootCA-G3.cer); reject any chain not rooted there
 *   4. Verify each cert's issuer/signature/NotBefore/NotAfter
 *   5. ES256-verify the JWS signature against the leaf public key after
 *      converting the wire-format raw r||s signature to DER
 *
 * The deprecated `POST https://buy.itunes.apple.com/verifyReceipt` endpoint is
 * never called.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.7 / §8.1
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use App\Support\DecimalString;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AppleReceiptVerifier
{
    /**
     * Decode a StoreKit 2 JWS and return a typed transaction object.
     *
     * @throws IapVerificationException
     */
    public function verify(string $jws, string $expectedOriginalTransactionId): AppleVerifiedTransaction
    {
        $payload = $this->decodeJwsPayload($jws);

        $bundleId = (string) config('subscription.iap.apple.bundle_id', '');
        $receivedBundleId = (string) ($payload['bundleId'] ?? '');

        // In local/testing with an empty configured bundle id, skip the bundle
        // check (matches the CLAUDE.md bypass pattern: gated explicitly + empty
        // config — never `return true`).
        if ($bundleId !== '' && $receivedBundleId !== '' && $receivedBundleId !== $bundleId) {
            throw new IapVerificationException(
                "Apple JWS bundleId mismatch: expected '{$bundleId}', got '{$receivedBundleId}'."
            );
        }

        $originalTransactionId = (string) ($payload['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            throw new IapVerificationException('Apple JWS missing originalTransactionId.');
        }

        // Mobile sends the StoreKit 2 originalTransactionId in the request body;
        // it MUST match what's inside the JWS. Drift indicates tampering or a
        // mobile bug.
        if ($expectedOriginalTransactionId !== '' && $originalTransactionId !== $expectedOriginalTransactionId) {
            throw new IapVerificationException(
                'Apple JWS originalTransactionId does not match request body.'
            );
        }

        $transactionId = (string) ($payload['transactionId'] ?? $originalTransactionId);
        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '') {
            throw new IapVerificationException('Apple JWS missing productId.');
        }

        $appAccountToken = isset($payload['appAccountToken']) && is_string($payload['appAccountToken'])
            ? (string) $payload['appAccountToken']
            : null;

        // Apple's `price` field for subscriptions is the storefront currency
        // amount in minor units (integer micros for some plans, plain integer
        // for storefronts with implicit 2 decimals — Apple is inconsistent).
        // We normalise to minor units (decimals=2) when the value looks like
        // a plain integer; if Apple emits `priceAmountMicros` we use 6 decimals.
        [$amountSmallestUnit, $amountDecimals] = $this->extractPrice($payload);

        $amountCurrency = strtoupper((string) ($payload['currency'] ?? 'EUR'));

        $purchaseDate = $this->parseAppleEpochMillis($payload['purchaseDate'] ?? null);
        $originalPurchaseDate = $this->parseAppleEpochMillis($payload['originalPurchaseDate'] ?? null);
        $expiresDate = $this->parseAppleEpochMillis($payload['expiresDate'] ?? null);

        // Apple sets `inAppOwnershipType` = `FAMILY_SHARED` for sharing-derived
        // receipts (we reject these at the verify endpoint with ERR_SUB_002 kind=family_sharing_unsupported).
        $ownershipType = (string) ($payload['inAppOwnershipType'] ?? 'PURCHASED');
        $isFamilyShared = $ownershipType === 'FAMILY_SHARED';

        $environment = (string) ($payload['environment'] ?? 'Production');
        $isSandbox = strtolower($environment) === 'sandbox';

        return new AppleVerifiedTransaction(
            originalTransactionId: $originalTransactionId,
            transactionId: $transactionId,
            productId: $productId,
            bundleId: $receivedBundleId,
            appAccountToken: $appAccountToken,
            amountSmallestUnit: $amountSmallestUnit,
            amountDecimals: $amountDecimals,
            amountCurrency: $amountCurrency,
            purchaseDate: $purchaseDate,
            originalPurchaseDate: $originalPurchaseDate,
            expiresDate: $expiresDate,
            isInIntroOfferPeriod: (bool) ($payload['offerType'] ?? false),
            isTrialPeriod: (string) ($payload['type'] ?? '') === 'Auto-Renewable Subscription'
                && isset($payload['offerType']),
            isFamilyShared: $isFamilyShared,
            isSandbox: $isSandbox,
            rawJws: $jws,
        );
    }

    /**
     * Decode an Apple Server Notifications V2 envelope (the outer
     * `signedPayload` JWS). Returns the decoded payload array.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    public function decodeNotificationPayload(string $signedPayload): array
    {
        return $this->decodeJwsPayload($signedPayload);
    }

    /**
     * Decode the inner `signedTransactionInfo` JWS embedded in a notification.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    public function decodeSignedTransactionInfo(string $signedTransactionInfo): array
    {
        return $this->decodeJwsPayload($signedTransactionInfo);
    }

    /**
     * Decode a JWS payload — strict in production, JSON-decoded in local/testing.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    private function decodeJwsPayload(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new IapVerificationException('Apple JWS malformed: expected 3 segments.');
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if ($payloadJson === false) {
            throw new IapVerificationException('Apple JWS payload could not be base64url-decoded.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payloadJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new IapVerificationException('Apple JWS payload is not valid JSON: ' . $e->getMessage());
        }

        if (! is_array($decoded)) {
            throw new IapVerificationException('Apple JWS payload is not a JSON object.');
        }

        // Signature verification path: in production we MUST validate the x5c
        // certificate chain against Apple Root CA G3. In local/testing we
        // deliberately skip (CLAUDE.md bypass pattern). In every other
        // environment, the verifier must FAIL CLOSED — accepting an unverified
        // JWS in prod would allow any authenticated user to forge an
        // originalTransactionId / productId / expiresDate and grant themselves
        // a Pro subscription. The explicit operator escape hatch is
        // APPLE_JWS_VERIFICATION_BYPASS=true (intended for staging only).
        if (app()->environment('local', 'testing')) {
            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        // The bypass flag is honoured ONLY outside production. Even if an
        // operator sets APPLE_JWS_VERIFICATION_BYPASS=true on a production
        // env (accidentally, or via a leaked config), we MUST NOT skip the
        // chain check — the bypass is a full auth-bypass for the entire
        // Apple IAP surface (any authenticated user could forge a JWS with
        // arbitrary originalTransactionId / productId / expiresDate). The
        // production-environment gate is the same pattern used by other
        // bypass paths in this codebase (see CLAUDE.md "Webhook auth bypass").
        $bypassRequested = (bool) config('subscription.iap.apple_jws_verification_bypass', false);
        if ($bypassRequested && app()->environment('production')) {
            Log::error('iap.apple.jws.bypass_rejected_in_production', [
                'environment' => app()->environment(),
                'reason'      => 'APPLE_JWS_VERIFICATION_BYPASS=true ignored in production; running chain validation',
            ]);
            $bypassRequested = false;
        }

        if (! $bypassRequested) {
            $this->verifyJwsChain($parts[0], $parts[1], $parts[2]);
        } else {
            // Bypass flag is honoured (non-production env) — operator has
            // explicitly accepted the risk, typically for a staging
            // environment without provisioned certs. Log every bypass so
            // it's visible in audit logs.
            Log::warning('iap.apple.jws.signature_verify_bypassed', [
                'environment' => app()->environment(),
                'reason'      => 'APPLE_JWS_VERIFICATION_BYPASS=true',
            ]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Production JWS-chain verification. Performs (in order):
     *   1. Base64url-decode the protected header; require alg=ES256 + 3-cert x5c
     *   2. Decode the DER certs from x5c (NB: standard base64, not base64url)
     *   3. Pin the root: x5c[2] SHA-256 fingerprint MUST be in the configured
     *      pin list (Apple Root CA G3 by default). Defence-in-depth against a
     *      hostile WWDR cert chain that happens to be self-consistent.
     *   4. Chain-validate each link: x5c[0] signed by x5c[1], x5c[1] signed by
     *      x5c[2]; every cert's NotBefore/NotAfter contains now().
     *   5. ES256-verify the JWS signature: signed input is `header.payload`
     *      bytes; the wire signature is raw r||s (64 bytes), which we convert
     *      to DER before handing to `openssl_verify` (PHP's OpenSSL extension
     *      only accepts DER-encoded ECDSA signatures).
     *
     * Any failure throws IapVerificationException with a reason that we log
     * but DO NOT expose to the user (the verify endpoint surfaces ERR_SUB_001).
     *
     * @throws IapVerificationException
     */
    private function verifyJwsChain(string $headerB64, string $payloadB64, string $signatureB64): void
    {
        // 1. Decode and validate the protected header.
        $headerJson = $this->base64UrlDecode($headerB64);
        if ($headerJson === false) {
            throw $this->chainFailure('header_b64_decode_failed', 'Apple JWS header could not be decoded.');
        }

        try {
            /** @var mixed $headerDecoded */
            $headerDecoded = json_decode($headerJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw $this->chainFailure('header_json_invalid', 'Apple JWS header is not valid JSON: ' . $e->getMessage());
        }

        if (! is_array($headerDecoded)) {
            throw $this->chainFailure('header_not_object', 'Apple JWS header is not a JSON object.');
        }

        $alg = (string) ($headerDecoded['alg'] ?? '');
        if ($alg !== 'ES256') {
            throw $this->chainFailure('header_alg_unsupported', "Apple JWS alg must be ES256, got '{$alg}'.");
        }

        $x5c = $headerDecoded['x5c'] ?? null;
        if (! is_array($x5c) || count($x5c) !== 3) {
            throw $this->chainFailure(
                'header_x5c_invalid',
                'Apple JWS header x5c must be an array of exactly 3 base64 DER certificates.'
            );
        }

        // 2. Decode certs (base64, NOT base64url — JWS spec §4.1.6).
        // Real Apple certs are <2 KB DER (~3 KB base64). Cap each x5c entry
        // at 16 KB before we allocate / hand to OpenSSL so a malformed JWS
        // can't drag the request into a memory-DoS path.
        $pems = [];
        foreach ($x5c as $i => $entry) {
            if (! is_string($entry) || $entry === '') {
                throw $this->chainFailure('x5c_entry_invalid', "Apple JWS x5c[{$i}] is not a non-empty string.");
            }
            if (strlen($entry) > 16384) {
                throw $this->chainFailure(
                    'x5c_entry_too_large',
                    "Apple JWS x5c[{$i}] exceeds 16 KB cap (real Apple certs are <3 KB base64).",
                );
            }
            $derBase64 = preg_replace('/\s+/', '', $entry) ?? '';
            $der = base64_decode($derBase64, true);
            if ($der === false || $der === '') {
                throw $this->chainFailure('x5c_entry_decode_failed', "Apple JWS x5c[{$i}] is not valid base64.");
            }

            $pem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split(base64_encode($der), 64, "\n")
                . "-----END CERTIFICATE-----\n";

            $resource = openssl_x509_read($pem);
            if ($resource === false) {
                throw $this->chainFailure(
                    'x5c_entry_parse_failed',
                    "Apple JWS x5c[{$i}] is not a valid X.509 certificate.",
                );
            }

            $pems[$i] = [
                'pem'      => $pem,
                'der'      => $der,
                'resource' => $resource,
            ];
        }

        [$leaf, $intermediate, $root] = [$pems[0], $pems[1], $pems[2]];

        // 3. Pin the root by SHA-256(DER) fingerprint. Pins are configured as
        // uppercase hex strings without separators (matches `openssl x509
        // -fingerprint -sha256` output once colons are stripped).
        $rootFingerprint = strtoupper(hash('sha256', $root['der']));
        $pins = $this->loadRootPins();
        if ($pins === []) {
            throw $this->chainFailure(
                'no_root_pins_configured',
                'Apple Root CA pin list is empty — refusing to chain-validate.'
            );
        }
        if (! in_array($rootFingerprint, $pins, true)) {
            throw $this->chainFailure(
                'root_fingerprint_mismatch',
                'Apple JWS root certificate fingerprint does not match any pinned root.',
                ['root_fingerprint' => $rootFingerprint]
            );
        }

        // 4. Validity windows + chain signatures.
        $now = Carbon::now();
        foreach (['leaf' => $leaf, 'intermediate' => $intermediate, 'root' => $root] as $label => $cert) {
            $parsed = openssl_x509_parse($cert['resource']);
            if (! is_array($parsed)) {
                throw $this->chainFailure("{$label}_parse_failed", "Apple JWS {$label} cert could not be parsed.");
            }

            // openssl_x509_parse() documents these as Unix timestamps, but on
            // 32-bit PHP or some OpenSSL builds the field can come back as a
            // numeric string. Accept both rather than fail-closed on every
            // production request.
            $notBefore = $this->parseCertTimestamp($parsed['validFrom_time_t'] ?? null);
            $notAfter = $this->parseCertTimestamp($parsed['validTo_time_t'] ?? null);

            if ($notBefore === null || $notAfter === null) {
                throw $this->chainFailure(
                    "{$label}_validity_missing",
                    "Apple JWS {$label} cert is missing NotBefore/NotAfter."
                );
            }

            if ($now->lt($notBefore)) {
                throw $this->chainFailure(
                    "{$label}_not_yet_valid",
                    "Apple JWS {$label} cert is not yet valid (NotBefore in the future)."
                );
            }

            if ($now->gt($notAfter)) {
                throw $this->chainFailure(
                    "{$label}_expired",
                    "Apple JWS {$label} cert is expired (NotAfter in the past)."
                );
            }
        }

        // Verify chain links via openssl_x509_verify (PHP 8.0+). Returns 1 on
        // success, 0 on signature mismatch, negative on error.
        $leafSignedByIntermediate = openssl_x509_verify($leaf['resource'], $intermediate['resource']);
        if ($leafSignedByIntermediate !== 1) {
            throw $this->chainFailure(
                'leaf_signature_invalid',
                'Apple JWS leaf cert was not signed by the intermediate cert.',
                ['result' => $leafSignedByIntermediate]
            );
        }

        $intermediateSignedByRoot = openssl_x509_verify($intermediate['resource'], $root['resource']);
        if ($intermediateSignedByRoot !== 1) {
            throw $this->chainFailure(
                'intermediate_signature_invalid',
                'Apple JWS intermediate cert was not signed by the pinned root.',
                ['result' => $intermediateSignedByRoot]
            );
        }

        // Defence-in-depth: confirm the root self-signs (catches a degenerate
        // chain where x5c[2] is not actually a root). openssl_x509_verify
        // returns 1 only when the signature checks out.
        $rootSelfSigned = openssl_x509_verify($root['resource'], $root['resource']);
        if ($rootSelfSigned !== 1) {
            throw $this->chainFailure(
                'root_not_self_signed',
                'Apple JWS root cert does not self-sign — chain is malformed.',
                ['result' => $rootSelfSigned]
            );
        }

        // 5. ES256-verify the signature against the leaf public key.
        $leafPublicKey = openssl_pkey_get_public($leaf['pem']);
        if ($leafPublicKey === false) {
            throw $this->chainFailure('leaf_public_key_unreadable', 'Apple JWS leaf public key could not be read.');
        }

        $rawSignature = $this->base64UrlDecode($signatureB64);
        if ($rawSignature === false || strlen($rawSignature) !== 64) {
            $len = $rawSignature === false ? -1 : strlen($rawSignature);
            throw $this->chainFailure(
                'signature_b64_invalid',
                'Apple JWS ES256 signature must base64url-decode to exactly 64 bytes (r||s).',
                ['decoded_length' => $len]
            );
        }

        $derSignature = $this->ecdsaRawToDer($rawSignature);
        $signedInput = $headerB64 . '.' . $payloadB64;

        $verifyResult = openssl_verify($signedInput, $derSignature, $leafPublicKey, OPENSSL_ALGO_SHA256);
        if ($verifyResult !== 1) {
            throw $this->chainFailure(
                'signature_verify_failed',
                'Apple JWS ES256 signature did not verify against the leaf public key.',
                ['result' => $verifyResult]
            );
        }
    }

    /**
     * Load the pinned root CA SHA-256 fingerprints. Two sources, merged:
     *   - config('subscription.iap.apple.root_ca_fingerprints') — explicit list
     *   - config('subscription.iap.apple.root_ca_path') — bundled .cer file
     *
     * Returns an array of uppercase hex fingerprints (no separators).
     *
     * @return array<int, string>
     */
    private function loadRootPins(): array
    {
        $pins = [];

        /** @var mixed $configured */
        $configured = config('subscription.iap.apple.root_ca_fingerprints', []);
        if (is_array($configured)) {
            foreach ($configured as $fingerprint) {
                if (is_string($fingerprint) && $fingerprint !== '') {
                    $pins[] = $this->normaliseFingerprint($fingerprint);
                }
            }
        }

        /** @var mixed $path */
        $path = config('subscription.iap.apple.root_ca_path');
        if (is_string($path) && $path !== '' && is_file($path)) {
            $der = @file_get_contents($path);
            if (is_string($der) && $der !== '') {
                // Bundled cert may be DER or PEM — auto-detect.
                if (str_starts_with($der, '-----BEGIN ')) {
                    $cert = openssl_x509_read($der);
                    if ($cert !== false) {
                        $exported = '';
                        if (openssl_x509_export($cert, $exported, false)) {
                            // $exported is PEM; convert to DER for fingerprint.
                            $derBytes = $this->pemToDer($exported);
                            if ($derBytes !== null) {
                                $pins[] = strtoupper(hash('sha256', $derBytes));
                            }
                        }
                    }
                } else {
                    // Assume DER bytes.
                    $pins[] = strtoupper(hash('sha256', $der));
                }
            }
        }

        // De-duplicate while preserving order.
        return array_values(array_unique($pins));
    }

    private function normaliseFingerprint(string $fingerprint): string
    {
        // Strip whitespace and colons, uppercase.
        return strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $fingerprint));
    }

    private function pemToDer(string $pem): ?string
    {
        if (! preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $matches)) {
            return null;
        }

        $b64 = (string) preg_replace('/\s+/', '', $matches[1]);
        $der = base64_decode($b64, true);

        return $der === false ? null : $der;
    }

    /**
     * Convert a raw ECDSA P-256 signature (64 bytes r||s) to ASN.1 DER, as
     * required by `openssl_verify`. Each integer is encoded as a minimal
     * positive INTEGER (leading 0x00 if the MSB is set, so the value stays
     * positive in DER).
     *
     * Layout: SEQUENCE { INTEGER r, INTEGER s }
     */
    private function ecdsaRawToDer(string $raw): string
    {
        $r = substr($raw, 0, 32);
        $s = substr($raw, 32, 32);

        $rEncoded = $this->derEncodeInteger($r);
        $sEncoded = $this->derEncodeInteger($s);

        $sequenceBody = $rEncoded . $sEncoded;
        $sequenceLength = $this->derEncodeLength(strlen($sequenceBody));

        return "\x30" . $sequenceLength . $sequenceBody;
    }

    private function derEncodeInteger(string $value): string
    {
        // Strip leading 0x00 bytes (but never leave the value empty).
        $i = 0;
        $len = strlen($value);
        while ($i < $len - 1 && $value[$i] === "\x00") {
            $i++;
        }
        $stripped = substr($value, $i);

        // If the MSB of the first byte is set, prepend 0x00 so DER doesn't read
        // the integer as negative.
        if ($stripped !== '' && (ord($stripped[0]) & 0x80) !== 0) {
            $stripped = "\x00" . $stripped;
        }

        $length = $this->derEncodeLength(strlen($stripped));

        return "\x02" . $length . $stripped;
    }

    private function derEncodeLength(int $length): string
    {
        // DER short form: 0..127 → single byte; otherwise long form with a
        // length-of-length prefix. ECDSA r/s are <= 33 bytes each, so SEQUENCE
        // length here is always <= ~70 (short form). Long form retained for
        // robustness/PHPStan completeness.
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        $n = $length;
        while ($n > 0) {
            $bytes = chr($n & 0xFF) . $bytes;
            $n >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Build (and log) a chain-validation IapVerificationException. Logs the
     * structured failure for ops, returns a generic-but-not-too-generic message
     * for the controller. The verify endpoint surfaces ERR_SUB_001 to the user
     * regardless of the reason here — we never leak internal details.
     *
     * @param array<string, mixed> $context
     */
    private function chainFailure(string $reason, string $message, array $context = []): IapVerificationException
    {
        Log::warning('iap.apple.jws.chain_validation_failed', array_merge([
            'reason' => $reason,
        ], $context));

        return new IapVerificationException('Apple JWS chain validation failed: ' . $message);
    }

    /**
     * openssl_x509_parse()'s `validFrom_time_t` / `validTo_time_t` are
     * documented as Unix timestamps but return as int on most builds and as
     * numeric strings on some (32-bit PHP, certain OpenSSL builds with
     * post-2038 dates). Accept either; reject anything else so callers can
     * fail closed.
     */
    private function parseCertTimestamp(mixed $value): ?Carbon
    {
        if (is_int($value)) {
            return Carbon::createFromTimestampUTC($value);
        }
        if (is_string($value) && ctype_digit($value)) {
            return Carbon::createFromTimestampUTC((int) $value);
        }

        return null;
    }

    /**
     * Extract the price from an Apple JWS payload and normalise to
     * (smallest_unit_integer, decimals).
     *
     * Apple `price` is a number-or-string; `priceAmountMicros` (when present)
     * is integer-string with 6 decimals.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{0: int, 1: int}
     */
    private function extractPrice(array $payload): array
    {
        if (isset($payload['priceAmountMicros'])) {
            $raw = (string) $payload['priceAmountMicros'];

            return [(int) $raw, 6];
        }

        if (isset($payload['price']) && is_numeric($payload['price'])) {
            // Apple's `price` is documented to be the storefront amount in
            // minor units (decimals=2 for most storefronts). Avoid float casts:
            // operate on the string form and treat it as a minor-unit integer.
            $raw = (string) $payload['price'];
            // Strip any decimal portion (Apple emits ints in newer payloads;
            // older sandbox payloads emit decimals).
            if (str_contains($raw, '.')) {
                // A float such as 1.0E-5 prints with a dot and an exponent,
                // which bcmul() rejects; DecimalString expands it first.
                $major = DecimalString::fromNumeric($payload['price'], 2);
                $raw = $major === null ? '0' : bcmul($major, '100', 0);
            }

            return [(int) $raw, 2];
        }

        return [0, 2];
    }

    private function parseAppleEpochMillis(mixed $value): ?Carbon
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $millis = (int) $value;

        return Carbon::createFromTimestampMs($millis);
    }

    private function base64UrlDecode(string $input): string|false
    {
        $padded = strtr($input, '-_', '+/');
        $padLen = 4 - (strlen($padded) % 4);
        if ($padLen < 4) {
            $padded .= str_repeat('=', $padLen);
        }

        return base64_decode($padded, true);
    }
}
