<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config([
        'subscription.iap.apple.bundle_id'   => 'app.zelta',
        'subscription.iap.apple.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.google.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.google.webhook_audience'     => '',
        'subscription.iap.google.service_account_path' => null,
        'subscription.iap.google.service_account_json' => null,
        'subscription.iap.receipt_pepper'              => 'test-pepper',
    ]);
});

function appleJwsForConflict(string $originalTransactionId, ?int $expiresEpochMs = null): string
{
    $payload = [
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => $originalTransactionId,
        'transactionId'         => $originalTransactionId,
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'purchaseDate'          => now()->subDay()->getTimestampMs(),
        // Default to ~30 days in the future so reactivation paths work.
        'expiresDate'        => $expiresEpochMs ?? now()->addMonth()->getTimestampMs(),
        'inAppOwnershipType' => 'PURCHASED',
        'environment'        => 'Sandbox',
    ];

    $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'ES256'])), '+/', '-_'), '=');
    $payloadB64 = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
    $sig = rtrim(strtr(base64_encode('sig'), '+/', '-_'), '=');

    return "{$header}.{$payloadB64}.{$sig}";
}

it('returns ERR_SUB_002 two_stores_active when user has an active Google IAP and verifies Apple', function () {
    $user = User::factory()->create();

    IapSubscription::query()->create([
        'id'                            => (string) Str::uuid(),
        'user_id'                       => $user->id,
        'store'                         => 'google',
        'tier'                          => 'pro',
        'status'                        => 'active',
        'play_subscription_resource_id' => 'existing-google-resource-001',
        'current_period_ends_at'        => now()->addMonth(),
    ]);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => appleJwsForConflict('cross-store-tx-001'),
        'originalTransactionId' => 'cross-store-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-cross-store-iiiiiiiiiiiiiiii',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_SUB_002');
    $response->assertJsonPath('error.conflict.kind', 'two_stores_active');
    $response->assertJsonPath('error.conflict.attemptedSource', 'apple_iap');
});

it('returns ERR_SUB_002 different_zelta_user when Apple originalTransactionId is bound to another user', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $owner->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'shared-tx-001',
        'current_period_ends_at'  => now()->addMonth(),
    ]);

    Sanctum::actingAs($attacker, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => appleJwsForConflict('shared-tx-001'),
        'originalTransactionId' => 'shared-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-different-user-jjjjjjjjjjjjj',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_SUB_002');
    // Because there's no two-store conflict at the projection level (the
    // owner's row is apple, attempted is apple), the conflict surfaces inside
    // persistApple as different_zelta_user.
    $response->assertJsonPath('error.conflict.kind', 'different_zelta_user');
});

it('returns 200 with reactivated:true when an Apple cancel-at-period-end sub is re-verified within grace', function () {
    $user = User::factory()->create();
    IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'reactivate-tx-001',
        'current_period_ends_at'  => now()->addMonth(),
        'cancel_at_period_end'    => true,
    ]);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => appleJwsForConflict('reactivate-tx-001'),
        'originalTransactionId' => 'reactivate-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-reactivate-kkkkkkkkkkkkkkkk',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('reactivated', true);

    $sub = IapSubscription::query()->where('original_transaction_id', 'reactivate-tx-001')->first();
    expect($sub->cancel_at_period_end)->toBeFalse();
});

it('returns 200 idempotent (reactivated:false) when an already-active sub is re-verified', function () {
    $user = User::factory()->create();
    IapSubscription::query()->create([
        'id'                      => (string) Str::uuid(),
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'tier'                    => 'pro',
        'status'                  => 'active',
        'original_transaction_id' => 'already-active-tx-001',
        'current_period_ends_at'  => now()->addMonth(),
        'cancel_at_period_end'    => false,
    ]);

    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => appleJwsForConflict('already-active-tx-001'),
        'originalTransactionId' => 'already-active-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-already-active-llllllllllll',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('reactivated', false);
});

it('post-erasure Apple webhook hash-match increments scrubbed_renewal_count', function () {
    // Build a scrubbed receipt referenced by hash only.
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'active',
        // raw original_transaction_id intentionally NULL (already scrubbed).
    ]);

    $rawTx = 'scrubbed-original-tx-001';
    $hash = hash_hmac('sha256', $rawTx, 'test-pepper');

    $receipt = App\Domain\Subscription\Models\IapReceipt::query()->create([
        'iap_subscription_id'          => $sub->id,
        'user_id'                      => null,
        'store'                        => 'apple',
        'original_transaction_id'      => null,
        'original_transaction_id_hash' => $hash,
        'product_id'                   => 'zelta_pro_monthly',
        'tier'                         => 'pro',
        'amount_smallest_unit'         => 499,
        'amount_decimals'              => 2,
        'amount_currency'              => 'EUR',
        'scrubbed_at'                  => now(),
        'scrubbed_renewal_count'       => 0,
    ]);

    $payload = [
        'notificationType' => 'DID_RENEW',
        'notificationUUID' => (string) Str::uuid(),
        'data'             => [
            'transactionInfo' => [
                'originalTransactionId' => $rawTx,
                'productId'             => 'zelta_pro_monthly',
                'currency'              => 'EUR',
                'price'                 => 499,
                'expiresDate'           => 1_720_000_000_000,
            ],
        ],
    ];

    $response = $this->postJson('/api/webhooks/apple/notifications', $payload);
    $response->assertStatus(200);

    $receipt->refresh();
    expect($receipt->scrubbed_renewal_count)->toBe(1);
    expect(ProcessedWebhookEvent::query()->where('provider', 'apple')->count())->toBe(1);
});
