<?php

/**
 * OnboardingCompleted — fired when a user completes onboarding.
 *
 * Dispatched by OnboardingController::complete() and ::skip() (both paths
 * signal onboarding completion for cue dispatch purposes). Listened to by
 * OnboardingCompletedListener which dispatches EnqueueProTrialReminderD1.
 *
 * Note: there is no separate App\Domain\Onboarding domain; the Onboarding
 * controller lives in App\Http\Controllers. This event is placed under the
 * Subscription domain namespace as the closest owner of the pro-trial cue
 * lifecycle (per spec §5.5 F-21 guidance: use Subscription domain if no
 * Onboarding domain exists).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.5 (F-21)
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Events;

final readonly class OnboardingCompleted
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
