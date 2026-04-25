<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate([
        'name'       => UserRoles::ADMIN->value,
        'guard_name' => 'web',
    ]);
    $this->operator = User::factory()->create(['email' => 'op@finaegis.com']);
    $this->operator->assignRole(UserRoles::ADMIN->value);
});

it('disables a reviewer via --email, revokes bypasses, keeps is_review_account true', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $reviewer->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'bypass_rate_limit'         => true,
        'bypass_sms_otp'            => true,
        'created_by'                => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(0);

    $flag = AccountFlag::where('user_id', $reviewer->id)->first();
    expect($flag->is_review_account)->toBeTrue();
    expect($flag->bypass_device_attestation)->toBeFalse();
    expect($flag->bypass_rate_limit)->toBeFalse();
    expect($flag->bypass_sms_otp)->toBeFalse();
    expect($flag->disabled_at)->not->toBeNull();
});

it('--all-expired only touches expired rows', function () {
    $expired = User::factory()->create(['email' => 'expired@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $expired->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'expires_at'                => CarbonImmutable::now()->subDay(),
        'created_by'                => $this->operator->id,
    ]);

    $active = User::factory()->create(['email' => 'active@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $active->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'expires_at'                => CarbonImmutable::now()->addDays(30),
        'created_by'                => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', ['--all-expired' => true])
        ->assertExitCode(0);

    expect(AccountFlag::where('user_id', $expired->id)->first()->disabled_at)->not->toBeNull();
    expect(AccountFlag::where('user_id', $active->id)->first()->disabled_at)->toBeNull();
    expect(AccountFlag::where('user_id', $active->id)->first()->bypass_device_attestation)->toBeTrue();
});

it('--re-enable restores bypasses and clears disabled_at', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $reviewer->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => false,
        'bypass_rate_limit'         => false,
        'disabled_at'               => now(),
        'created_by'                => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--re-enable'      => true,
    ])->assertExitCode(0);

    $flag = AccountFlag::where('user_id', $reviewer->id)->first();
    expect($flag->disabled_at)->toBeNull();
    expect($flag->bypass_device_attestation)->toBeTrue();
    expect($flag->bypass_rate_limit)->toBeTrue();
});

it('--re-enable preserves the original expires_at', function () {
    $originalExpiry = CarbonImmutable::now()->addDays(45);

    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $reviewer->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'expires_at'                => $originalExpiry,
        'created_by'                => $this->operator->id,
    ]);

    // Disable
    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(0);

    $disabled = AccountFlag::where('user_id', $reviewer->id)->firstOrFail();
    $disabledExpiry = $disabled->expires_at;
    expect($disabledExpiry)->not->toBeNull();
    if ($disabledExpiry !== null) {
        expect($disabledExpiry->toIso8601String())->toBe($originalExpiry->toIso8601String());
    }

    // Re-enable
    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--re-enable'      => true,
    ])->assertExitCode(0);

    $reEnabled = AccountFlag::where('user_id', $reviewer->id)->firstOrFail();
    $reEnabledExpiry = $reEnabled->expires_at;
    expect($reEnabledExpiry)->not->toBeNull();
    if ($reEnabledExpiry !== null) {
        expect($reEnabledExpiry->toIso8601String())->toBe($originalExpiry->toIso8601String());
    }
});

it('exits 1 when --email is neither a review account nor --all-expired is passed', function () {
    User::factory()->create(['email' => 'normal@finaegis.com']);

    $this->artisan('account:disable-reviewer', [
        '--email'          => 'normal@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 in production without --allow-production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 when --email is provided without --operator-email', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', [
        '--email' => 'reviewer@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 when --re-enable is invoked without --operator-email', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'disabled_at'       => now(),
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', [
        '--email'     => 'reviewer@finaegis.com',
        '--re-enable' => true,
    ])->assertExitCode(1);
});

it('exits 1 when --operator-email resolves to a non-admin user', function () {
    $nonAdmin = User::factory()->create(['email' => 'plain@finaegis.com']);

    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:disable-reviewer', [
        '--email'          => 'reviewer@finaegis.com',
        '--operator-email' => $nonAdmin->email,
    ])->assertExitCode(1);
});
