<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

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

it('lists seeded review accounts in table form', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'note'              => 'audit-note-xyz',
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:list-reviewers')
        ->assertExitCode(0)
        ->expectsOutputToContain('reviewer@finaegis.com');
});

it('emits JSON when --json flag is set', function () {
    $reviewer = User::factory()->create(['email' => 'reviewer@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $reviewer->id,
        'is_review_account' => true,
        'note'              => 'App Store 2026-Q2',
        'created_by'        => $this->operator->id,
    ]);

    $this->artisan('account:list-reviewers', ['--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('reviewer@finaegis.com');
});

it('handles empty result without failing', function () {
    $this->artisan('account:list-reviewers')
        ->assertExitCode(0)
        ->expectsOutputToContain('No review accounts found.');
});
