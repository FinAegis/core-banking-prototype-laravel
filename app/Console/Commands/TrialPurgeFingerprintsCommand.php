<?php

/**
 * Daily sweep for the `trial_card_fingerprints` table.
 *
 * Drops rows whose `last_used_at` is older than 12 months — past the trial
 * retry window, those fingerprints are eligible again so the gate doesn't
 * need a row anymore. Idempotent — safe to re-run.
 *
 * Scheduled daily at 02:30 UTC via routes/console.php (offset from
 * idempotency:purge at 02:00 to avoid table lock contention).
 *
 *   php artisan trial:purge-fingerprints
 *   php artisan trial:purge-fingerprints --dry-run
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Subscription\Services\TrialFingerprintService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class TrialPurgeFingerprintsCommand extends Command
{
    protected $signature = 'trial:purge-fingerprints
        {--dry-run : Print the count without deleting anything}';

    protected $description = 'Purge trial_card_fingerprints rows older than the 12-month retry window.';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMonths(TrialFingerprintService::RETRY_WINDOW_MONTHS);

        $count = DB::table('trial_card_fingerprints')
            ->where('last_used_at', '<', $cutoff)
            ->count();

        if ($count === 0) {
            $this->info('No expired trial fingerprints. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                'Found %d expired trial fingerprint(s) older than %s. Re-run without --dry-run to purge.',
                $count,
                $cutoff->toDateTimeString(),
            ));

            return self::SUCCESS;
        }

        $deleted = DB::table('trial_card_fingerprints')
            ->where('last_used_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf('Purged %d expired trial fingerprint(s).', $deleted));

        return self::SUCCESS;
    }
}
