<?php

declare(strict_types=1);

use App\Domain\Subscription\Services\SubscriptionService;
use App\Models\User;

/**
 * Helper: refresh a user model and assert non-null for PHPStan narrowing.
 */
function freshUser(User $user): User
{
    $fresh = User::query()->find($user->id);
    if (! $fresh instanceof User) {
        throw new RuntimeException('User vanished between create and refresh.');
    }

    return $fresh;
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    config(['services.stripe.subscription_prices.monthly_pro' => 'price_test_monthly']);
    config(['services.stripe.subscription_prices.annual_pro' => 'price_test_annual']);
});

it('rejects annual to monthly downgrade with ERR_SUB_006 (deltas Q17.4)', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_annual_user',
    ]);

    // Cashier subscriptions table is already migrated (existing) — insert a row
    // mirroring the swap-down scenario.
    DB::table('subscriptions')->insert([
        'user_id'       => $user->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_annual_001',
        'stripe_status' => 'active',
        'stripe_price'  => 'price_test_annual',
        'quantity'      => 1,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $service = app(SubscriptionService::class);
    $result = $service->changePlan(freshUser($user), 'monthly_pro');

    expect($result['code'] ?? null)->toBe('ERR_SUB_006');
});

it('returns the existing subscription on same-plan no-op swap', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_same_plan',
    ]);

    DB::table('subscriptions')->insert([
        'user_id'       => $user->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_same_001',
        'stripe_status' => 'active',
        'stripe_price'  => 'price_test_monthly',
        'quantity'      => 1,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $service = app(SubscriptionService::class);
    $result = $service->changePlan(freshUser($user), 'monthly_pro');

    $success = $result['success'] ?? null;
    expect($success)->toBeArray();
    expect(is_array($success) && is_array($success['subscription'] ?? null) ? $success['subscription']['tier'] : null)
        ->toBe('pro');
});

it('reactivate is idempotent on a non-cancelled subscription', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_reactivate',
    ]);

    DB::table('subscriptions')->insert([
        'user_id'       => $user->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_react_001',
        'stripe_status' => 'active',
        'stripe_price'  => 'price_test_monthly',
        'quantity'      => 1,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $service = app(SubscriptionService::class);
    $result = $service->reactivate(freshUser($user));

    expect($result['success'] ?? null)->toBeArray();
});

it('returns ERR_SUB_002 for cancel on a user without a subscription', function () {
    $user = User::factory()->create();

    $service = app(SubscriptionService::class);
    $result = $service->cancel($user);

    expect($result['code'] ?? null)->toBe('ERR_SUB_002');
});

it('GET /me returns pro tier and currentPeriodEnd for an active monthly subscriber', function () {
    $user = User::factory()->create([
        'stripe_id' => 'cus_me_active',
    ]);

    DB::table('subscriptions')->insert([
        'user_id'       => $user->id,
        'type'          => 'default',
        'stripe_id'     => 'sub_me_active',
        'stripe_status' => 'active',
        'stripe_price'  => 'price_test_monthly',
        'quantity'      => 1,
        'trial_ends_at' => now()->addDays(7),
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $service = app(SubscriptionService::class);
    $shape = $service->read(freshUser($user));

    expect($shape['tier'])->toBe('pro');
    expect($shape['source'])->toBe('stripe_web');
    expect($shape['plan'])->toBe('monthly_pro');
    expect($shape['cancelledAtPeriodEnd'])->toBeFalse();
});
