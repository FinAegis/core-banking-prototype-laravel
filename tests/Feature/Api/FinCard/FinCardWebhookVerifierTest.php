<?php

/**
 * Tests for FinCardWebhookVerifier.
 *
 * FinCard signs webhooks with SHA256withRSA over the RAW body (no timestamp
 * wrapper), base64-encoded, delivered in either the X-FC-SIGNATURE or
 * X-WSB-SIGNATURE header. The public key is FinCard's platform key.
 */

declare(strict_types=1);

use App\Infrastructure\FinCard\FinCardWebhookVerifier;

/**
 * @return array{0: string, 1: string} [privatePem, publicPem]
 */
function finCardTestKeypair(): array
{
    $res = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    if ($res === false) {
        throw new RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
    }

    openssl_pkey_export($res, $privatePem);
    $details = openssl_pkey_get_details($res);
    if ($details === false) {
        throw new RuntimeException('openssl_pkey_get_details failed');
    }

    return [(string) $privatePem, (string) $details['key']];
}

/** Produce a valid base64 SHA256withRSA signature over the raw body. */
function signFinCard(string $body, string $privatePem): string
{
    $ok = openssl_sign($body, $rawSig, $privatePem, OPENSSL_ALGO_SHA256);
    if ($ok === false) {
        throw new RuntimeException('openssl_sign failed: ' . openssl_error_string());
    }

    return base64_encode($rawSig);
}

it('accepts a valid RSA signature over the raw body', function () {
    [$priv, $pub] = finCardTestKeypair();
    $body = '{"eventType":"create","data":{"cardId":"c1"}}';

    $verifier = new FinCardWebhookVerifier($pub);

    expect($verifier->verify($body, signFinCard($body, $priv)))->toBeTrue();
});

it('rejects a tampered body', function () {
    [$priv, $pub] = finCardTestKeypair();
    $body = '{"eventType":"create"}';
    $sig = signFinCard($body, $priv);

    $verifier = new FinCardWebhookVerifier($pub);

    expect($verifier->verify($body . ' ', $sig))->toBeFalse();
});

it('rejects a signature made with the wrong key', function () {
    [$privAttacker] = finCardTestKeypair();
    [, $pubReal] = finCardTestKeypair();
    $body = '{"eventType":"create"}';

    $verifier = new FinCardWebhookVerifier($pubReal);

    expect($verifier->verify($body, signFinCard($body, $privAttacker)))->toBeFalse();
});

it('rejects an empty or non-base64 signature', function () {
    [, $pub] = finCardTestKeypair();
    $verifier = new FinCardWebhookVerifier($pub);

    expect($verifier->verify('{}', ''))->toBeFalse()
        ->and($verifier->verify('{}', '!!!not base64!!!'))->toBeFalse();
});

it('accepts any request in non-production when no key is configured (dev passthrough)', function () {
    $verifier = new FinCardWebhookVerifier('');

    expect($verifier->verify('{}', 'anything'))->toBeTrue();
});

it('fails closed in production when no key is configured', function () {
    $original = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        $verifier = new FinCardWebhookVerifier('');
        expect($verifier->verify('{}', 'anything'))->toBeFalse();
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
});

it('normalizes a base64-encoded PEM public key', function () {
    [$priv, $pub] = finCardTestKeypair();
    $body = '{"eventType":"create"}';

    // FinCard's key pasted as a single-line base64 blob (a friendly .env form).
    $verifier = new FinCardWebhookVerifier(base64_encode($pub));

    expect($verifier->verify($body, signFinCard($body, $priv)))->toBeTrue();
});

it('resolves the signature from either header name', function () {
    // X-FC-SIGNATURE preferred.
    expect(FinCardWebhookVerifier::signatureFrom(fn (string $h): ?string => match ($h) {
        'X-FC-SIGNATURE' => 'fc-sig',
        default          => null,
    }))->toBe('fc-sig');

    // Falls back to X-WSB-SIGNATURE.
    expect(FinCardWebhookVerifier::signatureFrom(fn (string $h): ?string => match ($h) {
        'X-WSB-SIGNATURE' => 'wsb-sig',
        default           => null,
    }))->toBe('wsb-sig');

    // Neither present → empty.
    expect(FinCardWebhookVerifier::signatureFrom(fn (string $h): ?string => null))->toBe('');
});
