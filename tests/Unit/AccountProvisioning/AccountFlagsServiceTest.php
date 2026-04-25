<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Models\User;
use DB;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns null when user has no flag row', function () {
    $user = User::factory()->create();
    $service = new AccountFlagsService();

    expect($service->forUser($user))->toBeNull();
    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeFalse();
});

it('returns the flag row and reports bypasses', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'bypass_rate_limit'         => false,
    ]);

    $service = new AccountFlagsService();

    expect($service->forUser($user))->not->toBeNull();
    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeTrue();
    expect($service->hasReviewBypass($user, 'rate_limit'))->toBeFalse();
});

it('short-circuits when flag is disabled or expired', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'disabled_at'               => now(),
    ]);

    $service = new AccountFlagsService();

    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeFalse();
});

it('refuses bypasses when is_review_account=false even with bypass columns set', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => false,
        'bypass_device_attestation' => true,
        'bypass_rate_limit'         => true,
    ]);

    $service = new AccountFlagsService();

    expect($service->hasReviewBypass($user, 'rate_limit'))->toBeFalse();
    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeFalse();
});

it('caches lookups per request', function () {
    $user = User::factory()->create();
    AccountFlag::create(['user_id' => $user->id, 'is_review_account' => true]);

    $service = new AccountFlagsService();
    $service->forUser($user);

    DB::enableQueryLog();
    $service->forUser($user);
    $service->forUser($user);
    expect(DB::getQueryLog())->toBeEmpty();
});
