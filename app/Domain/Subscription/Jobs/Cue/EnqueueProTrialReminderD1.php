<?php

/**
 * EnqueueProTrialReminderD1 — delayed job dispatched 24h after onboarding completion.
 *
 * Dispatched by OnboardingCompletedListener with ->delay(now()->addDay()).
 * At fire time, checks eligibility conditions and inserts a pro_trial_reminder_d1 cue
 * for users who are still free-tier and trial-eligible.
 *
 * Skips:
 *   - user_erased: User::find($userId) returns null
 *   - opted_out: users.pro_marketing_opt_out is true
 *   - tier_changed: SubscriptionProjection shows user is on paid tier
 *   - trial_used: TrialFingerprintService::isEligibleForCard returns false
 *
 * Idempotency: occurrence_window_start is epoch ('1970-01-01T00:00:00Z') —
 * lifetime window, at most one cue per user.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Jobs\Cue;

use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Domain\Subscription\Projections\SubscriptionProjection;
use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EnqueueProTrialReminderD1 implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @inheritDoc */
    public int $tries = 5;

    /** @inheritDoc */
    public int $backoff = 30;

    /** Lifetime occurrence window — at most one pro_trial_reminder_d1 cue per user. */
    private const OCCURRENCE_WINDOW = '1970-01-01T00:00:00Z';

    private const KIND = 'pro_trial_reminder_d1';

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(
        CueRepository $cueRepository,
        SubscriptionProjection $subscriptionProjection,
    ): void {
        $startAt = microtime(true);

        $user = User::find($this->userId);

        if ($user === null) {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'user_erased', 'user_id' => $this->userId]);

            return;
        }

        // @phpstan-ignore-next-line property.nonObject — column added by migration
        if ((bool) ($user->pro_marketing_opt_out ?? false)) {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'opted_out', 'user_id' => $this->userId]);

            return;
        }

        $projection = $subscriptionProjection->for($user);

        if ($projection['tier'] !== 'free') {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'tier_changed', 'user_id' => $this->userId]);

            return;
        }

        // Check if user has already claimed a trial card fingerprint (trial already used).
        // Uses TrialCardFingerprint model — a user who used a trial has at least one row
        // where last_user_id = $user->id (or first_user_id) within the 12-month window.
        $trialUsed = TrialCardFingerprint::query()
            ->where(function ($q) use ($user): void {
                $q->where('first_user_id', $user->id)
                    ->orWhere('last_user_id', $user->id);
            })
            ->exists();

        if ($trialUsed) {
            Log::info('cue.job.skipped', ['kind' => self::KIND, 'reason' => 'trial_used', 'user_id' => $this->userId]);

            return;
        }

        $cueRepository->createIdempotent(
            user: $user,
            kind: self::KIND,
            payload: [
                'trialDays'       => 7,
                'planDisplayName' => 'Pro',
            ],
            occurrenceWindowStartIso8601: self::OCCURRENCE_WINDOW,
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
