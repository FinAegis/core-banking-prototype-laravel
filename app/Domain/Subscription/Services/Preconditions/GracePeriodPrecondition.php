<?php

/**
 * GracePeriodPrecondition — suppress grace_period_started if subscription recovered.
 *
 * Stripe Apple retry window is ~16 days. If the subscription transitions back to
 * active before the user dismisses the cue, suppress it.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class GracePeriodPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            return false;
        }

        // Subscription is valid (recovered from grace period) — suppress.
        return ! $subscription->valid();
    }
}
