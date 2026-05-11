<?php

/**
 * SubscriptionTrialStarted — fired when a Stripe subscription with a trial period is created.
 *
 * Dispatched by SubscriptionWebhookController::onSubscriptionCreated() when the
 * subscription has trial_ends_at IS NOT NULL. Listened to by
 * SubscriptionTrialStartedListener which dispatches the three trial-ending delayed jobs.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5 (F-21)
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Events;

use Carbon\Carbon;

final readonly class SubscriptionTrialStarted
{
    public function __construct(
        public readonly int $userId,
        public readonly Carbon $trialStartedAt,
        public readonly Carbon $trialEndsAt,
    ) {
    }
}
