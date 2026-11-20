<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Compliance\Models\KycDocument;
use App\Models\Team;
use App\Models\TeamInvitation as TeamInvitationModel;
use App\Models\TeamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Sanctum\Sanctum;

/*
| Business team roles are labels inside the organisation (team_user_roles)
| and never become global (spatie) staff roles; team management only acts on
| members of the team; the Filament admin check holds on Livewire update
| requests; team invitation mail is capped.
*/

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
});

/**
 * Every staff role name (StaffRoles groups).
 *
 * @return list<string>
 */
function g385StaffRoleNames(): array
{
    return ['admin', 'super_admin', 'bank_admin', 'stablecoin_operator', 'compliance_officer', 'risk_manager'];
}

/**
 * A business organisation owned by a customer. By default its allowed_roles
 * are what sign-up stored before the fix, plus every staff role name (a
 * legacy or tampered row).
 *
 * @param  list<string>|null  $allowedRoles
 * @return array{0: User, 1: Team}
 */
function g385BusinessTeam(?array $allowedRoles = null): array
{
    $owner = User::factory()->withPersonalTeam()->create();
    $owner->assignRole('customer_business');

    /** @var Team $team */
    $team = $owner->ownedTeams()->firstOrFail();
    $team->forceFill([
        'is_business_organization' => true,
        'organization_type'        => 'business',
        'max_users'                => 10,
        'allowed_roles'            => $allowedRoles ?? [...g385StaffRoleNames(), 'accountant', 'operations_manager', 'customer_service'],
    ])->save();

    return [$owner->fresh() ?? $owner, $team->fresh() ?? $team];
}

function g385Member(Team $team, string $teamRole = 'accountant'): User
{
    $member = User::factory()->create();
    $member->assignRole('customer_business');
    $team->users()->attach($member);
    $team->assignUserRole($member, $teamRole);

    return $member->fresh() ?? $member;
}

function g385Staff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh() ?? $user;
}

/**
 * @return array<int, mixed>
 */
function g385Roles(User $user): array
{
    return ($user->fresh() ?? $user)->getRoleNames()->sort()->values()->all();
}

describe('business team roles never become global staff roles', function (): void {
    it('rejects a staff role name as a team role on member creation, even when the team row still lists it', function (string $role): void {
        [$owner, $team] = g385BusinessTeam();

        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), [
                'name'     => 'Mallory',
                'email'    => 'mallory@example.com',
                'password' => 'Str0ng-Passw0rd-2026',
                'role'     => $role,
            ])
            ->assertSessionHasErrors('role');

        expect(User::query()->where('email', 'mallory@example.com')->exists())->toBeFalse()
            ->and($team->users()->count())->toBe(0)
            ->and(TeamUserRole::query()->where('team_id', $team->id)->count())->toBe(0);
    })->with(g385StaffRoleNames());

    it('creates a member with the team role in team_user_roles only and no global staff role', function (): void {
        [$owner, $team] = g385BusinessTeam();

        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), [
                'name'     => 'Alice',
                'email'    => 'alice@example.com',
                'password' => 'Str0ng-Passw0rd-2026',
                'role'     => 'accountant',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teams.members.index', $team));

        $member = User::query()->where('email', 'alice@example.com')->firstOrFail();

        expect($team->users()->whereKey($member->id)->exists())->toBeTrue()
            ->and($team->getUserTeamRole($member)?->role)->toBe('accountant')
            ->and(g385Roles($member))->toBe(['customer_business'])
            ->and($member->current_team_id)->toBe($team->id);
    });

    it('rejects a staff role name on a role update and leaves the member\'s global roles untouched', function (string $role): void {
        [$owner, $team] = g385BusinessTeam();
        $member = g385Member($team, 'accountant');

        $this->actingAs($owner)
            ->put(route('teams.members.update', [$team, $member]), ['role' => $role])
            ->assertSessionHasErrors('role');

        expect(g385Roles($member))->toBe(['customer_business'])
            ->and($team->getUserTeamRole($member)?->role)->toBe('accountant');
    })->with(g385StaffRoleNames());

    it('updates only the team role of a member and never detaches global roles', function (): void {
        [$owner, $team] = g385BusinessTeam();
        $member = g385Member($team, 'accountant');
        $member->assignRole('developer');

        $this->actingAs($owner)
            ->put(route('teams.members.update', [$team, $member]), ['role' => 'customer_service'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teams.members.index', $team));

        expect($team->getUserTeamRole($member)?->role)->toBe('customer_service')
            ->and(g385Roles($member))->toBe(['customer_business', 'developer']);
    });

    it('no longer offers staff role names to new or existing business teams', function (): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        $user = app(CreateNewUser::class)->create([
            'name'                  => 'Biz Owner',
            'email'                 => 'biz-owner@example.com',
            'password'              => 'Str0ng-Passw0rd-2026!',
            'password_confirmation' => 'Str0ng-Passw0rd-2026!',
            'is_business_customer'  => '1',
            'terms'                 => '1',
        ], isApiRegistration: true);

        /** @var Team $team */
        $team = $user->ownedTeams()->firstOrFail();

        expect($team->is_business_organization)->toBeTrue()
            ->and($team->allowed_roles)->toBe(['accountant', 'operations_manager', 'customer_service'])
            ->and(array_intersect($team->getAvailableRoles(), g385StaffRoleNames()))->toBe([]);

        // A team row stored before the fix still lists staff roles: they are filtered out.
        [, $legacy] = g385BusinessTeam();
        expect($legacy->getAvailableRoles())->toBe(['accountant', 'operations_manager', 'customer_service']);

        // Default when allowed_roles is null.
        [, $defaults] = g385BusinessTeam();
        $defaults->forceFill(['allowed_roles' => null])->save();
        expect(($defaults->fresh() ?? $defaults)->getAvailableRoles())->toBe(['accountant', 'operations_manager', 'customer_service']);
    });

    it('no longer turns team management into compliance staff access, while compliance staff keep it', function (): void {
        [$owner, $team] = g385BusinessTeam();

        // The owner's second account, already a member of the team.
        $alt = g385Member($team, 'accountant');
        $this->actingAs($owner)
            ->put(route('teams.members.update', [$team, $alt]), ['role' => 'risk_manager'])
            ->assertSessionHasErrors('role');

        // A freshly minted member with an allowed team role.
        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), [
                'name'     => 'Minted',
                'email'    => 'minted@example.com',
                'password' => 'Str0ng-Passw0rd-2026',
                'role'     => 'operations_manager',
            ])
            ->assertSessionHasNoErrors();
        $minted = User::query()->where('email', 'minted@example.com')->firstOrFail();

        // A member whose stored team role is a staff role name (row from before the fix).
        $legacy = g385Member($team, 'compliance_officer');

        foreach ([$alt, $minted, $legacy] as $account) {
            expect(g385Roles($account))->toBe(['customer_business']);

            Sanctum::actingAs($account->fresh() ?? $account, ['read', 'write', 'delete']);
            $this->getJson('/api/compliance/alerts')->assertForbidden();
            $this->getJson('/api/transaction-monitoring')->assertForbidden();
        }

        Sanctum::actingAs(g385Staff('compliance_officer'), ['read', 'write', 'delete']);
        $this->getJson('/api/compliance/alerts')->assertOk();
        $this->getJson('/api/transaction-monitoring')->assertOk();
    });
});

describe('team management only acts on members of the team', function (): void {
    it('answers 404 when a team owner updates the role of a user outside the team, leaving that user untouched', function (): void {
        [$owner, $team] = g385BusinessTeam();
        $admin = g385Staff('admin');
        $admin->assignRole('super_admin');

        $this->actingAs($owner)
            ->put(route('teams.members.update', [$team, $admin]), ['role' => 'accountant'])
            ->assertNotFound();

        expect(g385Roles($admin))->toBe(['admin', 'super_admin'])
            ->and(TeamUserRole::query()->where('user_id', $admin->id)->exists())->toBeFalse()
            ->and($team->users()->whereKey($admin->id)->exists())->toBeFalse();
    });

    it('answers 404 to edit and remove requests for a user outside the team', function (): void {
        [$owner, $team] = g385BusinessTeam();
        [, $otherTeam] = g385BusinessTeam();
        $outsider = g385Member($otherTeam, 'accountant');
        $outsider->forceFill(['current_team_id' => $otherTeam->id])->save();

        $this->actingAs($owner)->get(route('teams.members.edit', [$team, $outsider]))->assertNotFound();
        $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $outsider]))->assertNotFound();

        $outsider = $outsider->fresh();
        expect($outsider?->current_team_id)->toBe($otherTeam->id)
            ->and($otherTeam->users()->whereKey($outsider?->id)->exists())->toBeTrue()
            ->and($otherTeam->getUserTeamRole($outsider)?->role)->toBe('accountant');
    });

    it('still lets the owner remove a member of the team and keeps the owner protected', function (): void {
        [$owner, $team] = g385BusinessTeam();
        $member = g385Member($team, 'customer_service');
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]))
            ->assertRedirect(route('teams.members.index', $team))
            ->assertSessionHas('success');

        expect($team->users()->whereKey($member->id)->exists())->toBeFalse()
            ->and(TeamUserRole::query()->where('team_id', $team->id)->where('user_id', $member->id)->exists())->toBeFalse()
            ->and($member->fresh()?->current_team_id)->toBeNull()
            ->and(g385Roles($member))->toBe(['customer_business']);

        // The owner target keeps its existing answer (redirect with an error).
        $this->actingAs($owner)
            ->put(route('teams.members.update', [$team, $owner]), ['role' => 'accountant'])
            ->assertRedirect(route('teams.members.index', $team))
            ->assertSessionHas('error');
    });

    it('still refuses a non-owner', function (): void {
        [, $team] = g385BusinessTeam();
        $member = g385Member($team, 'accountant');
        $other = g385Member($team, 'customer_service');

        $this->actingAs($member)
            ->put(route('teams.members.update', [$team, $other]), ['role' => 'accountant'])
            ->assertForbidden();

        expect($team->getUserTeamRole($other)?->role)->toBe('customer_service');
    });
});

describe('KYC document download', function (): void {
    it('serves the owner and compliance staff, marks staff access in the audit log, and gives a business team member with a compliance team role a 404', function (): void {
        Storage::fake('private');
        $owner = User::factory()->create();
        Storage::disk('private')->put('kyc/' . $owner->uuid . '/passport.pdf', 'PDF-BYTES');
        $document = KycDocument::factory()->create([
            'user_uuid' => $owner->uuid,
            'file_path' => 'kyc/' . $owner->uuid . '/passport.pdf',
            'metadata'  => ['original_name' => 'passport.pdf'],
        ]);
        $path = "/api/compliance/kyc/documents/{$document->id}/download";

        // The business owner gives a member the compliance_officer team role
        // through team management, the path that used to copy it onto the
        // member's global roles.
        [$teamOwner, $team] = g385BusinessTeam();
        $member = g385Member($team, 'accountant');
        $this->actingAs($teamOwner)->put(route('teams.members.update', [$team, $member]), ['role' => 'compliance_officer']);

        Sanctum::actingAs($member->fresh() ?? $member, ['read', 'write', 'delete']);
        $this->getJson($path)->assertNotFound();
        expect(AuditLog::query()->where('action', 'kyc.document_downloaded')->count())->toBe(0)
            ->and(g385Roles($member))->toBe(['customer_business']);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        expect($this->get($path)->assertOk()->streamedContent())->toBe('PDF-BYTES');
        $ownLog = AuditLog::query()->where('action', 'kyc.document_downloaded')->latest('id')->firstOrFail();
        expect($ownLog->tags)->toBe('kyc,document');

        $officer = g385Staff('compliance_officer');
        Sanctum::actingAs($officer, ['read', 'write', 'delete']);
        expect($this->get($path)->assertOk()->streamedContent())->toBe('PDF-BYTES');
        $staffLog = AuditLog::query()->where('action', 'kyc.document_downloaded')->latest('id')->firstOrFail();
        expect($staffLog->tags)->toBe('kyc,document,staff_access')
            ->and($staffLog->user_uuid)->toBe($officer->uuid)
            ->and($staffLog->metadata)->toMatchArray(['staff_access' => true, 'document_owner_uuid' => $owner->uuid]);
    });
});

/**
 * Render the Filament EditUser page as $admin and return the page
 * component's Livewire snapshot (as the browser would send it back).
 */
function g385EditUserSnapshot(PHPUnit\Framework\TestCase $test, User $admin, User $record): string
{
    $html = (string) $test->actingAs($admin)->get("/admin/users/{$record->uuid}/edit")->assertOk()->getContent();

    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

    foreach ($matches[1] as $raw) {
        $json = htmlspecialchars_decode($raw, ENT_QUOTES);
        $snapshot = json_decode($json, true);

        if (is_array($snapshot) && str_ends_with((string) ($snapshot['memo']['name'] ?? ''), 'edit-user')) {
            return $json;
        }
    }

    throw new RuntimeException('EditUser snapshot not found in the admin page.');
}

/**
 * Replay a snapshot through POST /livewire/update, running the page's
 * delete header action.
 *
 * @return TestResponse<Illuminate\Http\JsonResponse>
 */
function g385ReplayDelete(PHPUnit\Framework\TestCase $test, string $snapshot): TestResponse
{
    return $test->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
        'components' => [[
            'snapshot' => $snapshot,
            'updates'  => [],
            'calls'    => [
                ['path' => '', 'method' => 'mountAction', 'params' => ['delete']],
                ['path' => '', 'method' => 'callMountedAction', 'params' => []],
            ],
        ]],
    ]);
}

describe('Filament admin check on Livewire update requests', function (): void {
    it('runs admin actions for a current admin', function (): void {
        $admin = g385Staff('admin');
        $victim = User::factory()->create();
        $snapshot = g385EditUserSnapshot($this, $admin, $victim);

        g385ReplayDelete($this, $snapshot)->assertOk();

        expect(User::query()->whereKey($victim->id)->exists())->toBeFalse();
    });

    it('rejects a demoted admin replaying a saved admin snapshot', function (): void {
        $admin = g385Staff('admin');
        $victim = User::factory()->create();
        $snapshot = g385EditUserSnapshot($this, $admin, $victim);

        $admin->removeRole('admin');
        $this->actingAs($admin->fresh() ?? $admin);

        g385ReplayDelete($this, $snapshot)->assertForbidden();

        expect(User::query()->whereKey($victim->id)->exists())->toBeTrue();
    });

    it('rejects a guest replaying a saved admin snapshot', function (): void {
        $admin = g385Staff('admin');
        $victim = User::factory()->create();
        $snapshot = g385EditUserSnapshot($this, $admin, $victim);

        Auth::forgetGuards();

        g385ReplayDelete($this, $snapshot)->assertUnauthorized();

        expect(User::query()->whereKey($victim->id)->exists())->toBeTrue();
    });
});

describe('team invitation throttling', function (): void {
    it('caps invitations per inviter across all of their teams and sends no mail past the cap', function (): void {
        Mail::fake();
        config(['jetstream.invitation_limits.per_inviter_hourly' => 3]);

        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        /** @var Team $second */
        $second = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $invite = app(InviteTeamMember::class);

        $invite->invite($owner, $team, 'victim1@example.com', 'admin');
        $invite->invite($owner, $team, 'victim2@example.com', 'admin');
        $invite->invite($owner, $second, 'victim3@example.com', 'admin');

        expect(fn () => $invite->invite($owner, $second, 'victim4@example.com', 'admin'))
            ->toThrow(ValidationException::class);

        Mail::assertSent(TeamInvitation::class, 3);
        expect($team->teamInvitations()->count() + $second->teamInvitations()->count())->toBe(3)
            ->and(TeamInvitationModel::query()->where('email', 'victim4@example.com')->exists())->toBeFalse();

        // Another inviter is not affected.
        $other = User::factory()->withPersonalTeam()->create();
        /** @var Team $otherTeam */
        $otherTeam = $other->ownedTeams()->firstOrFail();
        $invite->invite($other, $otherTeam, 'victim4@example.com', 'admin');
        Mail::assertSent(TeamInvitation::class, 4);
    });

    it('caps invitations per recipient address per team, and cancelling does not reset the count', function (): void {
        Mail::fake();
        config(['jetstream.invitation_limits.per_recipient_daily' => 2]);
        $invite = app(InviteTeamMember::class);

        $first = User::factory()->withPersonalTeam()->create();
        /** @var Team $firstTeam */
        $firstTeam = $first->ownedTeams()->firstOrFail();

        $invite->invite($first, $firstTeam, 'target@example.com', 'admin');
        $firstTeam->teamInvitations()->delete(); // cancelTeamInvitation
        $invite->invite($first, $firstTeam, 'Target@Example.com', 'admin');
        $firstTeam->teamInvitations()->delete();

        expect(fn () => $invite->invite($first, $firstTeam, 'target@example.com', 'admin'))
            ->toThrow(ValidationException::class);

        Mail::assertSent(TeamInvitation::class, 2);
        expect($firstTeam->teamInvitations()->count())->toBe(0);

        // Another team's invitations to the same address do not use up this
        // team's allowance, so nobody can block an address for other teams.
        $second = User::factory()->withPersonalTeam()->create();
        /** @var Team $secondTeam */
        $secondTeam = $second->ownedTeams()->firstOrFail();

        $invite->invite($second, $secondTeam, 'target@example.com', 'admin');
        Mail::assertSent(TeamInvitation::class, 3);
        expect(TeamInvitationModel::query()->where('team_id', $secondTeam->id)->where('email', 'target@example.com')->exists())->toBeTrue();
    });

    it('renders Markdown link syntax in the team name as plain text', function (): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        $team->forceFill(['name' => '[Unlock your wallet](https://evil.example/login)'])->save();

        $invitation = TeamInvitationModel::create(['team_id' => $team->id, 'email' => 'victim@example.com', 'role' => 'admin']);

        $html = (new TeamInvitation($invitation))->render();

        // GHSA-rrfq: a name that fails the team name rule is not shown at all,
        // so its web address cannot be turned into a link by the mail client.
        expect($html)->not->toContain('href="https://evil.example/login"')
            ->and($html)->not->toContain('evil.example')
            ->and($html)->toContain('You have been invited to join a team.');
    });

    // Names stored before the team name rule, or personal team names built
    // from a user's name before GHSA-rrfq, are replaced by a neutral sentence.
    it('never turns a stored team name into Markdown in the invitation email', function (string $name): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        $team->forceFill(['name' => $name])->save();

        $invitation = TeamInvitationModel::create(['team_id' => $team->id, 'email' => 'victim@example.com', 'role' => 'admin']);

        $html = (new TeamInvitation($invitation))->render();

        expect($html)->not->toMatch('/<a\b[^>]*evil\.example/i')
            ->and($html)->not->toMatch('/<img\b[^>]*evil\.example/i')
            ->and($html)->not->toMatch('/<(h[1-6]|code|pre|em|strong|blockquote|li)\b[^>]*>[^<]*(Unlock|evil)/i')
            ->and($html)->not->toContain('evil.example')
            ->and($html)->toContain('You have been invited to join a team.');
    })->with([
        'backslash before the brackets' => ['\\[Unlock your wallet\\](https://evil.example/login)'],
        'double backslash'              => ['\\\\[Unlock your wallet\\\\](https://evil.example/login)'],
        'image'                         => ['![Unlock your wallet](https://evil.example/login.png)'],
        'autolink'                      => ['Unlock your wallet <https://evil.example/login>'],
        'reference link'                => ["[Unlock your wallet][x]\n\n[x]: https://evil.example/login"],
        'emphasis and code'             => ['**Unlock your wallet** `https://evil.example/login`'],
        'heading on a new line'         => ["x\n# Unlock your wallet https://evil.example/login"],
    ]);

    it('only accepts letters, numbers, spaces and basic punctuation in team names', function (): void {
        $owner = User::factory()->withPersonalTeam()->create();
        /** @var Team $team */
        $team = $owner->ownedTeams()->firstOrFail();
        $originalName = $team->name;
        $teamCount = Team::query()->count();

        foreach (['[Unlock](https://evil.example)', 'Acme (EU)', 'Back\\slash', '<b>Acme</b>', "Acme\nCorp", 'Acme_*Corp*', 'Acme `x`', 'Acme | x'] as $bad) {
            expect(fn () => app(CreateTeam::class)->create($owner, ['name' => $bad]))
                ->toThrow(ValidationException::class);
            expect(fn () => app(UpdateTeamName::class)->update($owner, $team, ['name' => $bad]))
                ->toThrow(ValidationException::class);
        }

        expect(Team::query()->count())->toBe($teamCount)
            ->and($team->fresh()?->name)->toBe($originalName);

        foreach (["O'Brien & Sons", 'Zelta-Pay, Inc.', 'Łukasz Žemaitis Team 2', 'Acme Ltd.'] as $good) {
            $created = app(CreateTeam::class)->create($owner, ['name' => $good]);
            expect($created->name)->toBe($good);

            app(UpdateTeamName::class)->update($owner, $team, ['name' => $good]);
            expect($team->fresh()?->name)->toBe($good);
        }
    });
});

describe('user:audit-staff-roles', function (): void {
    it('flags every holder of a staff or legacy team role who is not on the operator allowlist', function (): void {
        config(['security.staff_role_allowlist' => 'Config-Operator@Example.com']);
        [$owner, $team] = g385BusinessTeam();
        $owner->assignRole('bank_admin'); // owns a business team

        // An outside account the old update path granted a role to: it owns
        // only its personal team and is in no business team.
        $outside = User::factory()->withPersonalTeam()->create();
        $outside->syncRoles(['compliance_officer']);

        // A member who got the global role and was later removed from the team.
        $removed = g385Member($team, 'compliance_officer');
        $removed->assignRole('risk_manager');
        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $removed]))
            ->assertRedirect(route('teams.members.index', $team));
        expect($team->users()->whereKey($removed->id)->exists())->toBeFalse();

        // A current member holding a legacy role.
        $legacy = g385Member($team, 'operations_manager');
        $legacy->assignRole('operations_manager');

        // An account with a staff role and no team at all.
        $teamless = g385Staff('super_admin');

        // Operators on the allowlist (option and config).
        $operator = g385Staff('admin');
        $configOperator = User::factory()->create(['email' => 'config-operator@example.com']);
        $configOperator->assignRole('compliance_officer');

        $plain = g385Member($team, 'accountant');

        $this->artisan('user:audit-staff-roles', ['--allow' => [strtoupper($operator->email)]])
            ->expectsOutputToContain($outside->email)
            ->expectsOutputToContain($removed->email)
            ->expectsOutputToContain($legacy->email)
            ->expectsOutputToContain($teamless->email)
            ->expectsOutputToContain($owner->email)
            ->expectsOutputToContain($operator->email)
            ->expectsOutputToContain($configOperator->email)
            ->doesntExpectOutputToContain($plain->email)
            ->expectsOutputToContain('7 user(s) hold audited roles; 5 flagged for review, 2 on the operator allowlist.')
            ->assertExitCode(1);

        $this->artisan('user:audit-staff-roles', ['--flagged' => true, '--allow' => [$operator->email]])
            ->expectsOutputToContain($outside->email)
            ->expectsOutputToContain($removed->email)
            ->doesntExpectOutputToContain($operator->email)
            ->doesntExpectOutputToContain($configOperator->email)
            ->assertExitCode(1);

        // Read-only without --revoke.
        expect(g385Roles($outside))->toBe(['compliance_officer'])
            ->and(g385Roles($removed))->toBe(['customer_business', 'risk_manager'])
            ->and(g385Roles($legacy))->toContain('operations_manager');
    });

    it('exits 0 when every holder is on the operator allowlist', function (): void {
        [, $team] = g385BusinessTeam();
        g385Member($team, 'accountant');
        $staff = g385Staff('compliance_officer');
        config(['security.staff_role_allowlist' => [$staff->email]]);

        $this->artisan('user:audit-staff-roles')
            ->expectsOutputToContain($staff->email)
            ->expectsOutputToContain('1 user(s) hold audited roles; 0 flagged for review, 1 on the operator allowlist.')
            ->assertExitCode(0);
    });

    it('revokes the audited roles of a flagged user only after confirmation', function (): void {
        $operator = g385Staff('admin');
        $outside = User::factory()->withPersonalTeam()->create();
        $outside->syncRoles(['compliance_officer', 'customer_private']);
        $kept = g385Staff('risk_manager');

        // Without an interactive confirmation nothing changes.
        $this->artisan('user:audit-staff-roles', ['--revoke' => true, '--allow' => [$operator->email], '--no-interaction' => true])
            ->assertExitCode(1);
        expect(g385Roles($outside))->toBe(['compliance_officer', 'customer_private'])
            ->and(g385Roles($kept))->toBe(['risk_manager']);

        $this->artisan('user:audit-staff-roles', ['--revoke' => true, '--allow' => [$operator->email]])
            ->expectsConfirmation("Remove compliance_officer from {$outside->email} (ID {$outside->id})?", 'yes')
            ->expectsConfirmation("Remove risk_manager from {$kept->email} (ID {$kept->id})?", 'no')
            ->expectsOutputToContain('1 flagged user(s) still hold audited roles.')
            ->assertExitCode(1);

        expect(g385Roles($outside))->toBe(['customer_private'])
            ->and(g385Roles($kept))->toBe(['risk_manager'])
            ->and(g385Roles($operator))->toBe(['admin']);

        $this->artisan('user:audit-staff-roles', ['--revoke' => true, '--allow' => [$operator->email]])
            ->expectsConfirmation("Remove risk_manager from {$kept->email} (ID {$kept->id})?", 'yes')
            ->assertExitCode(0);
        expect(g385Roles($kept))->toBe([]);
    });

    it('never revokes the last admin', function (): void {
        $admin = g385Staff('admin');
        $admin->assignRole('compliance_officer');

        $this->artisan('user:audit-staff-roles', ['--revoke' => true])
            ->expectsConfirmation("Remove admin, compliance_officer from {$admin->email} (ID {$admin->id})?", 'yes')
            ->expectsOutputToContain('Kept the admin role')
            ->assertExitCode(1);

        expect(g385Roles($admin))->toBe(['admin']);
    });

    it('lets user:demote revoke a staff role it flagged', function (): void {
        [, $team] = g385BusinessTeam();
        $granted = g385Member($team, 'compliance_officer');
        $granted->assignRole('compliance_officer');

        $this->artisan('user:demote', ['email' => $granted->email, '--role' => 'compliance_officer'])
            ->assertExitCode(0);

        expect(g385Roles($granted))->toBe(['customer_business']);

        $this->artisan('user:demote', ['email' => $granted->email, '--role' => 'customer_business'])
            ->assertExitCode(1);
        expect(g385Roles($granted))->toBe(['customer_business']);
    });
});
