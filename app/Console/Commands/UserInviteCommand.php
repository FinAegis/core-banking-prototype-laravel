<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\User\Services\UserInvitationService;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;

class UserInviteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:invite
                            {email : Email to invite}
                            {--role=private : Role to assign (private, admin, super_admin)}
                            {--inviter= : Email of the inviting admin (defaults to first admin)}';

    /**
     * @var string
     */
    protected $description = 'Send an email invitation to join the platform';

    public function handle(UserInvitationService $service): int
    {
        $email = (string) $this->argument('email');
        $role = (string) $this->option('role');

        $allowedRoles = ['private', 'admin', 'super_admin'];
        if (! in_array($role, $allowedRoles, true)) {
            $this->error("Invalid role: {$role}. Allowed: " . implode(', ', $allowedRoles));

            return 1;
        }

        $inviterEmail = $this->option('inviter');
        if (is_string($inviterEmail) && $inviterEmail !== '') {
            $inviter = User::where('email', $inviterEmail)->first();
        } else {
            $inviter = User::role('admin')->first() ?? User::first();
        }

        if ($inviter === null) {
            $this->error('No inviter found. Create an admin first: php artisan user:create --admin');

            return 1;
        }

        try {
            $invitation = $service->invite($email, $role, $inviter);

            $this->info("Invitation sent to {$email}");
            $this->line("  Role:    {$role}");
            $this->line("  Expires: {$invitation->expires_at->format('Y-m-d H:i')}");
            $this->line("  Token:   {$invitation->token}");
            $this->line('');
            $this->line('  Accept URL: ' . config('app.url') . '/invitation/accept?token=' . $invitation->token);

            return 0;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
