<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Events\AccountPurged;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Illuminate\Support\Facades\Event;
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

it('purges (anonymizes + disables) a review account with --confirm', function () {
    Event::fake([AccountPurged::class]);

    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'                   => $reviewer->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'created_by'                => $this->operator->id,
    ]);

    $this->artisan('account:purge-reviewer', [
        '--email'   => 'reviewer@finaegis.com',
        '--confirm' => true,
    ])->assertExitCode(0);

    // Email anonymized
    $user = User::find($reviewer->id);
    expect($user)->not->toBeNull();
    expect($user->email)->not->toBe('reviewer@finaegis.com');
    expect($user->email)->toStartWith('purged-')->toEndWith('@example.invalid');

    // Flag row preserved, disabled, bypasses revoked
    $flag = AccountFlag::where('user_id', $reviewer->id)->first();
    expect($flag)->not->toBeNull();
    expect($flag->disabled_at)->not->toBeNull();
    expect($flag->bypass_device_attestation)->toBeFalse();

    Event::assertDispatched(AccountPurged::class, fn ($e) => $e->userId === (int) $reviewer->id);
});

it('refuses without --confirm', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:purge-reviewer', ['--email' => 'reviewer@finaegis.com'])
        ->assertExitCode(1);
});

it('refuses when user is not a review account', function () {
    User::factory()->create(['email' => 'normal@finaegis.com']);

    $this->artisan('account:purge-reviewer', [
        '--email'   => 'normal@finaegis.com',
        '--confirm' => true,
    ])->assertExitCode(1);
});

it('refuses in production without --allow-production', function () {
    app()->detectEnvironment(fn () => 'production');

    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:purge-reviewer', [
        '--email'   => 'reviewer@finaegis.com',
        '--confirm' => true,
    ])->assertExitCode(1);
});
