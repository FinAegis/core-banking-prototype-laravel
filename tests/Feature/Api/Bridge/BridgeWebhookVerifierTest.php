<?php

/**
 * Unit-level tests for BridgeWebhookVerifier covering BOTH signature schemes:
 *
 *  - v0 / asymmetric (Bridge's current "Bridge by Stripe" platform):
 *    header `X-Webhook-Signature: t=<ms>,v0=<base64(RSA-SHA256 sig)>`, signed
 *    over `<timestamp>.<raw_body>`, verified against a per-endpoint RSA public
 *    key. There is NO shared secret in this model.
 *  - v1 / HMAC (legacy / test fallback): `t=<sec>,v1=<hex_hmac_sha256>`.
 */

declare(strict_types=1);

use App\Infrastructure\Bridge\BridgeWebhookVerifier;

/**
 * @return array{0: string, 1: string} [privatePem, publicPem]
 */
function bridgeTestKeypair(): array
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

/** Produce a valid asymmetric `X-Webhook-Signature` header value. */
function signBridgeAsymmetric(string $body, string $privatePem, ?int $tsMs = null): string
{
    $tsMs ??= time() * 1000;
    $message = $tsMs . '.' . $body;

    $ok = openssl_sign($message, $rawSig, $privatePem, OPENSSL_ALGO_SHA256);
    if ($ok === false) {
        throw new RuntimeException('openssl_sign failed: ' . openssl_error_string());
    }

    return 't=' . $tsMs . ',v0=' . base64_encode($rawSig);
}

it('accepts a valid asymmetric (v0) signature', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1","type":"customer.kyc_link_completed"}';

    $verifier = new BridgeWebhookVerifier('', $pub);

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv)))->toBeTrue();
});

it('rejects a tampered body under the v0 scheme', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1","type":"customer.kyc_link_completed"}';
    $header = signBridgeAsymmetric($body, $priv);

    $verifier = new BridgeWebhookVerifier('', $pub);

    // Signature was computed over the original body; a trailing space breaks it.
    expect($verifier->verify($body . ' ', $header))->toBeFalse();
});

it('rejects a v0 signature made with the wrong key', function () {
    [$privAttacker] = bridgeTestKeypair();
    [, $pubReal] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';

    $verifier = new BridgeWebhookVerifier('', $pubReal);

    expect($verifier->verify($body, signBridgeAsymmetric($body, $privAttacker)))->toBeFalse();
});

it('rejects a v0 signature with a stale timestamp (replay window)', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';
    // 11 minutes ago, in ms — beyond Bridge's ~10 minute advice.
    $staleMs = (time() - 660) * 1000;

    $verifier = new BridgeWebhookVerifier('', $pub);

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv, $staleMs)))->toBeFalse();
});

it('rejects a v0 signature when no public key is configured', function () {
    [$priv] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';

    // Secret configured but no public key: a v0 header cannot be verified.
    $verifier = new BridgeWebhookVerifier('whsec_x', '');

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv)))->toBeFalse();
});

it('still accepts a valid legacy HMAC (v1) signature when a secret is set', function () {
    $secret = 'whsec_test';
    $body = '{"id":"evt_1"}';
    $ts = time();
    $sig = hash_hmac('sha256', $ts . '.' . $body, $secret);

    $verifier = new BridgeWebhookVerifier($secret, '');

    expect($verifier->verify($body, "t={$ts},v1={$sig}"))->toBeTrue();
});

it('rejects a tampered HMAC (v1) signature', function () {
    $verifier = new BridgeWebhookVerifier('whsec_test', '');
    $body = '{"id":"evt_1"}';
    $ts = time();

    expect($verifier->verify($body, "t={$ts},v1=deadbeef"))->toBeFalse();
});

it('falls through to dev passthrough when no credentials are configured (non-production)', function () {
    $verifier = new BridgeWebhookVerifier('', '');

    // testing environment → permissive so the local dev loop works.
    expect($verifier->verify('{}', 't=1,v0=abc'))->toBeTrue();
});

it('fails closed when no credentials are configured in production', function () {
    $original = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        $verifier = new BridgeWebhookVerifier('', '');
        expect($verifier->verify('{}', 't=1,v0=abc'))->toBeFalse();
    } finally {
        app()->detectEnvironment(fn () => $original);
    }
});

it('rejects a legacy HMAC (v1) signature under production config (public key only)', function () {
    [, $pub] = bridgeTestKeypair();
    $secret = 'whsec_test';
    $body = '{"id":"evt_1"}';
    $ts = time();
    $sig = hash_hmac('sha256', $ts . '.' . $body, $secret);

    // Production configures ONLY the public key (no HMAC secret). A v1 header
    // must not be honoured — proves there is no scheme-downgrade path.
    $verifier = new BridgeWebhookVerifier('', $pub);

    expect($verifier->verify($body, "t={$ts},v1={$sig}"))->toBeFalse();
});

it('accepts v0 under production config (public key only, empty secret)', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_prod"}';

    $verifier = new BridgeWebhookVerifier('', $pub);

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv)))->toBeTrue();
});

it('rejects a header with an empty or zero timestamp', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';
    $verifier = new BridgeWebhookVerifier('', $pub);

    $valid = signBridgeAsymmetric($body, $priv);
    $v0 = substr($valid, (int) strpos($valid, 'v0='));

    expect($verifier->verify($body, "t=,{$v0}"))->toBeFalse();
    expect($verifier->verify($body, "t=0,{$v0}"))->toBeFalse();
    expect($verifier->verify($body, 'v0=abc'))->toBeFalse(); // no t= at all
});

it('accepts a PEM public key supplied base64-encoded (single-line env style)', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';

    $verifier = new BridgeWebhookVerifier('', base64_encode($pub));

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv)))->toBeTrue();
});

it('accepts a base64 public key wrapped in surrounding quotes', function () {
    [$priv, $pub] = bridgeTestKeypair();
    $body = '{"id":"evt_1"}';

    // A value pasted into .env as BRIDGE_WEBHOOK_PUBLIC_KEY="<base64>".
    $verifier = new BridgeWebhookVerifier('', '"' . base64_encode($pub) . '"');

    expect($verifier->verify($body, signBridgeAsymmetric($body, $priv)))->toBeTrue();
});
