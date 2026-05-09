<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Models\User;

beforeEach(function () {
    config(['cache.default' => 'array']);
    // Empty webhook secret bypasses signature verification in local/testing env.
    config(['services.stripe.webhook_secret' => '']);
});

it('writes a consent log row on customer.subscription.created with metadata', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_test_consent_001',
    ]);

    $payload = stripeEvent('evt_test_consent_001', 'customer.subscription.created', [
        'id'       => 'sub_test_001',
        'customer' => 'cus_test_consent_001',
        'metadata' => [
            'plan'                => 'monthly_pro',
            'consent_accepted_at' => now()->toIso8601String(),
            'consent_version'     => 1,
            'consent_text_hash'   => hash('sha256', 'I waive my 14-day right of withdrawal.'),
            'remote_ip_hash'      => hash_hmac('sha256', '203.0.113.10', (string) config('app.key')),
        ],
        'items' => [
            'data' => [[
                'price' => ['unit_amount' => 499, 'currency' => 'eur'],
            ]],
        ],
    ]);

    $response = $this->postJson('/api/webhooks/stripe/subscriptions', $payload);

    $response->assertStatus(200);
    $response->assertJsonPath('replayed', false);

    expect(SubscriptionConsentLog::query()->count())->toBe(1);
    $row = SubscriptionConsentLog::query()->first();
    expect($row->user_id)->toBe($user->id);
    expect($row->consent_version)->toBe(1);
});

it('writes an outbox row on customer.subscription.created', function () {
    User::factory()->create(['stripe_id' => 'cus_test_outbox_001']);

    $payload = stripeEvent('evt_outbox_initial', 'customer.subscription.created', [
        'id'       => 'sub_outbox_001',
        'customer' => 'cus_test_outbox_001',
        'items'    => [
            'data' => [[
                'price' => ['unit_amount' => 499, 'currency' => 'eur'],
            ]],
        ],
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', $payload)->assertStatus(200);

    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->source_type)->toBe('stripe');
    expect($outbox->source_event_id)->toBe('evt_outbox_initial');
    expect($outbox->event_kind)->toBe('customer.subscription.created');
    expect($outbox->status)->toBe('pending');
});

it('writes an outbox row on invoice.payment_succeeded', function () {
    User::factory()->create(['stripe_id' => 'cus_renewal']);

    $payload = stripeEvent('evt_renewal', 'invoice.payment_succeeded', [
        'id'           => 'in_renewal',
        'customer'     => 'cus_renewal',
        'subscription' => 'sub_renewal',
        'amount_paid'  => 499,
        'currency'     => 'eur',
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', $payload)->assertStatus(200);

    $outbox = RevenueOutboxEvent::query()->where('event_kind', 'invoice.payment_succeeded')->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->payload['amount'])->toBe(499);
    expect($outbox->payload['denomination'])->toBe('EUR');
});

it('is idempotent on Stripe event.id (redelivery is no-op)', function () {
    User::factory()->create(['stripe_id' => 'cus_dedup']);

    $payload = stripeEvent('evt_dedup_001', 'invoice.payment_succeeded', [
        'id'           => 'in_dedup',
        'customer'     => 'cus_dedup',
        'subscription' => 'sub_dedup',
        'amount_paid'  => 499,
        'currency'     => 'eur',
    ]);

    $first = $this->postJson('/api/webhooks/stripe/subscriptions', $payload);
    $first->assertStatus(200);
    $first->assertJsonPath('replayed', false);

    expect(ProcessedWebhookEvent::query()->count())->toBe(1);
    expect(RevenueOutboxEvent::query()->count())->toBe(1);

    $second = $this->postJson('/api/webhooks/stripe/subscriptions', $payload);
    $second->assertStatus(200);
    $second->assertJsonPath('replayed', true);

    // Still only one row in each table.
    expect(ProcessedWebhookEvent::query()->count())->toBe(1);
    expect(RevenueOutboxEvent::query()->count())->toBe(1);
});

it('writes a negative-amount outbox row on charge.refunded (ADR-0004 sign-prefix)', function () {
    User::factory()->create(['stripe_id' => 'cus_refund']);

    $payload = stripeEvent('evt_refund', 'charge.refunded', [
        'id'              => 'ch_refund',
        'customer'        => 'cus_refund',
        'amount_refunded' => 499,
        'currency'        => 'eur',
    ]);

    $this->postJson('/api/webhooks/stripe/subscriptions', $payload)->assertStatus(200);

    $outbox = RevenueOutboxEvent::query()->where('event_kind', 'charge.refunded')->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->payload['amount'])->toBe(-499);
});

/**
 * Build a Stripe-event-shaped JSON payload.
 *
 * @param array<string, mixed> $object
 *
 * @return array<string, mixed>
 */
function stripeEvent(string $eventId, string $type, array $object): array
{
    return [
        'id'   => $eventId,
        'type' => $type,
        'data' => ['object' => $object],
    ];
}
