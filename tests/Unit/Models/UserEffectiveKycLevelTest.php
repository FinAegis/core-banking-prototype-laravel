<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns real kyc_level when no flag override exists', function () {
    $user = User::factory()->create(['kyc_level' => 'basic']);
    expect($user->effectiveKycLevel())->toBe(1);
});

it('returns override level when flag is active and override is set', function () {
    $user = User::factory()->create(['kyc_level' => 'none']);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => 2,
    ]);

    expect($user->effectiveKycLevel())->toBe(2);
});

it('falls back to real level when flag is disabled', function () {
    $user = User::factory()->create(['kyc_level' => 'basic']);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => 2,
        'disabled_at'        => now(),
    ]);

    expect($user->effectiveKycLevel())->toBe(1);
});

it('falls back to real level when override is null', function () {
    $user = User::factory()->create(['kyc_level' => 'basic']);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => null,
    ]);

    expect($user->effectiveKycLevel())->toBe(1);
});
