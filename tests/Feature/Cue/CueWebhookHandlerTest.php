<?php

/**
 * CueWebhookHandlerTest — tests for slice 4 additions to SubscriptionWebhookController.
 *
 * Covers:
 *  - customer.subscription.trial_will_end — dispatches trial-ending delayed jobs
 *  - customer.subscription.updated status='past_due' — inserts grace_period_started cue
 *  - invoice.payment_failed — inserts payment_failed cue
 *  - invoice.payment_succeeded (after failure) — dismisses payment_failed cue
 *  - customer.subscription.deleted (user cancel) — inserts subscription_canceled_external cue
 *  - charge.refunded — inserts refund_processed cue
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.7
 */

declare(strict_types=1);

use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1d;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1h;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding2d;
use App\Domain\Subscription\Models\Cue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.stripe.subscription_webhook_secret' => '']);
    Queue::fake();
});

/**
 * Build a minimal Stripe event payload for testing.
 *
 * @param array<string, mixed> $object
 */
function stripeEvent(string $type, array $object): string
{
    return (string) json_encode([
        'id'   => 'evt_test_' . substr(md5(uniqid()), 0, 8),
        'type' => $type,
        'data' => ['object' => $object],
    ], JSON_THROW_ON_ERROR);
}

it('inserts payment_failed cue on invoice.payment_failed', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_pf001']);

    $payload = stripeEvent('invoice.payment_failed', [
        'id'           => 'in_pf001',
        'customer'     => 'cus_pf001',
        'amount_due'   => 999,
        'currency'     => 'eur',
        'period_start' => now()->startOfMonth()->timestamp,
    ]);

    $response = $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true));

    $response->assertOk();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'payment_failed')->first();
    expect($cue)->not()->toBeNull();
    expect($cue->priority)->toBe('critical');
});

it('dismisses payment_failed cue on invoice.payment_succeeded', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_ps001']);

    // Pre-create a payment_failed cue.
    Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subHour(),
        'expires_at'      => now()->addWeek(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:payment_failed:window1"),
    ]);

    $payload = stripeEvent('invoice.payment_succeeded', [
        'id'           => 'in_ps001',
        'customer'     => 'cus_ps001',
        'amount_paid'  => 999,
        'currency'     => 'eur',
        'subscription' => 'sub_ps001',
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'payment_failed')->first();
    expect($cue->dismissed_at)->not()->toBeNull();
});

it('inserts grace_period_started cue on subscription.updated status=past_due', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_gps001']);

    $payload = stripeEvent('customer.subscription.updated', [
        'id'                   => 'sub_gps001',
        'customer'             => 'cus_gps001',
        'status'               => 'past_due',
        'current_period_start' => now()->startOfMonth()->timestamp,
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'grace_period_started')->first();
    expect($cue)->not()->toBeNull();
    expect($cue->priority)->toBe('high');
    $payload = $cue->payload;
    expect($payload['source'])->toBe('stripe');
});

it('inserts subscription_canceled_external cue on user-initiated cancel', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_sce001']);

    $payload = stripeEvent('customer.subscription.deleted', [
        'id'                   => 'sub_sce001',
        'customer'             => 'cus_sce001',
        'status'               => 'canceled',
        'canceled_at'          => now()->timestamp,
        'cancellation_details' => ['reason' => 'cancellation_requested'],
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'subscription_canceled_external')->first();
    expect($cue)->not()->toBeNull();
});

it('does NOT insert subscription_canceled_external for non-user cancels', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_sce002']);

    $payload = stripeEvent('customer.subscription.deleted', [
        'id'                   => 'sub_sce002',
        'customer'             => 'cus_sce002',
        'status'               => 'canceled',
        'canceled_at'          => now()->timestamp,
        'cancellation_details' => ['reason' => 'payment_failed'],
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    expect(Cue::query()->where('user_id', $user->id)->where('kind', 'subscription_canceled_external')->count())->toBe(0);
});

it('inserts refund_processed cue on charge.refunded', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_rp001']);

    $payload = stripeEvent('charge.refunded', [
        'id'              => 'ch_rp001',
        'customer'        => 'cus_rp001',
        'amount_refunded' => 999,
        'currency'        => 'eur',
        'created'         => now()->timestamp,
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'refund_processed')->first();
    expect($cue)->not()->toBeNull();
});

it('dispatches trial_ending delayed jobs on customer.subscription.trial_will_end', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_twe001']);
    $trialEnd = now()->addDays(3)->timestamp;

    $payload = stripeEvent('customer.subscription.trial_will_end', [
        'id'         => 'sub_twe001',
        'customer'   => 'cus_twe001',
        'trial_end'  => $trialEnd,
        'start_date' => now()->timestamp,
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', json_decode($payload, true))->assertOk();

    Queue::assertPushed(EnqueueTrialEnding2d::class);
    Queue::assertPushed(EnqueueTrialEnding1d::class);
    Queue::assertPushed(EnqueueTrialEnding1h::class);
});

it('replayed webhook event is idempotent — does not double-insert cue', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_idem001']);

    $eventBody = [
        'id'   => 'evt_idem_001',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id'              => 'ch_idem001',
            'customer'        => 'cus_idem001',
            'amount_refunded' => 500,
            'currency'        => 'eur',
            'created'         => now()->timestamp,
        ]],
    ];

    $this->postJson('/api/webhooks/stripe/subscriptions', $eventBody)->assertOk();
    $this->postJson('/api/webhooks/stripe/subscriptions', $eventBody)->assertOk();

    // Only one cue should exist (dedup via processed_webhook_events).
    expect(Cue::query()->where('user_id', $user->id)->where('kind', 'refund_processed')->count())->toBe(1);
});
