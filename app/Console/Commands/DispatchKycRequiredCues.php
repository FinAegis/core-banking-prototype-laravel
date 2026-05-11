<?php

/**
 * DispatchKycRequiredCues — aggregate-condition hourly cron (Plan B Backend-Q8).
 *
 * Runs at :15 past every hour. Scans users who have crossed the AMLD5 €1,000
 * lifetime spend threshold and have not completed KYC — inserting a kyc_required
 * cue for each candidate who doesn't already have an active (non-dismissed) one.
 *
 * Uses a LEFT JOIN candidate query rather than NOT IN (subquery) to avoid
 * performance degradation at scale (per Backend-Q8 aggregate-condition pattern).
 * chunkById(1000) for memory isolation.
 *
 * Idempotency: occurrence_window_start is epoch ('1970-01-01T00:00:00Z') —
 * lifetime window. The LEFT JOIN excludes users who already have a pending cue.
 * Users with dismissed cues ARE included (they may cross the threshold again).
 *
 *   php artisan cue:dispatch-kyc-required
 *   php artisan cue:dispatch-kyc-required --dry-run
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.6
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DispatchKycRequiredCues extends Command
{
    protected $signature = 'cue:dispatch-kyc-required
        {--dry-run : Print the candidate count without creating cues}';

    protected $description = 'Dispatch kyc_required cues for users approaching €1,000 lifetime spend (Backend-Q8 aggregate-condition cron)';

    /** AMLD5 lifetime spend threshold in cents (€1,000.00). */
    private const AMLD5_THRESHOLD_CENTS = 100_000;

    /** Lifetime occurrence window key for one active kyc_required cue per user. */
    private const OCCURRENCE_WINDOW = '1970-01-01T00:00:00Z';

    private const KIND = 'kyc_required';

    public function __construct(private readonly CueRepository $cueRepository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $startAt = microtime(true);

        // LEFT JOIN candidate query: users who crossed the AMLD5 threshold,
        // have not completed KYC, and do NOT have an active (non-dismissed)
        // kyc_required cue. Dismissed cues are not excluded — the user may
        // cross the threshold again after dismissing.
        $candidateCount = DB::table('users as u')
            ->leftJoin('cues as c', function ($join): void {
                $join->on('c.user_id', '=', 'u.id')
                    ->where('c.kind', '=', self::KIND)
                    ->whereNull('c.dismissed_at');
            })
            ->where('u.lifetime_spend_cents', '>=', self::AMLD5_THRESHOLD_CENTS)
            ->whereNull('u.kyc_completed_at')
            ->whereNull('c.id')
            ->count('u.id');

        Log::info('cue.cron.aggregate.kyc_required.candidates', [
            'count' => $candidateCount,
        ]);

        if ($candidateCount === 0) {
            $this->info('No KYC-required candidates. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf(
                'Found %d KYC-required candidate(s). Re-run without --dry-run to create cues.',
                $candidateCount,
            ));

            return self::SUCCESS;
        }

        $processed = 0;

        // Chunk via LEFT JOIN + chunkById for memory isolation.
        DB::table('users as u')
            ->select('u.id')
            ->leftJoin('cues as c', function ($join): void {
                $join->on('c.user_id', '=', 'u.id')
                    ->where('c.kind', '=', self::KIND)
                    ->whereNull('c.dismissed_at');
            })
            ->where('u.lifetime_spend_cents', '>=', self::AMLD5_THRESHOLD_CENTS)
            ->whereNull('u.kyc_completed_at')
            ->whereNull('c.id')
            ->orderBy('u.id')
            ->chunkById(1000, function ($rows) use (&$processed): void {
                foreach ($rows as $row) {
                    $user = User::find($row->id);
                    if (! $user instanceof User) {
                        continue;
                    }

                    $this->cueRepository->createIdempotent(
                        user: $user,
                        kind: self::KIND,
                        payload: [
                            'thresholdCents' => self::AMLD5_THRESHOLD_CENTS,
                            'regulation'     => 'AMLD5',
                        ],
                        occurrenceWindowStartIso8601: self::OCCURRENCE_WINDOW,
                    );

                    $processed++;
                }
            }, 'u.id', 'id');

        $duration = microtime(true) - $startAt;

        Log::info('cue.cron.aggregate.kyc_required.processed', [
            'count'    => $processed,
            'duration' => round($duration, 4),
        ]);

        $this->info(sprintf('Dispatched %d kyc_required cue(s).', $processed));

        return self::SUCCESS;
    }
}
