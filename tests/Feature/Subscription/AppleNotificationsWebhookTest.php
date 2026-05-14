<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config([
        'subscription.iap.apple.bundle_id'   => 'app.zelta',
        'subscription.iap.apple.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.receipt_pepper' => 'test-pepper',
    ]);
});

/**
 * @param array<string, mixed> $payloadOverrides
 */
function appleJwsForWebhook(array $payloadOverrides): string
{
    $payload = array_merge([
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'webhook-tx-001',
        'transactionId'         => 'webhook-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'purchaseDate'          => 1_715_000_000_000,
        'expiresDate'           => 1_720_000_000_000,
        'inAppOwnershipType'    => 'PURCHASED',
        'environment'           => 'Production',
    ], $payloadOverrides);

    return appleEncodeJws($payload);
}

/**
 * @param array<string, mixed> $payload
 */
function appleEncodeJws(array $payload): string
{
    $header = base64UrlEncodeWebhook((string) json_encode(['alg' => 'ES256']));
    $payloadB64 = base64UrlEncodeWebhook((string) json_encode($payload));
    $sig = base64UrlEncodeWebhook('signature');

    return "{$header}.{$payloadB64}.{$sig}";
}

function base64UrlEncodeWebhook(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Build an Apple ASN V2 envelope with the given transaction info.
 *
 * @param array<string, mixed> $extra
 *
 * @return array<string, mixed>
 */
function appleNotification(string $notificationType, ?string $subtype, array $extra): array
{
    return [
        'notificationType' => $notificationType,
        'subtype'          => $subtype,
        'notificationUUID' => (string) Str::uuid(),
        'data'             => [
            'transactionInfo' => array_merge([
                'originalTransactionId' => 'webhook-tx-001',
                'productId'             => 'zelta_pro_monthly',
                'currency'              => 'EUR',
                'price'                 => 499,
                'expiresDate'           => 1_720_000_000_000,
            ], $extra),
        ],
    ];
}

it('dedups Apple notifications on notificationUUID', function () {
    $payload = appleNotification('DID_RENEW', null, []);

    $resp1 = $this->postJson('/api/webhooks/apple/notifications', $payload);
    $resp2 = $this->postJson('/api/webhooks/apple/notifications', $payload);

    $resp1->assertStatus(200);
    $resp2->assertStatus(200);

    expect(ProcessedWebhookEvent::query()->where('provider', 'apple')->count())->toBe(1);
});

it('processes DID_RENEW and updates current_period_ends_at + writes outbox', function () {
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'renewal-tx-001',
        'current_period_ends_at'  => now()->subDay(),
        'cancel_at_period_end'    => false,
    ]);

    $payload = appleNotification('DID_RENEW', null, [
        'originalTransactionId' => 'renewal-tx-001',
        'expiresDate'           => 1_720_000_000_000,
    ]);

    $response = $this->postJson('/api/webhooks/apple/notifications', $payload);

    $response->assertStatus(200);
    $sub->refresh();
    assert($sub->current_period_ends_at !== null);
    expect($sub->current_period_ends_at->timestamp)->toBe(1_720_000_000);
    expect($sub->last_notification_type)->toBe('DID_RENEW');

    $outbox = RevenueOutboxEvent::query()
        ->where('source_type', 'apple_iap')
        ->where('event_kind', 'iap_subscription_renewal')
        ->first();
    expect($outbox)->not()->toBeNull();
});

it('processes REFUND and writes a NEGATIVE outbox row', function () {
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'refund-tx-001',
        'current_period_ends_at'  => now()->addMonth(),
        'cancel_at_period_end'    => false,
    ]);

    $payload = appleNotification('REFUND', null, [
        'originalTransactionId' => 'refund-tx-001',
        'price'                 => 499,
    ]);

    $this->postJson('/api/webhooks/apple/notifications', $payload)->assertStatus(200);

    $sub->refresh();
    expect($sub->status)->toBe('refunded');
    expect($sub->refunded_at)->not()->toBeNull();

    $outbox = RevenueOutboxEvent::query()->where('event_kind', 'iap_refund')->first();
    expect($outbox)->not()->toBeNull();
    expect((int) ($outbox->payload['amount'] ?? 0))->toBe(-499);
});

it('processes DID_CHANGE_RENEWAL_STATUS AUTO_RENEW_DISABLED → cancel_at_period_end=true', function () {
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'cancel-tx-001',
        'current_period_ends_at'  => now()->addMonth(),
        'cancel_at_period_end'    => false,
    ]);

    $payload = appleNotification('DID_CHANGE_RENEWAL_STATUS', 'AUTO_RENEW_DISABLED', [
        'originalTransactionId' => 'cancel-tx-001',
    ]);

    $this->postJson('/api/webhooks/apple/notifications', $payload)->assertStatus(200);

    $sub->refresh();
    expect($sub->cancel_at_period_end)->toBeTrue();
});

it('returns 200 even on malformed JWS payload (never bubbles 5xx to Apple)', function () {
    $response = $this->postJson('/api/webhooks/apple/notifications', [
        // missing notificationUUID + notificationType
        'something' => 'else',
    ]);

    $response->assertStatus(200);
    // No dedup row written when fields are missing.
    expect(ProcessedWebhookEvent::query()->count())->toBe(0);
});
