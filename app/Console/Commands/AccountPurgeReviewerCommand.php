<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Events\AccountPurged;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

class AccountPurgeReviewerCommand extends Command
{
    /** @var string */
    protected $signature = 'account:purge-reviewer
                            {--email=}
                            {--confirm}
                            {--allow-production}
                            {--operator-email= : Admin operator email (required)}';

    /** @var string */
    protected $description = 'Anonymize + disable a review account (blocked in production without --allow-production)';

    public function handle(AccountProvisioningService $service): int
    {
        if (! $this->option('confirm')) {
            $this->error('--confirm is required.');

            return 1;
        }

        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required.');

            return 1;
        }

        $operatorEmail = (string) $this->option('operator-email');
        if ($operatorEmail === '') {
            $this->error('--operator-email is required');

            return 1;
        }

        $operator = User::where('email', $operatorEmail)->first();
        if ($operator === null || ! $operator->hasRole(UserRoles::ADMIN->value)) {
            $this->error("Operator {$operatorEmail} not found or not an admin.");

            return 1;
        }

        $email = (string) $this->option('email');
        $user = User::where('email', $email)->first();

        if ($user === null || $user->accountFlag === null || ! $user->accountFlag->is_review_account) {
            $this->error("User {$email} is not a review account.");

            return 1;
        }

        // User model has no SoftDeletes trait, so we anonymize + disable instead
        // of hard-deleting. Keeps the AccountFlag audit row intact and preserves
        // referential integrity for FK-bearing tables (accounts, tokens, etc.).
        $service->disable($user);

        $user->forceFill([
            'email'    => "purged-{$user->id}-" . bin2hex(random_bytes(8)) . '@example.invalid',
            'password' => Hash::make(bin2hex(random_bytes(16))),
        ])->save();

        Event::dispatch(new AccountPurged(userId: (int) $user->id, operatorId: (int) $operator->id));

        $this->info("purged: {$email}");

        return 0;
    }
}
