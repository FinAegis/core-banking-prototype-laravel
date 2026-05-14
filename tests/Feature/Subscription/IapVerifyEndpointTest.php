<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\IapSubscriptionEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    // Configure the IAP product ids so plan resolution works in tests.
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
        'subscription.iap.google.package_name'         => 'app.zelta',
        'subscription.iap.google.webhook_audience'     => '',
        'subscription.iap.google.service_account_path' => null,
        'subscription.iap.google.service_account_json' => null,
        'subscription.iap.receipt_pepper'              => 'test-pepper',
    ]);
});

/**
 * Build a (local/testing) Apple JWS payload string. Verifier doesn't check
 * signatures in local/testing — only the payload JSON is read.
 *
 * @param array<string, mixed> $payloadOverrides
 */
function makeAppleJws(array $payloadOverrides = []): string
{
    $payload = array_merge([
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'apple-orig-tx-001',
        'transactionId'         => 'apple-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'purchaseDate'          => 1_715_000_000_000,
        'expiresDate'           => 1_717_000_000_000,
        'inAppOwnershipType'    => 'PURCHASED',
        'environment'           => 'Sandbox',
        'type'                  => 'Auto-Renewable Subscription',
    ], $payloadOverrides);

    $header = base64UrlEncode((string) json_encode(['alg' => 'ES256', 'x5c' => []]));
    $payloadB64 = base64UrlEncode((string) json_encode($payload));
    $signature = base64UrlEncode('signature-placeholder');

    return "{$header}.{$payloadB64}.{$signature}";
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

it('verifies an Apple StoreKit 2 receipt and creates iap_subscriptions + iap_receipts + outbox', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'appVersion'            => '1.3.0',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-apple-verify-aaaaaaaaaaaaaaaa',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('tier', 'pro');
    $response->assertJsonPath('source', 'apple_iap');
    $response->assertJsonPath('reactivated', false);

    $sub = IapSubscription::query()->where('user_id', $user->id)->first();
    expect($sub)->not()->toBeNull();
    expect($sub->store)->toBe('apple');
    expect($sub->original_transaction_id)->toBe('apple-orig-tx-001');

    $receipt = IapReceipt::query()->where('iap_subscription_id', $sub->id)->first();
    expect($receipt)->not()->toBeNull();
    expect($receipt->amount_smallest_unit)->toBe(499);
    expect($receipt->amount_decimals)->toBe(2);
    expect($receipt->amount_currency)->toBe('EUR');
    expect($receipt->environment)->toBe('sandbox');

    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->source_type)->toBe('apple_iap');
    expect($outbox->event_kind)->toBe('iap_subscription_initial');
});

it('verifies a Google Play receipt using the local-bypass verifier path', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'   => 'google_play',
        'receipt'    => 'google-purchase-token-001',
        'productId'  => 'zelta_pro_annual',
        'appVersion' => '1.3.0',
        'currency'   => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-google-verify-bbbbbbbbbbbbbbbb',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('source', 'google_play');

    $sub = IapSubscription::query()->where('user_id', $user->id)->first();
    expect($sub->store)->toBe('google');
    expect($sub->play_subscription_resource_id)->toStartWith('synthetic-');

    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox->source_type)->toBe('google_play');
});

it('returns ERR_SUB_001 for an unknown productId', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-002',
        'productId'             => 'zelta_pro_quarterly', // not in config map
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bad-product-cccccccccccccccc',
    ]);

    // Validation rejects unknown product IDs before reaching the service
    // (the request rules enforce `in:zelta_pro_monthly,zelta_pro_annual`).
    $response->assertStatus(422);
});

it('returns ERR_CUR_001 for a non-EUR currency', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(['currency' => 'USD']),
        'originalTransactionId' => 'apple-orig-tx-003',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'USD',
    ], [
        'Idempotency-Key' => 'idem-bad-currency-dddddddddddddddd',
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'ERR_CUR_001');
});

it('returns ERR_SUB_002 kind=family_sharing_unsupported for an Apple Family Sharing receipt', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $jws = makeAppleJws([
        'originalTransactionId' => 'apple-family-tx-001',
        'inAppOwnershipType'    => 'FAMILY_SHARED',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-family-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-family-share-eeeeeeeeeeeeeeee',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_SUB_002');
    $response->assertJsonPath('error.conflict.kind', 'family_sharing_unsupported');
    $response->assertJsonPath('error.conflict.attemptedSource', 'apple_iap');
    $response->assertJsonPath('error.conflict.existingSubscription.source', 'apple_iap');
    expect($response->json('error.conflict.existingSubscription.currentPeriodEndsAt'))->not->toBeNull();
});

it('returns ERR_SUB_001 when the request bundleId does not match Apple JWS', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['subscription.iap.apple.bundle_id' => 'app.zelta']);

    $jws = makeAppleJws([
        'bundleId'              => 'com.evil.app', // mismatched
        'originalTransactionId' => 'apple-bundle-mismatch-001',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-bundle-mismatch-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bundle-mismatch-ffffffffffff',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
});

it('rejects an Apple JWS whose originalTransactionId does not match request body (ERR_SUB_001)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $jws = makeAppleJws([
        'originalTransactionId' => 'apple-actual-tx',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-spoofed-tx', // mismatch
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-tx-mismatch-gggggggggggggggg',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
});

it('requires Idempotency-Key header (ERR_VALIDATION_001)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-noidem',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_001');
});

it('appends an event to iap_subscription_events on first verify', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(['originalTransactionId' => 'apple-event-test-001']),
        'originalTransactionId' => 'apple-event-test-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-event-store-hhhhhhhhhhhhhhhh',
    ])->assertStatus(200);

    $sub = IapSubscription::query()->first();
    $events = IapSubscriptionEvent::query()->where('aggregate_uuid', $sub->id)->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->event_class)->toBe('AppleSubscriptionVerified');
    expect($events->first()->aggregate_version)->toBe(1);
});

/**
 * Regression: in production (no local/testing bypass), the Apple verifier must
 * throw IapVerificationException when JWS chain validation is not implemented
 * and the explicit operator bypass flag is unset. The fail-closed behaviour
 * was added in the slice 2 code-review pass after the verifier was found to
 * silently accept unverified JWS payloads in non-local environments.
 */
it('Apple verifier fails closed in production when JWS bypass is unset', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Pretend we are in production but leave APPLE_JWS_VERIFICATION_BYPASS unset
    // (config default is false → verifier must throw on the JWS path).
    $this->app['env'] = 'production';
    config(['subscription.iap.apple_jws_verification_bypass' => false]);

    $jws = makeAppleJws();

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'store'   => 'apple',
        'receipt' => $jws,
    ], [
        'Idempotency-Key' => 'idem-fail-closed-aaaaaaaaaaaaaaaa',
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('ERR_SUB_001');
    expect(IapSubscription::query()->count())->toBe(0);
    expect(IapReceipt::query()->count())->toBe(0);
});

it('Apple verifier accepts JWS in production when bypass flag is explicitly set', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Operator explicitly accepted the risk (staging environment without certs).
    $this->app['env'] = 'production';
    config(['subscription.iap.apple_jws_verification_bypass' => true]);

    $jws = makeAppleJws();

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'store'   => 'apple',
        'receipt' => $jws,
    ], [
        'Idempotency-Key' => 'idem-bypass-set-bbbbbbbbbbbbbbbbb',
    ]);

    $response->assertStatus(200);
    expect(IapSubscription::query()->count())->toBe(1);
});
