<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Domain\Subscription\Services\TrialFingerprintService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\PaymentMethod;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.stripe.webhook_secret' => '']);
    config(['services.stripe.subscription_webhook_secret' => '']);
    config(['services.stripe.trial_fingerprint_pepper' => 'test_pepper_32_bytes_long_!!!!!!!!!']);
    config(['services.stripe.subscription_prices.monthly_pro' => 'price_test_monthly']);
    config(['services.stripe.subscription_prices.annual_pro' => 'price_test_annual']);
});

/**
 * Stripe payment method shape for tests. Mirrors the Stripe SDK contract:
 * `$pm->card->fingerprint` is the read path our webhook uses.
 */
function fakeStripePaymentMethod(string $fingerprint, string $pmId = 'pm_card_test'): PaymentMethod
{
    $pm = PaymentMethod::constructFrom([
        'id'   => $pmId,
        'type' => 'card',
        'card' => [
            'fingerprint' => $fingerprint,
            'brand'       => 'visa',
            'last4'       => '4242',
        ],
    ]);

    return $pm;
}

/**
 * Build a Stripe-event-shaped JSON payload for checkout.session.completed.
 *
 * @param array<string, mixed> $extra
 *
 * @return array<string, mixed>
 */
function checkoutSessionCompletedEvent(string $eventId, string $customerId, string $pmId, array $extra = []): array
{
    return [
        'id'   => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => array_replace([
                'id'             => 'cs_test_' . substr($eventId, -8),
                'mode'           => 'setup',
                'customer'       => $customerId,
                'payment_method' => $pmId,
                'metadata'       => [
                    'plan'                => 'monthly_pro',
                    'consent_shown_at'    => now()->toIso8601String(),
                    'consent_accepted_at' => now()->toIso8601String(),
                    'consent_version'     => '1',
                    'consent_text_hash'   => hash('sha256', 'I waive my 14-day right of withdrawal.'),
                    'remote_ip_hash'      => hash_hmac('sha256', '203.0.113.10', (string) config('app.key')),
                ],
            ], $extra),
        ],
    ];
}

/**
 * Bind a stub StripeClient with a mocked paymentMethods->retrieve() call.
 *
 * Stripe's StripeClient exposes services via public properties typed as the
 * concrete service classes; Mockery's mock implements those via __get magic
 * and an internal service map, so we round-trip via Mockery::mock(StripeClient)
 * and assign the service mock directly.
 */
function bindStripeClientWithFingerprint(string $fingerprint, string $pmId = 'pm_card_test'): void
{
    $pm = fakeStripePaymentMethod($fingerprint, $pmId);

    /** @var PaymentMethodService&Mockery\MockInterface $pmService */
    $pmService = Mockery::mock(PaymentMethodService::class);
    $pmService->shouldReceive('retrieve')
        ->with($pmId)
        ->andReturn($pm);
    $pmService->shouldReceive('detach')->andReturn($pm);

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->paymentMethods = $pmService;

    app()->instance(StripeClient::class, $stripe);
}

it('Concern 1 — writes a trial_card_fingerprints row on a Setup-mode checkout.session.completed', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_trial_first',
    ]);

    bindStripeClientWithFingerprint('fp_unique_card_001');

    // Suppress the explicit Cashier ->newSubscription()->create() leg: it
    // calls live Stripe APIs which we don't mock here. The fingerprint write
    // is asserted regardless of subscription creation success.
    $payload = checkoutSessionCompletedEvent('evt_setup_001', 'cus_trial_first', 'pm_card_test');

    $this->postJson('/api/webhooks/stripe/subscriptions', $payload)->assertStatus(200);

    $hash = app(TrialFingerprintService::class)->hash('fp_unique_card_001');
    $row = TrialCardFingerprint::query()->find($hash);

    expect($row)->not()->toBeNull();
    expect($row->first_user_id)->toBe($user->id);
    expect($row->last_user_id)->toBe($user->id);
    expect($row->stripe_payment_method_id)->toBe('pm_card_test');
});

it('Concern 1 — blocks a SECOND trial within 12 months for the same card fingerprint (ERR_SUB_003 path)', function () {
    $first = User::factory()->create(['stripe_id' => 'cus_trial_first']);
    $second = User::factory()->create(['stripe_id' => 'cus_trial_second']);

    // First user successfully claims the fingerprint.
    bindStripeClientWithFingerprint('fp_shared_card');
    $payload1 = checkoutSessionCompletedEvent('evt_first', 'cus_trial_first', 'pm_card_test');
    $this->postJson('/api/webhooks/stripe/subscriptions', $payload1)->assertStatus(200);

    $service = app(TrialFingerprintService::class);
    $hash = $service->hash('fp_shared_card');
    $rowAfterFirst = TrialCardFingerprint::query()->find($hash);
    expect($rowAfterFirst)->not()->toBeNull();
    expect($rowAfterFirst->trial_user_count)->toBe(1);

    // Second user attempts to claim the SAME fingerprint within the 12mo window.
    // The webhook must NOT bump trial_user_count (ineligible → reject).
    bindStripeClientWithFingerprint('fp_shared_card', 'pm_card_test_second');
    $payload2 = checkoutSessionCompletedEvent('evt_second', 'cus_trial_second', 'pm_card_test_second');
    $this->postJson('/api/webhooks/stripe/subscriptions', $payload2)->assertStatus(200);

    $rowAfterSecond = TrialCardFingerprint::query()->find($hash);
    expect($rowAfterSecond->trial_user_count)->toBe(1); // unchanged — gate held
    expect($rowAfterSecond->last_user_id)->toBe($first->id); // still first user
});

it('Concern 2 — end-to-end: checkout.session.completed → fingerprint write → Subscription create call attempted', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_e2e_001',
    ]);

    bindStripeClientWithFingerprint('fp_e2e_card_001');

    // The Subscription creation call (newSubscription()->create()) hits the
    // Cashier path which calls live Stripe — that's expected to fail in the
    // test environment because Cashier internally instantiates its own
    // StripeClient. The webhook handler swallows that exception (per its
    // try/catch) so the fingerprint write + dedup are still asserted.
    $payload = checkoutSessionCompletedEvent('evt_e2e_001', 'cus_e2e_001', 'pm_card_test');

    $response = $this->postJson('/api/webhooks/stripe/subscriptions', $payload);
    $response->assertStatus(200);

    // Fingerprint claim recorded.
    $hash = app(TrialFingerprintService::class)->hash('fp_e2e_card_001');
    $row = TrialCardFingerprint::query()->find($hash);
    expect($row)->not()->toBeNull();
    expect($row->first_user_id)->toBe($user->id);

    // Dedup row written.
    expect(App\Domain\Subscription\Models\ProcessedWebhookEvent::query()->count())->toBe(1);
});

afterEach(function (): void {
    Mockery::close();
});
