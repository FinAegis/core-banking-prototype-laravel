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

it('GET /api/v1/ramp/supported returns stripe_bridge capabilities via the interface', function () {
    $user = App\Models\User::factory()->create(['kyc_status' => 'approved']);
    Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    $response = $this->getJson('/api/v1/ramp/supported');

    $response->assertOk();
    $response->assertJsonPath('data.provider', 'stripe_bridge');
    $response->assertJsonPath('data.crypto_currencies', ['USDC']);
    expect($response->json('data.fiat_currencies'))->toContain('USD');
});

// ──────────────────────────────────────────────────────────────────────────────
// End-to-end integration tests
// ──────────────────────────────────────────────────────────────────────────────

it('POST /api/v1/ramp/session persists stripe_session_id and stripe_client_secret', function () {
    $user = App\Models\User::factory()->create(['kyc_status' => 'approved']);
    Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions' => Http::response([
            'id'            => 'cos_test_created',
            'client_secret' => 'cs_live_secret_fake',
            'status'        => 'initialized',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/ramp/session', [
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => 100,
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.provider', 'stripe_bridge');

    $session = App\Models\RampSession::where('user_id', $user->id)->first();
    expect($session)->not->toBeNull();
    assert($session !== null);
    expect($session->provider)->toBe('stripe_bridge');
    expect($session->stripe_session_id)->toBe('cos_test_created');
    expect($session->stripe_client_secret)->toBe('cs_live_secret_fake');
});

it('GET /api/v1/ramp/quotes returns a single-element array with canonical payment methods', function () {
    $user = App\Models\User::factory()->create(['kyc_status' => 'approved']);
    Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/quotes*' => Http::response([
            'source_amount'      => '100.00',
            'destination_amount' => '98.50000000',
            'fees'               => ['total_fee' => '1.50', 'network_fee' => '0.50'],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/ramp/quotes?type=on&fiat=USD&amount=100&crypto=USDC');

    $response->assertOk();
    $response->assertJsonPath('data.provider', 'stripe_bridge');
    $quotes = $response->json('data.quotes');
    expect($quotes)->toHaveCount(1);
    expect($quotes[0]['payment_methods'])->toBe(['card', 'bank_transfer']);
});

it('rejects BTC on Stripe with a provider-named error message', function () {
    $user = App\Models\User::factory()->create(['kyc_status' => 'approved']);
    Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    $response = $this->postJson('/api/v1/ramp/session', [
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => 100,
        'crypto_currency' => 'BTC',
        'wallet_address'  => 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ]);

    $response->assertStatus(422);
    $errorMessage = $response->json('error.message');
    expect($errorMessage)->toContain('BTC')->toContain('stripe_bridge');
});

it('getSessionStatus does not clobber a webhook-set terminal status', function () {
    $user = App\Models\User::factory()->create(['kyc_status' => 'approved']);
    Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    // Seed a session that a webhook has already marked as completed
    $session = App\Models\RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => App\Models\RampSession::STATUS_COMPLETED,
        'crypto_amount'       => 98.5,
        'provider_session_id' => 'cos_test_terminal',
    ]);

    // Stripe happens to still say payment_pending (the webhook is ahead of the poll)
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_test_terminal' => Http::response([
            'id'                 => 'cos_test_terminal',
            'status'             => 'payment_pending',
            'destination_amount' => null,
        ], 200),
    ]);

    $response = $this->getJson("/api/v1/ramp/session/{$session->id}");

    $response->assertOk();
    $session->refresh();
    // Must remain COMPLETED — the pending-path early return plus the terminal
    // state check inside the transaction prevent the clobber.
    expect($session->status)->toBe(App\Models\RampSession::STATUS_COMPLETED);
    expect($session->crypto_amount)->toBe(98.5);
});

it('non-custody: a successful completion webhook writes zero rows to wallet/ledger tables', function () {
    $user = App\Models\User::factory()->create();
    App\Models\RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => App\Models\RampSession::STATUS_PENDING,
        'provider_session_id' => 'cos_test_noncustody',
    ]);

    // Capture row counts in fund-custody tables before the webhook fires.
    // - ledgers: event-sourced ledger entries (double-entry bookkeeping rows)
    // - transactions: event-sourced transaction aggregate rows
    // A non-custodial ramp completion must NOT write to either table because
    // no internal funds move — crypto goes directly to the user's external wallet.
    $tableCountsBefore = [
        'ledgers'      => Illuminate\Support\Facades\DB::table('ledgers')->count(),
        'transactions' => Illuminate\Support\Facades\DB::table('transactions')->count(),
    ];

    // Send the webhook
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $fixtures['session_completed']['data']['object']['id'] = 'cos_test_noncustody';
    $body = (string) json_encode($fixtures['session_completed']);
    $secret = 'whsec_test_fake';
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"],
        $body
    );

    $response->assertOk();

    // Assert zero row growth in balance/ledger tables
    foreach ($tableCountsBefore as $table => $before) {
        expect(Illuminate\Support\Facades\DB::table($table)->count())->toBe($before);
    }
});
