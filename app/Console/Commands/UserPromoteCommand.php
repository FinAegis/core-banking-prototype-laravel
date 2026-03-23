<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class UserPromoteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:promote
                            {email : Email of the user to promote}
                            {--role=admin : Role to assign (admin, super_admin)}';

    /**
     * @var string
     */
    protected $description = 'Promote an existing user to admin or super_admin';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleName = (string) $this->option('role');

        $allowedRoles = ['admin', 'super_admin'];
        if (! in_array($roleName, $allowedRoles, true)) {
            $this->error("Invalid role: {$roleName}. Allowed: " . implode(', ', $allowedRoles));

            return 1;
        }

        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User not found: {$email}");

            return 1;
        }

        if ($user->hasRole($roleName)) {
            $this->info("{$email} already has the '{$roleName}' role.");

            return 0;
        }

        Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($roleName);

        $this->info("Promoted {$email} to '{$roleName}'.");

        return 0;
    }
}
