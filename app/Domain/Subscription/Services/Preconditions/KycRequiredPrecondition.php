<?php

/**
 * KycRequiredPrecondition — suppress kyc_required cue if user completed KYC.
 *
 * Checks users.kyc_completed_at (added in slice 4 migration). If set, the user
 * has completed KYC and no longer needs the reminder.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class KycRequiredPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        // @phpstan-ignore-next-line property.nonObject — column added by migration
        return $user->kyc_completed_at === null;
    }
}
