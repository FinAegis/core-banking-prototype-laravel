<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\TeamUserRole;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Console\Command;

/**
 * Read-only audit of global (spatie) staff roles.
 *
 * Business team management used to copy a member's team role onto the
 * member's global roles, so an owner of a business team could hand out
 * compliance_officer / risk_manager (and the legacy accountant,
 * operations_manager and customer_service roles, which carry platform
 * permissions). This command lists every holder of those roles and flags
 * the ones who are also a non-owner member of a business team, so an
 * operator can review them and revoke with `user:demote`. It never changes
 * any role itself.
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
                            {--flagged : Only list the flagged users}';

    /**
     * @var string
     */
    protected $description = 'List users holding staff roles and flag those who are also non-owner members of a business team (exit 1 when any is flagged)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $auditedRoles = array_values(array_unique([...StaffRoles::ALL, ...self::LEGACY_TEAM_ROLES]));

        $holders = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $auditedRoles))
            ->with('roles')
            ->orderBy('id')
            ->get();

        $rows = [];
        $flagged = 0;

        foreach ($holders as $user) {
            /** @var User $user */
            $roles = $user->roles->pluck('name')->intersect($auditedRoles)->values()->implode(', ');

            $memberships = Team::query()
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

                    return sprintf('#%d %s (team role: %s)', $team->id, $team->name, is_string($teamRole) ? $teamRole : '-');
                })
                ->values();

            $isFlagged = $memberships->isNotEmpty();

            if ($isFlagged) {
                $flagged++;
            }

            if ($this->option('flagged') && ! $isFlagged) {
                continue;
            }

            $rows[] = [
                $user->id,
                $user->email,
                $roles,
                $memberships->isEmpty() ? '-' : $memberships->implode('; '),
                $isFlagged ? 'REVIEW' : '',
            ];
        }

        if ($rows !== []) {
            $this->table(['ID', 'Email', 'Staff roles', 'Non-owner business team memberships', 'Flag'], $rows);
        }

        $this->line(sprintf('%d user(s) hold staff roles; %d flagged for review.', $holders->count(), $flagged));

        if ($flagged > 0) {
            $this->warn('Flagged users are members of a customer business team. Confirm each staff role was granted by an operator; revoke the others with: php artisan user:demote <email> --role=<role>');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
