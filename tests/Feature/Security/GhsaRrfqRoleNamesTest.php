<?php

declare(strict_types=1);

use App\Domain\Governance\Models\GcuVotingProposal;
use App\Domain\Governance\Policies\GcuVotingProposalPolicy;
use App\Models\User;
use App\Traits\HasApiScopes;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

// The staff role is named `super_admin` (RolesSeeder, StaffRoles::ADMINS).
// Several checks asked for `super-admin`, a role that does not exist, so a
// user holding only `super_admin` was treated as a regular user by them.

function rrfqRoleUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }

    return $user;
}

/**
 * @return list<string>
 */
function rrfqDefaultScopes(User $user): array
{
    $issuer = new class () {
        use HasApiScopes;

        /**
         * @return list<string>
         */
        public function scopesFor(User $user): array
        {
            return array_values($this->getDefaultScopesForUser($user));
        }
    };

    return $issuer->scopesFor($user);
}

describe('super_admin is recognised as an administrator', function (): void {
    it('requires two-factor authentication on the admin API for a super_admin without 2FA', function (): void {
        Sanctum::actingAs(rrfqRoleUser('super_admin'), ['read', 'write', 'delete']);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');
    });

    it('still requires two-factor authentication for admin and lets a regular user through the middleware', function (): void {
        Sanctum::actingAs(rrfqRoleUser('admin'), ['read', 'write', 'delete']);
        $this->getJson('/api/admin/dashboard')->assertForbidden()->assertJsonPath('error', 'TWO_FACTOR_REQUIRED');

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(rrfqRoleUser(), ['read', 'write', 'delete']);
        $this->getJson('/api/admin/dashboard')->assertOk();
    });

    it('gives a super_admin the administrator token scopes', function (): void {
        expect(rrfqDefaultScopes(rrfqRoleUser('super_admin')))->toContain('admin', 'delete')
            ->and(rrfqDefaultScopes(rrfqRoleUser('admin')))->toContain('admin', 'delete')
            ->and(rrfqDefaultScopes(rrfqRoleUser()))->not->toContain('admin');
    });

    it('lets a super_admin manage GCU voting proposals, and not a regular user', function (): void {
        $policy = new GcuVotingProposalPolicy();
        $proposal = new GcuVotingProposal();

        $superAdmin = rrfqRoleUser('super_admin');
        expect($policy->create($superAdmin))->toBeTrue()
            ->and($policy->update($superAdmin, $proposal))->toBeTrue()
            ->and($policy->delete($superAdmin, $proposal))->toBeTrue();

        $regular = rrfqRoleUser();
        expect($policy->create($regular))->toBeFalse()
            ->and($policy->update($regular, $proposal))->toBeFalse()
            ->and($policy->delete($regular, $proposal))->toBeFalse();
    });
});
