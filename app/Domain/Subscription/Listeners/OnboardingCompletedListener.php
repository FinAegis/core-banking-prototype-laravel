<?php

/**
 * OnboardingCompletedListener — dispatches EnqueueProTrialReminderD1 after 24h.
 *
 * Bound to App\Domain\Subscription\Events\OnboardingCompleted in AppServiceProvider.
 * Uses ->delay(now()->addDay()) so the cue fires the next day.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Listeners;

use App\Domain\Subscription\Events\OnboardingCompleted;
use App\Domain\Subscription\Jobs\Cue\EnqueueProTrialReminderD1;
use Illuminate\Support\Facades\Log;

final class OnboardingCompletedListener
{
    public function handle(OnboardingCompleted $event): void
    {
        EnqueueProTrialReminderD1::dispatch($event->userId)
            ->delay(now()->addDay());

        Log::info('cue.job.dispatched', [
            'kind'    => 'pro_trial_reminder_d1',
            'user_id' => $event->userId,
            'delay'   => '24h',
        ]);
    }
}
