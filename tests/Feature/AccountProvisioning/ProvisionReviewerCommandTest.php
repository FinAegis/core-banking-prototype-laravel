<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
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

it('creates a reviewer user with flags and sub-seeded content (happy path)', function () {
    $exit = $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--note'           => 'App Store review 2026-Q2',
    ])->run();

    expect($exit)->toBe(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();

    $flag = AccountFlag::where('user_id', $user->id)->first();
    expect($flag)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();
    expect($flag->bypass_device_attestation)->toBeTrue();
    expect($flag->note)->toBe('App Store review 2026-Q2');
    expect($flag->created_by)->toBe((int) $this->operator->id);

    // Sub-seeders ran (wallets create BlockchainAddress rows).
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
});

it('is idempotent across repeated invocations', function () {
    $args = [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ];

    $this->artisan('account:provision-reviewer', $args)->assertExitCode(0);
    $this->artisan('account:provision-reviewer', $args)->assertExitCode(0);

    $user = User::where('email', 'appreview@finaegis.com')->firstOrFail();
    expect(AccountFlag::where('user_id', $user->id)->count())->toBe(1);
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
});

it('exits 1 when operator is not an admin', function () {
    $nonAdmin = User::factory()->create(['email' => 'plain@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => $nonAdmin->email,
    ])->assertExitCode(1);
});

it('exits 1 in production without --allow-production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 on email collision with non-review user', function () {
    User::factory()->create(['email' => 'existing@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'existing@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('exits 1 when --force-convert is passed in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('account:provision-reviewer', [
        '--email'            => 'appreview@finaegis.com',
        '--operator-email'   => 'op@finaegis.com',
        '--allow-production' => true,
        '--force-convert'    => true,
    ])->assertExitCode(1);
});

it('exits 1 when --expires-days exceeds the 90-day hard cap', function () {
    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
        '--expires-days'   => 365,
    ])->assertExitCode(1);
});
