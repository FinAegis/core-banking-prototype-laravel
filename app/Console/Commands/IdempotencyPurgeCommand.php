<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily sweep for the `idempotency_keys` table.
 *
 * Drops rows whose `expires_at` is in the past. Idempotent — safe to re-run
 * on the same day. Scheduled at 02:00 UTC via routes/console.php.
 *
 *   php artisan idempotency:purge
 *   php artisan idempotency:purge --dry-run
 */
class IdempotencyPurgeCommand extends Command
{
    protected $signature = 'idempotency:purge
        {--dry-run : Print the count without deleting anything}';

    protected $description = 'Purge expired rows from idempotency_keys (24h TTL).';

    public function handle(): int
    {
        $now = Carbon::now();

        $count = DB::table('idempotency_keys')
            ->where('expires_at', '<', $now)
            ->count();

        if ($count === 0) {
            $this->info('No expired idempotency keys. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                'Found %d expired idempotency key(s) older than %s. Re-run without --dry-run to purge.',
                $count,
                $now->toDateTimeString(),
            ));

            return self::SUCCESS;
        }

        $deleted = DB::table('idempotency_keys')
            ->where('expires_at', '<', $now)
            ->delete();

        $this->info(sprintf('Purged %d expired idempotency key(s).', $deleted));

        return self::SUCCESS;
    }
}
