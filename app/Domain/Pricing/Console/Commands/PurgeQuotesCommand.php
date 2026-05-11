<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PurgeQuotesCommand — daily expiry purge for price_quotes.
 *
 * Deletes rows where expires_at < NOW() - INTERVAL 7 DAY.
 * The 7-day grace period allows post-expiry forensics (e.g., the ADR-0002
 * chain ingestor referencing user_op_hash after a slow chain confirmation).
 * After 7 days, the row can safely be deleted.
 *
 * Scheduled daily at 03:10 UTC (offset from idempotency:purge at 02:00 and
 * trial:purge-fingerprints at 02:30 to avoid concurrent DELETE contention).
 *
 * Mirrors the idempotency:purge and trial:purge-fingerprints patterns from
 * slice 1. Redeemed rows (consumed_at IS NOT NULL) are also purged after 7
 * days — they are no longer needed once the grace window closes.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.10
 */
final class PurgeQuotesCommand extends Command
{
    /** @var string */
    protected $signature = 'pricing:purge-quotes
                            {--dry-run : Show what would be deleted without deleting}';

    /** @var string */
    protected $description = 'Purge expired price_quotes rows older than 7 days (Plan B slice 3)';

    public function handle(): int
    {
        $cutoff = now()->subDays(7)->format('Y-m-d H:i:s');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $count = DB::table('price_quotes')
                ->where('expires_at', '<', $cutoff)
                ->count();

            $this->info(sprintf(
                '[dry-run] Would delete %d price_quotes rows with expires_at < %s',
                $count,
                $cutoff,
            ));

            return Command::SUCCESS;
        }

        $deleted = DB::table('price_quotes')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $message = sprintf(
            'Purged %d price_quotes rows with expires_at < %s',
            $deleted,
            $cutoff,
        );

        $this->info($message);
        Log::info('[pricing:purge-quotes] ' . $message);

        return Command::SUCCESS;
    }
}
