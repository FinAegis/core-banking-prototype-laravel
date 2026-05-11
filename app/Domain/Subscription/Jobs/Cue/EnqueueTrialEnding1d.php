<?php

/**
 * EnqueueTrialEnding1d — dispatched 1 day before trial end.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Jobs\Cue;

use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EnqueueTrialEnding1d implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @inheritDoc */
    public int $tries = 5;

    /** @inheritDoc */
    public int $backoff = 30;

    private const KIND = 'trial_ending_1d';

    public function __construct(
        public readonly int $userId,
        public readonly Carbon $trialEndsAt,
        public readonly Carbon $trialStartedAt,
    ) {
    }

    public function handle(CueRepository $cueRepository): void
    {
        $startAt = microtime(true);

        $user = User::find($this->userId);

        if ($user === null) {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'user_erased', 'user_id' => $this->userId]);

            return;
        }

        $subscription = $user->subscription('default');
        if ($subscription === null || (! $subscription->onTrial())) {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'tier_changed', 'user_id' => $this->userId]);

            return;
        }

        $cueRepository->createIdempotent(
            user: $user,
            kind: self::KIND,
            payload: ['trialEndsAt' => $this->trialEndsAt->toIso8601ZuluString()],
            occurrenceWindowStartIso8601: $this->trialStartedAt->toIso8601ZuluString(),
        );

        $duration = microtime(true) - $startAt;

        Log::info('cue.job.success', ['kind' => self::KIND, 'user_id' => $this->userId, 'duration' => round($duration, 4)]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('cue.job.failed', [
            'kind'    => self::KIND,
            'user_id' => $this->userId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
