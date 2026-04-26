<?php

declare(strict_types=1);

namespace Tests\MultiConnection\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('completes apply() without deadlock under real multi-session topology', function () {
    $profile = app(ReviewerAccountProfile::class);

    $ctx = new ProvisioningContext(
        email: 'multiconn-' . uniqid() . '@example.invalid',
        name: 'Multi-Connection Test',
        region: 'US',
        expiresAt: now()->addDays(60)->toImmutable(),
        note: 'multi-connection regression',
        operatorId: 1,
    );

    // If apply() ever re-introduces a wrapping DB::transaction, this call
    // deadlocks on the cardholders FK lookup and times out at innodb_lock_wait_timeout.
    $result = app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    expect($result['password_action'])->toBe('created');
    expect($result['user'])->toBeInstanceOf(User::class);
});

it('persists AccountFlag and routes Cardholder write to the tenant connection', function () {
    $profile = app(ReviewerAccountProfile::class);

    $ctx = new ProvisioningContext(
        email: 'multiconn-flag-' . uniqid() . '@example.invalid',
        name: 'Flag Routing Test',
        region: 'US',
        expiresAt: now()->addDays(60)->toImmutable(),
        note: 'flag and tenant routing',
        operatorId: 1,
    );

    $result = app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    $userId = $result['user']->id;

    // AccountFlag persisted on default connection.
    $flagOnDefault = DB::connection()->table('account_flags')->where('user_id', $userId)->exists();
    expect($flagOnDefault)->toBeTrue();

    $flag = AccountFlag::where('user_id', $userId)->first();
    expect($flag)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();

    // Cardholder persisted on tenant connection (proves multi-session write completed).
    $cardholderOnTenant = DB::connection('tenant')->table('cardholders')->where('user_id', $userId)->exists();
    expect($cardholderOnTenant)->toBeTrue();
});
