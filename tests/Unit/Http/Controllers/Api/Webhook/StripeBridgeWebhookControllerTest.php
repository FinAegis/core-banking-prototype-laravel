<?php

declare(strict_types=1);

use App\Domain\Ramp\Services\StripeBridgeService;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    // Disable signature verification for tests
    config(['services.stripe.bridge_webhook_secret' => '']);
    $this->user = User::factory()->create();
});

function createStripeBridgeSession(int $userId, string $status = 'pending', string $stripeId = 'cos_test_123'): RampSession
{
    return RampSession::create([
        'user_id'             => $userId,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.00,
        'crypto_currency'     => 'USDC',
        'status'              => $status,
        'provider_session_id' => $stripeId,
        'stripe_session_id'   => $stripeId,
        'metadata'            => ['checkout_url' => 'https://crypto-onramp.stripe.com/test'],
    ]);
}

it('processes session updated webhook', function (): void {
    $session = createStripeBridgeSession($this->user->id);

    $response = $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.updated',
        'data' => [
            'object' => [
                'id'                 => 'cos_test_123',
                'status'             => 'payment_pending',
                'destination_amount' => '98.50',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['received' => true]);

    $session->refresh();
    expect($session->status)->toBe('processing');
});

it('processes session completed webhook', function (): void {
    $session = createStripeBridgeSession($this->user->id, 'processing');

    $response = $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.completed',
        'data' => [
            'object' => [
                'id'                 => 'cos_test_123',
                'status'             => 'fulfilled',
                'destination_amount' => '98.50',
            ],
        ],
    ]);

    $response->assertOk();

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->crypto_amount)->toBe(98.5);
});

it('skips terminal session status', function (): void {
    $session = createStripeBridgeSession($this->user->id, 'completed');

    $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.updated',
        'data' => [
            'object' => [
                'id'     => 'cos_test_123',
                'status' => 'payment_failed',
            ],
        ],
    ])->assertOk();

    $session->refresh();
    expect($session->status)->toBe('completed');
});

it('handles unknown session gracefully', function (): void {
    $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.updated',
        'data' => [
            'object' => [
                'id'     => 'cos_nonexistent',
                'status' => 'payment_pending',
            ],
        ],
    ])->assertOk();
});

it('rejects provider mismatch', function (): void {
    // Create a session with a different provider
    RampSession::create([
        'user_id'             => $this->user->id,
        'provider'            => 'onramper',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.00,
        'crypto_currency'     => 'USDC',
        'status'              => 'pending',
        'provider_session_id' => 'cos_mismatch',
        'stripe_session_id'   => 'cos_mismatch',
        'metadata'            => [],
    ]);

    $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.updated',
        'data' => [
            'object' => [
                'id'     => 'cos_mismatch',
                'status' => 'fulfilled',
            ],
        ],
    ])->assertOk();

    // Session should not be updated
    $session = RampSession::where('stripe_session_id', 'cos_mismatch')->first();
    expect($session)->not->toBeNull();
    assert($session instanceof RampSession);
    expect($session->status)->toBe('pending');
});

it('maps stripe statuses correctly', function (): void {
    $service = app(StripeBridgeService::class);

    expect($service->mapStripeStatus('initialized'))->toBe('pending');
    expect($service->mapStripeStatus('payment_pending'))->toBe('processing');
    expect($service->mapStripeStatus('payment_complete'))->toBe('processing');
    expect($service->mapStripeStatus('fulfilled'))->toBe('completed');
    expect($service->mapStripeStatus('payment_failed'))->toBe('failed');
    expect($service->mapStripeStatus('expired'))->toBe('expired');
    expect($service->mapStripeStatus('unknown_status'))->toBe('processing');
});

it('maps stripe status labels correctly', function (): void {
    $service = app(StripeBridgeService::class);

    expect($service->mapStripeStatusLabel('initialized'))->toBe('Waiting for payment');
    expect($service->mapStripeStatusLabel('payment_pending'))->toBe('Payment processing');
    expect($service->mapStripeStatusLabel('payment_complete'))->toBe('Sending crypto');
    expect($service->mapStripeStatusLabel('fulfilled'))->toBe('Completed');
    expect($service->mapStripeStatusLabel('payment_failed'))->toBe('Payment failed');
    expect($service->mapStripeStatusLabel('expired'))->toBe('Session expired');
});

it('handles expired status in webhook', function (): void {
    $session = createStripeBridgeSession($this->user->id);

    $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'crypto_onramp.session.updated',
        'data' => [
            'object' => [
                'id'     => 'cos_test_123',
                'status' => 'expired',
            ],
        ],
    ])->assertOk();

    $session->refresh();
    expect($session->status)->toBe('expired');
});

it('handles unrecognized event types gracefully', function (): void {
    $this->postJson('/api/webhooks/stripe/bridge', [
        'type' => 'some.unknown.event',
        'data' => [
            'object' => ['id' => 'cos_test_123'],
        ],
    ])->assertOk();
});
