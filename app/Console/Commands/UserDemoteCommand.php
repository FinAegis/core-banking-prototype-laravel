<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserDemoteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:demote
                            {email : Email of the user to demote}
                            {--role=admin : Role to remove (admin, super_admin)}';

    /**
     * @var string
     */
    protected $description = 'Remove admin or super_admin role from a user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleName = (string) $this->option('role');

        $user = User::where('email', $email)->first();
        if ($user === null) {
            $this->error("User not found: {$email}");

            return 1;
        }

        if (! $user->hasRole($roleName)) {
            $this->info("{$email} does not have the '{$roleName}' role.");

            return 0;
        }

        $user->removeRole($roleName);

        $this->info("Removed '{$roleName}' role from {$email}.");

        return 0;
    }
}
