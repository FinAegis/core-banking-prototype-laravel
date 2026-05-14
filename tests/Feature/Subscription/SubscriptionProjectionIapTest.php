<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Projections\SubscriptionProjection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config([
        'subscription.iap.apple.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.google.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
    ]);
});

it('hasActiveProSubscription returns true for iap when iap_subscriptions row is alive', function () {
    $user = User::factory()->create();
    $projection = app(SubscriptionProjection::class);

    expect($projection->hasActiveProSubscription($user, 'iap'))->toBeFalse();

    IapSubscription::query()->create([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'active',
    ]);

    expect($projection->hasActiveProSubscription($user, 'iap'))->toBeTrue();
    expect($projection->hasActiveProSubscription($user, 'apple'))->toBeTrue();
    expect($projection->hasActiveProSubscription($user, 'google'))->toBeFalse();
});

it('hasActiveProSubscription returns false for iap when only an expired row exists', function () {
    $user = User::factory()->create();
    $projection = app(SubscriptionProjection::class);

    IapSubscription::query()->create([
        'id'      => (string) Str::uuid(),
        'user_id' => $user->id,
        'store'   => 'apple',
        'tier'    => 'pro',
        'status'  => 'expired',
    ]);

    expect($projection->hasActiveProSubscription($user, 'iap'))->toBeFalse();
});

it('for() returns source=apple_iap shape for an Apple-subscribed user', function () {
    $user = User::factory()->create();
    $projection = app(SubscriptionProjection::class);

    $sub = IapSubscription::query()->create([
        'id'                     => (string) Str::uuid(),
        'user_id'                => $user->id,
        'store'                  => 'apple',
        'tier'                   => 'pro',
        'status'                 => 'active',
        'current_period_ends_at' => now()->addMonth(),
        'cancel_at_period_end'   => false,
    ]);

    IapReceipt::query()->create([
        'iap_subscription_id'  => $sub->id,
        'user_id'              => $user->id,
        'store'                => 'apple',
        'product_id'           => 'zelta_pro_monthly',
        'tier'                 => 'pro',
        'amount_smallest_unit' => 499,
        'amount_decimals'      => 2,
        'amount_currency'      => 'EUR',
    ]);

    $shape = $projection->for($user);

    expect($shape['tier'])->toBe('pro');
    expect($shape['source'])->toBe('apple_iap');
    expect($shape['plan'])->toBe('monthly_pro');
    expect($shape['currentPeriodEnd'])->not()->toBeNull();
});

it('for() returns free shape when no subscription exists in either source', function () {
    $user = User::factory()->create();
    $projection = app(SubscriptionProjection::class);

    $shape = $projection->for($user);

    expect($shape['tier'])->toBe('free');
    expect($shape['source'])->toBeNull();
});
