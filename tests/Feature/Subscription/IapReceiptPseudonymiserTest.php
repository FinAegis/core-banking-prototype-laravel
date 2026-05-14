<?php

declare(strict_types=1);

use App\Domain\Subscription\Iap\IapReceiptPseudonymiser;
use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['subscription.iap.receipt_pepper' => 'test-pepper-pseudonymiser']);
});

it('nulls personal columns and populates original_transaction_id_hash', function () {
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'active',
    ]);

    $rawTx = 'orig-tx-to-be-scrubbed-001';

    $receipt = IapReceipt::query()->create([
        'iap_subscription_id'     => $sub->id,
        'user_id'                 => $user->id,
        'store'                   => 'apple',
        'original_transaction_id' => $rawTx,
        'apple_app_account_token' => (string) Str::uuid(),
        'receipt_blob'            => 'raw-jws-content',
        'product_id'              => 'zelta_pro_monthly',
        'tier'                    => 'pro',
        'amount_smallest_unit'    => 499,
        'amount_decimals'         => 2,
        'amount_currency'         => 'EUR',
    ]);

    $expectedHash = hash_hmac('sha256', $rawTx, 'test-pepper-pseudonymiser');

    $pseudonymiser = app(IapReceiptPseudonymiser::class);
    $scrubbed = $pseudonymiser->pseudonymise($user, 'request-001');

    expect($scrubbed)->toBe(1);

    $receipt->refresh();
    expect($receipt->user_id)->toBeNull();
    expect($receipt->original_transaction_id)->toBeNull();
    expect($receipt->apple_app_account_token)->toBeNull();
    expect($receipt->receipt_blob)->toBeNull();
    expect($receipt->original_transaction_id_hash)->toBe($expectedHash);
    expect($receipt->scrubbed_at)->not()->toBeNull();

    // Money + tier preserved for tax retention.
    expect($receipt->amount_smallest_unit)->toBe(499);
    expect($receipt->tier)->toBe('pro');
});

it('does not re-scrub already-scrubbed rows', function () {
    $user = User::factory()->create();
    $sub = IapSubscription::query()->create([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'active',
    ]);

    IapReceipt::query()->create([
        'iap_subscription_id'  => $sub->id,
        'user_id'              => null, // already scrubbed
        'store'                => 'apple',
        'product_id'           => 'zelta_pro_monthly',
        'tier'                 => 'pro',
        'amount_smallest_unit' => 499,
        'amount_decimals'      => 2,
        'amount_currency'      => 'EUR',
        'scrubbed_at'          => now(),
    ]);

    $pseudonymiser = app(IapReceiptPseudonymiser::class);
    $scrubbed = $pseudonymiser->pseudonymise($user, 'request-002');

    expect($scrubbed)->toBe(0);
});

it('fingerprint() reproduces the same hash used at scrub time', function () {
    $pseudonymiser = app(IapReceiptPseudonymiser::class);

    $hash1 = $pseudonymiser->fingerprint('abc-123');
    $hash2 = $pseudonymiser->fingerprint('abc-123');
    $hash3 = $pseudonymiser->fingerprint('different-id');

    expect($hash1)->toBe($hash2);
    expect($hash1)->not()->toBe($hash3);
    expect($hash1)->toBe(hash_hmac('sha256', 'abc-123', 'test-pepper-pseudonymiser'));
});
