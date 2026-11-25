<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * One-off backfill: users linked to Privy whose email_verified_at is still
 * NULL (linked before the Privy linker set it, or created by older code) are
 * marked verified, because Privy verified their address. Safe to run more
 * than once: only NULL columns are filled, and an earlier verification time
 * is never changed. --dry-run only prints how many users would change.
 */
class UsersBackfillPrivyVerifiedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'users:backfill-privy-verified
                            {--dry-run : Only print how many users would be marked verified}';

    /**
     * @var string
     */
    protected $description = 'Mark Privy-linked users with no email_verified_at as verified (one-off, idempotent)';

    public function handle(): int
    {
        if ((bool) $this->option('dry-run')) {
            $count = $this->pending()->count();
            $this->info("{$count} Privy-linked users have no verified email. Dry run: nothing was changed.");

            return self::SUCCESS;
        }

        $updated = $this->pending()->update(['email_verified_at' => now()]);

        Log::info('Backfilled email_verified_at for Privy-linked users', ['count' => $updated]);
        $this->info("Marked {$updated} Privy-linked users as verified.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<User>
     */
    private function pending(): Builder
    {
        return User::query()
            ->whereNotNull('privy_user_id')
            ->where('privy_user_id', '!=', '')
            ->whereNull('email_verified_at');
    }
}
