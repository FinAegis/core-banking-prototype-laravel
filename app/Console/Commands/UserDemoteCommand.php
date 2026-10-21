<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UserDemoteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:demote
                            {email : Email of the user to demote}
                            {--role=admin : Role to remove (a staff role, e.g. admin, super_admin, compliance_officer)}';

    /**
     * @var string
     */
    protected $description = 'Remove an admin or other staff role from a user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleName = (string) $this->option('role');

        // Staff roles, plus the legacy roles business team management used to
        // grant (see user:audit-staff-roles).
        $allowedRoles = array_values(array_unique([...StaffRoles::ALL, ...UserAuditStaffRolesCommand::LEGACY_TEAM_ROLES]));
        if (! in_array($roleName, $allowedRoles, true)) {
            $this->error("Invalid role: {$roleName}. Allowed: " . implode(', ', $allowedRoles));

            return 1;
        }

        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User not found: {$email}");

            return 1;
        }

        if (! $user->hasRole($roleName)) {
            $this->info("{$email} does not have the '{$roleName}' role.");

            return 0;
        }

        // Prevent removing the last admin — would lock everyone out of /admin
        if ($roleName === 'admin') {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                $this->error('Cannot remove the last admin. Promote another user first.');

                return 1;
            }
        }

        $user->removeRole($roleName);

        Log::info('Admin role removed via CLI', [
            'user_id' => $user->id,
            'email'   => $email,
            'role'    => $roleName,
        ]);

        $this->info("Removed '{$roleName}' role from {$email}.");

        return 0;
    }
}
