<?php

/**
 * SubscriptionTrialStartedListener — dispatches three trial-ending delayed jobs.
 *
 * Bound to App\Domain\Subscription\Events\SubscriptionTrialStarted in AppServiceProvider.
 * Dispatches:
 *   - EnqueueTrialEnding2d at $trialEndsAt->subDays(2)
 *   - EnqueueTrialEnding1d at $trialEndsAt->subDays(1)
 *   - EnqueueTrialEnding1h at $trialEndsAt->subHour()
 *
 * Self-cancel pattern: if user converts mid-trial, jobs self-cancel in their
 * handle() via the onTrial() check. No Bus::findBatch() cancellation needed.
 *
 * Note: the customer.subscription.trial_will_end webhook also dispatches these
 * jobs (see SubscriptionWebhookController). The uniq_cues_idempotency constraint
 * prevents double cue creation if both paths fire.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Listeners;

use App\Domain\Subscription\Events\SubscriptionTrialStarted;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1d;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding1h;
use App\Domain\Subscription\Jobs\Cue\EnqueueTrialEnding2d;
use Illuminate\Support\Facades\Log;

final class SubscriptionTrialStartedListener
{
    public function handle(SubscriptionTrialStarted $event): void
    {
        $userId = $event->userId;
        $trialEndsAt = $event->trialEndsAt;
        $trialStartedAt = $event->trialStartedAt;

        // Dispatch all three delayed jobs. Each fires at the appropriate offset
        // before trial end and self-cancels if the user has already converted.
        EnqueueTrialEnding2d::dispatch($userId, $trialEndsAt, $trialStartedAt)
            ->delay($trialEndsAt->copy()->subDays(2));

        EnqueueTrialEnding1d::dispatch($userId, $trialEndsAt, $trialStartedAt)
            ->delay($trialEndsAt->copy()->subDay());

        EnqueueTrialEnding1h::dispatch($userId, $trialEndsAt, $trialStartedAt)
            ->delay($trialEndsAt->copy()->subHour());

        Log::info('cue.job.dispatched', [
            'kind'          => 'trial_ending_*',
            'user_id'       => $userId,
            'trial_ends_at' => $trialEndsAt->toIso8601String(),
        ]);
    }
}
