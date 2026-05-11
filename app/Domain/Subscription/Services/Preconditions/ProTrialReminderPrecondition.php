<?php

/**
 * ProTrialReminderPrecondition — suppress pro_trial_reminder_d1 if user opted out
 * or has already subscribed.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class ProTrialReminderPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        // Opt-out flag set — suppress.
        // @phpstan-ignore-next-line property.nonObject — column added by migration
        if ((bool) ($user->pro_marketing_opt_out ?? false)) {
            return false;
        }

        $subscription = $user->subscription('default');

        // User already has an active subscription — no need for the reminder.
        if ($subscription !== null && $subscription->valid()) {
            return false;
        }

        return true;
    }
}
