<?php

declare(strict_types=1);

use App\Models\RampSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'ramp.default_provider'                 => 'stripe_bridge',
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
    ]);
});

function signStripeBody(string $body, string $secret = 'whsec_test_fake', ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    return "t={$timestamp},v1={$signature}";
}

it('returns 404 for an unknown provider name', function () {
    $response = $this->postJson('/api/v1/ramp/webhook/pretend_provider', []);

    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'UNKNOWN_PROVIDER');
});

it('returns 400 when the Stripe signature is invalid', function () {
    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef'],
        '{"id":"evt_test","type":"crypto_onramp_session.updated"}'
    );

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'INVALID_SIGNATURE');
});

it('returns 400 when the Stripe-Signature header is missing', function () {
    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        [],
        '{"id":"evt_test"}'
    );

    $response->assertStatus(400);
});

it('returns 200 for a valid signature on an ignored event type', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = (string) json_encode($fixtures['unrelated_event']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
});

it('processes a valid session.completed event and updates the session row', function () {
    $user = User::factory()->create();
    $session = RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => RampSession::STATUS_PENDING,
        'provider_session_id' => 'cos_test_abc123',
    ]);

    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = (string) json_encode($fixtures['session_completed']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
    $session->refresh();
    expect($session->status)->toBe(RampSession::STATUS_COMPLETED)
        ->and($session->crypto_amount)->toBe(98.5);
});

it('preserves raw body bytes end-to-end (no JSON re-encoding)', function () {
    // This body has unusual whitespace and key ordering that Laravel's
    // decode+encode cycle would rewrite. If the signature was computed
    // over these exact bytes, only a raw-body-preserving controller passes.
    $body = "{\n  \"id\":\"evt_raw_test\",\n  \"type\":\"crypto_onramp_session.updated\",\n  \"data\":{\"object\":{\"id\":\"cos_raw_test\",\"status\":\"initialized\"}}\n}";
    $header = signStripeBody($body);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $header],
        $body
    );

    // The signature should verify successfully against the raw body.
    // Session won't be found, but the controller should have progressed
    // past signature verification — so 200, not 400.
    $response->assertOk();
});

it('idempotent: replaying a completed event on a terminal session is a no-op', function () {
    $user = User::factory()->create();
    $session = RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => RampSession::STATUS_COMPLETED,
        'crypto_amount'       => 98.5,
        'provider_session_id' => 'cos_test_abc123',
    ]);

    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = (string) json_encode($fixtures['session_completed']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
    $session->refresh();
    expect($session->status)->toBe(RampSession::STATUS_COMPLETED);
});
