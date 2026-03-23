<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UserListAdminsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:admins';

    /**
     * @var string
     */
    protected $description = 'List all users with admin or super_admin roles';

    public function handle(): int
    {
        $admins = User::role(['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found. Create one with: php artisan user:create --admin');

            return 0;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Roles', 'Created'],
            $admins->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->getRoleNames()->implode(', '),
                $user->created_at?->format('Y-m-d H:i'),
            ])->toArray()
        );

        return 0;
    }
}
