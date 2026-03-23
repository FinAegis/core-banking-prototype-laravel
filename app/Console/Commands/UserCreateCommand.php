<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserCreateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:create
                            {--name= : Full name}
                            {--email= : Email address}
                            {--password= : Password (prompted if omitted)}
                            {--admin : Assign admin role}';

    /**
     * @var string
     */
    protected $description = 'Create a new user account (production-safe alternative to web registration)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');
        $makeAdmin = (bool) $this->option('admin');

        $validator = Validator::make([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ], [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return 1;
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make((string) $password),
        ]);

        if ($makeAdmin) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->assignRole('admin');
        }

        $this->info("User created: {$user->email} (ID: {$user->id})");
        if ($makeAdmin) {
            $this->info('Admin role assigned — can access /admin dashboard.');
        }

        return 0;
    }
}
