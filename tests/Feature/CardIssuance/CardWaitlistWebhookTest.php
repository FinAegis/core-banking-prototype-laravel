<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardWaitlistDeposit;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

// TestCase is bound folder-wide in tests/Pest.php — re-binding it here makes Pest refuse to load the file.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    // Empty secret bypasses signature verification in local/testing.
    config(['cards.stripe_webhook_secret' => '']);
    config(['services.stripe.cards_webhook_secret' => '']);
});

/**
 * Build a Stripe-event-shaped payload tagged with our card_waitlist_deposit
 * flow metadata. Mirrors the shape Stripe sends.
 *
 * @param  array<string, mixed>  $object
 *
 * @return array<string, mixed>
 */
function cardWebhookEvent(string $eventId, string $type, array $object): array
{
    return [
        'id'   => $eventId,
        'type' => $type,
        'data' => ['object' => $object],
    ];
}

it('transitions a pending_payment deposit to paid on checkout.session.completed (paid)', function () {
    $user = User::factory()->create();
    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_paid_xyz',
    ]);

    $payload = cardWebhookEvent('evt_paid_001', 'checkout.session.completed', [
        'id'             => 'cs_paid_xyz',
        'payment_status' => 'paid',
        'payment_intent' => 'pi_paid_xyz',
        'amount_total'   => 500,
        'currency'       => 'eur',
        'metadata'       => ['flow' => 'card_waitlist_deposit'],
    ]);

    $response = $this->postJson('/api/webhooks/stripe/cards', $payload);

    $response->assertOk();
    $response->assertJsonPath('replayed', false);

    $deposit->refresh();
    expect($deposit->status)->toBe('paid');
    expect($deposit->stripe_payment_intent_id)->toBe('pi_paid_xyz');
    expect($deposit->paid_at)->not()->toBeNull();
    expect($deposit->refund_eligible_after)->not()->toBeNull();

    // Outbox row written in the SAME transaction as dedup.
    expect(RevenueOutboxEvent::query()->count())->toBe(1);
    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox->source_type)->toBe('stripe_cards');
    expect($outbox->event_kind)->toBe('checkout.session.completed');
});

it('does NOT transition to paid when payment_status is not paid', function () {
    $user = User::factory()->create();
    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_unpaid_xyz',
    ]);

    $payload = cardWebhookEvent('evt_unpaid_001', 'checkout.session.completed', [
        'id'             => 'cs_unpaid_xyz',
        'payment_status' => 'unpaid',
        'metadata'       => ['flow' => 'card_waitlist_deposit'],
    ]);

    $this->postJson('/api/webhooks/stripe/cards', $payload)->assertOk();

    $deposit->refresh();
    expect($deposit->status)->toBe('pending_payment');
    expect(RevenueOutboxEvent::query()->count())->toBe(0);
});

it('ignores checkout sessions that are not our flow', function () {
    $user = User::factory()->create();
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_some_session',
    ]);

    $payload = cardWebhookEvent('evt_other_flow', 'checkout.session.completed', [
        'id'             => 'cs_some_session',
        'payment_status' => 'paid',
        'metadata'       => ['flow' => 'other_thing'],
    ]);

    $this->postJson('/api/webhooks/stripe/cards', $payload)->assertOk();

    expect(RevenueOutboxEvent::query()->count())->toBe(0);
    expect(CardWaitlistDeposit::query()->first()->status)->toBe('pending_payment');
});

it('transitions a pending deposit to expired on checkout.session.expired', function () {
    $user = User::factory()->create();
    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_expired_001',
    ]);

    $payload = cardWebhookEvent('evt_expired_001', 'checkout.session.expired', [
        'id'       => 'cs_expired_001',
        'metadata' => ['flow' => 'card_waitlist_deposit'],
    ]);

    $this->postJson('/api/webhooks/stripe/cards', $payload)->assertOk();

    $deposit->refresh();
    expect($deposit->status)->toBe('expired');
    expect($deposit->expired_at)->not()->toBeNull();
});

it('transitions a paid deposit to refunded on charge.refunded (negative outbox)', function () {
    $user = User::factory()->create();
    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_refund_xyz',
        'stripe_payment_intent_id'   => 'pi_refund_xyz',
        'paid_at'                    => now()->subHour(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    $payload = cardWebhookEvent('evt_refund_001', 'charge.refunded', [
        'id'              => 'ch_refund_xyz',
        'payment_intent'  => 'pi_refund_xyz',
        'amount_refunded' => 500,
        'currency'        => 'eur',
        'refunds'         => [
            'data' => [
                ['id' => 're_refund_xyz'],
            ],
        ],
    ]);

    $this->postJson('/api/webhooks/stripe/cards', $payload)->assertOk();

    $deposit->refresh();
    expect($deposit->status)->toBe('refunded');
    expect($deposit->refunded_at)->not()->toBeNull();
    expect($deposit->stripe_refund_id)->toBe('re_refund_xyz');

    $outbox = RevenueOutboxEvent::query()->where('event_kind', 'charge.refunded')->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->payload['amount'])->toBe(-500);
    expect($outbox->source_type)->toBe('stripe_cards');
});

it('is idempotent on Stripe event.id — redelivery is a no-op', function () {
    $user = User::factory()->create();
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_dedup_001',
    ]);

    $payload = cardWebhookEvent('evt_dedup_001', 'checkout.session.completed', [
        'id'             => 'cs_dedup_001',
        'payment_status' => 'paid',
        'payment_intent' => 'pi_dedup_001',
        'amount_total'   => 500,
        'currency'       => 'eur',
        'metadata'       => ['flow' => 'card_waitlist_deposit'],
    ]);

    $first = $this->postJson('/api/webhooks/stripe/cards', $payload);
    $first->assertOk();
    $first->assertJsonPath('replayed', false);

    expect(ProcessedWebhookEvent::query()->where('provider', 'stripe_cards')->count())->toBe(1);
    expect(RevenueOutboxEvent::query()->count())->toBe(1);

    $second = $this->postJson('/api/webhooks/stripe/cards', $payload);
    $second->assertOk();
    $second->assertJsonPath('replayed', true);

    expect(ProcessedWebhookEvent::query()->where('provider', 'stripe_cards')->count())->toBe(1);
    expect(RevenueOutboxEvent::query()->count())->toBe(1);
});

it('returns 400 on invalid signature when a secret is configured', function () {
    config(['cards.stripe_webhook_secret' => 'whsec_test_real']);

    $payload = cardWebhookEvent('evt_bad_sig', 'checkout.session.completed', [
        'id'             => 'cs_bad_sig',
        'payment_status' => 'paid',
        'metadata'       => ['flow' => 'card_waitlist_deposit'],
    ]);

    $response = $this->postJson('/api/webhooks/stripe/cards', $payload, [
        'Stripe-Signature' => 't=0,v1=invalid',
    ]);

    $response->assertStatus(400);
});
