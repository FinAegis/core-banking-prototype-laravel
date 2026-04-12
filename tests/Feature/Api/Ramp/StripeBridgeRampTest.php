<?php

declare(strict_types=1);

use App\Domain\Ramp\Services\StripeBridgeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
    ]);
});

it('fetches a Stripe onramp session via getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_test_abc123' => Http::response([
            'id'                   => 'cos_test_abc123',
            'status'               => 'fulfilled',
            'source_amount'        => '100.00',
            'destination_amount'   => '98.50000000',
            'destination_currency' => 'usdc',
        ], 200),
    ]);

    $service = new StripeBridgeService();
    $result = $service->getSession('cos_test_abc123');

    expect($result)
        ->toHaveKeys(['status', 'destination_amount', 'raw'])
        ->and($result['status'])->toBe('fulfilled')
        ->and($result['destination_amount'])->toBe('98.50000000');
});

it('throws RuntimeException when Stripe returns 404 for getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_missing' => Http::response([
            'error' => ['message' => 'No such session', 'type' => 'invalid_request_error'],
        ], 404),
    ]);

    $service = new StripeBridgeService();
    $service->getSession('cos_missing');
})->throws(RuntimeException::class);

// ──────────────────────────────────────────────────────────────────────────────
// Signature verification
// ──────────────────────────────────────────────────────────────────────────────

it('accepts a valid Stripe-Signature header with a fresh timestamp', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeTrue();
});

it('rejects a tampered body even with a valid-looking signature', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $originalBody = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $tamperedBody = '{"id":"evt_test","type":"crypto_onramp_session.completed"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', $timestamp . '.' . $originalBody, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($tamperedBody, $header))->toBeFalse();
});

it('rejects a timestamp older than the 300s replay window', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $timestamp = time() - 600;  // 10 minutes ago
    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeFalse();
});

it('rejects a header missing the v1 signature element', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $validator = $provider->getWebhookValidator();
    $timestamp = time();

    expect($validator('{}', "t={$timestamp}"))->toBeFalse();
});

it('rejects an empty signature header', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $validator = $provider->getWebhookValidator();

    expect($validator('{}', ''))->toBeFalse();
});

it('accepts any of multiple v1 signature entries', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"test":"multi"}';
    $timestamp = time();
    $correct = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1=decoy_signature_1,v1={$correct},v1=decoy_signature_2";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// Payload normalisation
// ──────────────────────────────────────────────────────────────────────────────

it('normalizes a Stripe session.updated event into the canonical shape', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    $result = $provider->normalizeWebhookPayload($fixtures['session_updated']);

    expect($result)->not->toBeNull();
    assert($result !== null);
    expect($result['session_id'])->toBe('cos_test_abc123');
    expect($result['status'])->toBe(App\Models\RampSession::STATUS_PROCESSING);
    expect($result['crypto_amount'])->toBeNull();
    expect($result['raw'])->toBeArray();
});

it('normalizes a Stripe session.completed event with destination_amount', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    $result = $provider->normalizeWebhookPayload($fixtures['session_completed']);

    expect($result)->not->toBeNull();
    assert($result !== null);
    expect($result['session_id'])->toBe('cos_test_abc123');
    expect($result['status'])->toBe(App\Models\RampSession::STATUS_COMPLETED);
    expect($result['crypto_amount'])->toBe('98.50000000');
});

it('returns null for an unrelated Stripe event type', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    expect($provider->normalizeWebhookPayload($fixtures['unrelated_event']))->toBeNull();
});

it('returns null for a malformed event without a session id', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    expect($provider->normalizeWebhookPayload($fixtures['session_without_id']))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Capability + signature header
// ──────────────────────────────────────────────────────────────────────────────

it('returns the correct webhook signature header name', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    expect($provider->getWebhookSignatureHeader())->toBe('Stripe-Signature');
});

it('returns supported currencies in the canonical keyed shape', function () {
    $provider = app(App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $supported = $provider->getSupportedCurrencies();

    expect($supported)
        ->toHaveKeys(['fiatCurrencies', 'cryptoCurrencies', 'modes', 'limits'])
        ->and($supported['fiatCurrencies'])->toContain('USD')
        ->and($supported['cryptoCurrencies'])->toContain('USDC')
        ->and($supported['limits'])->toHaveKeys(['minAmount', 'maxAmount', 'dailyLimit']);
});
