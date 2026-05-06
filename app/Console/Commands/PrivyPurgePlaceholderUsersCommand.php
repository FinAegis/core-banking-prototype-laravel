<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off cleanup for users created by the v7.12.0 placeholder-email path
 * before email-OTP login (v7.13.0) shipped. Their email is of the form
 * `privy_<last16>@placeholder.zelta.app` and is never reachable.
 *
 * Pre-launch this is the easiest path: nuke the placeholder records and let
 * users re-signup through the email-OTP flow. Post-launch, run a backfill
 * that fetches each user's real email from Privy and updates them in place
 * instead — but that is not what this command does.
 *
 *   php artisan privy:purge-placeholder-users --dry-run
 *   php artisan privy:purge-placeholder-users --confirm
 */
class PrivyPurgePlaceholderUsersCommand extends Command
{
    private const PLACEHOLDER_EMAIL_PATTERN = 'privy\_%@placeholder.zelta.app';

    protected $signature = 'privy:purge-placeholder-users
        {--dry-run : Print the count without deleting anything}
        {--confirm : Skip the interactive confirmation prompt (for scripted runs)}';

    protected $description = 'Delete user rows whose email is a Privy placeholder (v7.12.0 transitional artefact)';

    public function handle(): int
    {
        $query = User::query()->where('email', 'like', self::PLACEHOLDER_EMAIL_PATTERN);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No placeholder users found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d user(s) with placeholder emails.', $count));

        if ($this->option('dry-run')) {
            $this->info('Dry run — no rows deleted. Re-run without --dry-run to purge.');

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            if (! $this->confirm("Delete {$count} placeholder user(s)? This cannot be undone.", false)) {
                $this->warn('Cancelled.');

                return self::FAILURE;
            }
        }

        $deleted = User::query()->where('email', 'like', self::PLACEHOLDER_EMAIL_PATTERN)->delete();
        $this->info(sprintf('Purged %d placeholder user(s).', $deleted));

        return self::SUCCESS;
    }
}
