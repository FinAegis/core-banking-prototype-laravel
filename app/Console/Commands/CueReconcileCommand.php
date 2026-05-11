<?php

/**
 * CueReconcileCommand — recovery cron for stuck cue jobs.
 *
 * Resets claimed_at for cue-related jobs that were claimed (pulled from the queue)
 * but never completed — they likely belong to a crashed worker. After the timeout
 * (10 minutes), they are visible to workers again for retry.
 *
 * This is a safety net for the database queue driver's visibility timeout.
 * Laravel's queue:work --timeout flag should handle most cases; this command
 * provides an explicit audit trail.
 *
 * NOTE: This command operates on the standard `jobs` table (Laravel database queue).
 * It does NOT modify the `cues` table.
 *
 *   php artisan cue:reconcile
 *   php artisan cue:reconcile --dry-run
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §15 OD-4
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CueReconcileCommand extends Command
{
    protected $signature = 'cue:reconcile
        {--dry-run : Print the count without resetting}
        {--timeout=10 : Claimed-but-not-processed timeout in minutes}';

    protected $description = 'Reset stuck cue jobs in the database queue (claimed but not processed)';

    /** Cue job class prefixes to filter in the jobs table. */
    private const CUE_JOB_CLASSES = [
        'App\\\\Domain\\\\Subscription\\\\Jobs\\\\Cue\\\\',
    ];

    public function handle(): int
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $cutoff = Carbon::now()->subMinutes($timeoutMinutes);

        // Count stuck jobs: reserved_at is set (claimed) but the job is still
        // in the table more than $timeout minutes later.
        $stuckCount = DB::table('jobs')
            ->where('reserved_at', '<=', $cutoff->timestamp)
            ->where(function ($query): void {
                foreach (self::CUE_JOB_CLASSES as $prefix) {
                    $query->orWhere('payload', 'like', '%' . str_replace('\\\\', '\\', $prefix) . '%');
                }
            })
            ->count();

        Log::info('cue.reconcile.stuck_jobs', [
            'count'           => $stuckCount,
            'timeout_minutes' => $timeoutMinutes,
        ]);

        if ($stuckCount === 0) {
            $this->info('No stuck cue jobs found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                '[dry-run] Found %d stuck cue job(s) older than %d minutes. Re-run without --dry-run to reset.',
                $stuckCount,
                $timeoutMinutes,
            ));

            return self::SUCCESS;
        }

        $reset = DB::table('jobs')
            ->where('reserved_at', '<=', $cutoff->timestamp)
            ->where(function ($query): void {
                foreach (self::CUE_JOB_CLASSES as $prefix) {
                    $query->orWhere('payload', 'like', '%' . str_replace('\\\\', '\\', $prefix) . '%');
                }
            })
            ->update(['reserved_at' => null, 'attempts' => DB::raw('attempts + 1')]);

        Log::info('cue.reconcile.reset', [
            'count'           => $reset,
            'timeout_minutes' => $timeoutMinutes,
        ]);

        $this->info(sprintf('Reset %d stuck cue job(s).', $reset));

        return self::SUCCESS;
    }
}
