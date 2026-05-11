<?php

/**
 * PaymentFailedPrecondition — suppress payment_failed cue only when subscription is valid.
 *
 * When invoice.payment_succeeded fires, the cue is dismissed via dismissed_at — that is
 * the primary resolution path. This precondition provides an additional safety valve:
 * if the subscription is currently valid (payment recovered and subscription reactivated),
 * suppress the cue.
 *
 * If no subscription exists at all (it may have been deleted after the payment failure),
 * the cue should still be shown — the user needs to take action.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class PaymentFailedPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        $subscription = $user->subscription('default');

        // No subscription found — the payment failure may have already caused termination.
        // Show the cue so the user can take action (re-subscribe, contact support).
        if ($subscription === null) {
            return true;
        }

        // Suppress if subscription is fully valid (payment recovered and renewed).
        return ! $subscription->valid();
    }
}
