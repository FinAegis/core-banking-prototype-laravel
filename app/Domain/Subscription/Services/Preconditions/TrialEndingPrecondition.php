<?php

/**
 * TrialEndingPrecondition — suppress trial_ending_* cues if user has converted.
 *
 * A user who upgraded to paid mid-trial no longer needs the "trial ending" nudge.
 * Checks Cashier subscription in-memory (subscription was eager-loaded by caller).
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class TrialEndingPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            // No subscription at all — trial must have been cancelled.
            return false;
        }

        // On trial = still need the nudge. Valid but not-on-trial = converted.
        return $subscription->onTrial();
    }
}
