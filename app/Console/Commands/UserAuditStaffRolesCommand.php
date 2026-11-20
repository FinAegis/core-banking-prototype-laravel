<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamUserRole;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Audit of global (spatie) staff roles.
 *
 * Business team management used to copy a member's team role onto the
 * member's global roles, for members and for any user picked by uuid. So an
 * owner of a business team could hand out compliance_officer / risk_manager
 * and the legacy accountant, operations_manager and customer_service roles,
 * which carry platform permissions. Such a role stays after the user leaves
 * the team, and the user may own a team or no team at all, so team data
 * cannot tell a legitimate operator from a self-granted role.
 *
 * The command therefore flags every holder of an audited role who is not on
 * the operator allowlist (config security.staff_role_allowlist and the
 * --allow option). A listed address counts only when it is verified. It
 * changes nothing unless --revoke is given, and then only after a
 * confirmation for each flagged user.
 */
class UserAuditStaffRolesCommand extends Command
{
    /**
     * Global roles that business team management used to grant besides the
     * StaffRoles names; they carry platform permissions (view_all_customers,
     * edit_customer_accounts, reverse_transactions, ...).
     */
    public const LEGACY_TEAM_ROLES = ['accountant', 'operations_manager', 'customer_service'];

    /**
     * @var string
     */
    protected $signature = 'user:audit-staff-roles
                            {--allow=* : Email of an operator whose staff roles were granted by an operator (repeatable; adds to config security.staff_role_allowlist)}
                            {--flagged : Only list the flagged users}
                            {--revoke : Ask, for each flagged user, whether to remove their audited roles}';

    /**
     * @var string
     */
    protected $description = 'List users holding staff roles and flag every holder who is not on the operator allowlist (exit 1 when any is flagged)';

    public function handle(): int
    {
        $auditedRoles = self::auditedRoles();
        $allowlist = $this->allowlist();

        $holders = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $auditedRoles))
            ->with('roles')
            ->orderBy('id')
            ->get();

        $rows = [];
        /** @var list<User> $flagged */
        $flagged = [];

        foreach ($holders as $user) {
            /** @var User $user */
            $roles = $this->heldRoles($user, $auditedRoles);
            // A listed address counts only once it is verified. An email
            // change takes effect only after the new address is confirmed,
            // so a role holder cannot pose as a listed operator by switching
            // to an address they do not control.
            $isListed = in_array(mb_strtolower((string) $user->email), $allowlist, true);
            $isAllowlisted = $isListed && $user->email_verified_at !== null;
            $ownedTeams = Team::query()->where('user_id', $user->id)->orderBy('id')->get();
            $memberships = $this->businessMemberships($user);

            if (! $isAllowlisted) {
                $flagged[] = $user;
            }

            if ($this->option('flagged') && $isAllowlisted) {
                continue;
            }

            $rows[] = [
                $user->id,
                $this->clean((string) $user->email),
                implode(', ', $roles),
                $ownedTeams->isEmpty() ? 'none' : $ownedTeams->map(fn (Team $team): string => $this->describeTeam($team))->implode('; '),
                $memberships === [] ? '-' : implode('; ', $memberships),
                $isAllowlisted ? 'allowlisted' : 'REVIEW: ' . ($isListed ? 'allowlisted address is not verified' : $this->reason($ownedTeams, $memberships)),
            ];
        }

        if ($rows !== []) {
            $this->table(['ID', 'Email', 'Audited roles', 'Owned teams', 'Business team memberships (not owner)', 'Flag'], $rows);
        }

        $this->line(sprintf(
            '%d user(s) hold audited roles; %d flagged for review, %d on the operator allowlist.',
            $holders->count(),
            count($flagged),
            $holders->count() - count($flagged),
        ));

        if ($flagged === []) {
            return self::SUCCESS;
        }

        if (! $this->option('revoke')) {
            $this->warn('Confirm each flagged role was granted by an operator. Add operators to STAFF_ROLE_ALLOWLIST (or --allow), and revoke the others with --revoke or: php artisan user:demote <email> --role=<role>');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->warn('--revoke needs an interactive confirmation for each user; nothing was revoked.');

            return self::FAILURE;
        }

        $remaining = 0;

        foreach ($flagged as $user) {
            if ($this->revokeAfterConfirmation($user, $auditedRoles)) {
                continue;
            }

            $remaining++;
        }

        if ($remaining > 0) {
            $this->warn(sprintf('%d flagged user(s) still hold audited roles.', $remaining));

            return self::FAILURE;
        }

        $this->info('No flagged user holds an audited role any more.');

        return self::SUCCESS;
    }

    /**
     * Staff roles plus the legacy roles business team management used to
     * grant.
     *
     * @return list<string>
     */
    public static function auditedRoles(): array
    {
        return array_values(array_unique([...StaffRoles::ALL, ...self::LEGACY_TEAM_ROLES]));
    }

    /**
     * Ask, then remove the user's audited roles. Never removes the last
     * admin. Returns true when the user no longer holds an audited role.
     *
     * @param  list<string>  $auditedRoles
     */
    private function revokeAfterConfirmation(User $user, array $auditedRoles): bool
    {
        $roles = $this->heldRoles($user, $auditedRoles);

        if (! $this->confirm(sprintf('Remove %s from %s (ID %d)?', implode(', ', $roles), $user->email, $user->id), false)) {
            return false;
        }

        $removed = [];

        foreach ($roles as $role) {
            if ($role === 'admin' && User::role('admin')->count() <= 1) {
                $this->error(sprintf('Kept the admin role of %s: it is the last admin. Promote another user first.', $user->email));

                continue;
            }

            $user->removeRole($role);
            $removed[] = $role;
        }

        if ($removed !== []) {
            Log::warning('Staff roles removed via user:audit-staff-roles', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'roles'   => $removed,
            ]);

            $this->info(sprintf('Removed %s from %s.', implode(', ', $removed), $user->email));
        }

        return count($removed) === count($roles);
    }

    /**
     * Operator emails (lowercase) from config security.staff_role_allowlist
     * (a comma-separated string or a list) and the --allow option.
     *
     * @return list<string>
     */
    private function allowlist(): array
    {
        $configured = config('security.staff_role_allowlist', []);
        $entries = is_array($configured) ? $configured : explode(',', (string) $configured);

        $option = $this->option('allow');
        if (is_array($option)) {
            $entries = [...$entries, ...$option];
        }

        $emails = [];
        foreach ($entries as $entry) {
            $email = mb_strtolower(trim((string) $entry));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @param  list<string>  $auditedRoles
     * @return list<string>
     */
    private function heldRoles(User $user, array $auditedRoles): array
    {
        $roles = $user->roles->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->intersect($auditedRoles)
            ->sort()
            ->all();

        return array_values($roles);
    }

    private function describeTeam(Team $team): string
    {
        return sprintf(
            '#%d %s (%s)',
            $team->id,
            $this->clean((string) $team->name),
            $team->is_business_organization ? 'business' : ($team->personal_team ? 'personal' : 'team'),
        );
    }

    /**
     * Team names are free text: drop control characters and escape console
     * style tags before printing them.
     */
    private function clean(string $text): string
    {
        return OutputFormatter::escape((string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', mb_scrub($text, 'UTF-8')));
    }

    /**
     * Business teams the user belongs to without owning them.
     *
     * @return list<string>
     */
    private function businessMemberships(User $user): array
    {
        $teams = Team::query()
            ->where('is_business_organization', true)
            ->where('user_id', '!=', $user->id)
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->orderBy('id')
            ->get()
            ->map(function (Team $team) use ($user): string {
                $teamRole = TeamUserRole::query()
                    ->where('team_id', $team->id)
                    ->where('user_id', $user->id)
                    ->value('role');

                return sprintf('#%d %s (team role: %s)', $team->id, $this->clean((string) $team->name), is_string($teamRole) ? $this->clean($teamRole) : '-');
            })
            ->all();

        return array_values($teams);
    }

    /**
     * @param  Collection<int, Team>  $ownedTeams
     * @param  list<string>  $memberships
     */
    private function reason(Collection $ownedTeams, array $memberships): string
    {
        if ($memberships !== []) {
            return 'member of a customer business team';
        }

        if ($ownedTeams->contains(fn (Team $team): bool => (bool) $team->is_business_organization)) {
            return 'owns a customer business team';
        }

        if ($ownedTeams->isEmpty()) {
            return 'owns no team';
        }

        return 'not on the operator allowlist';
    }
}
