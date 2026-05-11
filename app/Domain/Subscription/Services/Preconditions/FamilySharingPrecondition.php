<?php

/**
 * FamilySharingPrecondition — always show family_sharing_unsupported cue.
 *
 * This cue is created by slice 2 (IAP) when family sharing is detected.
 * The precondition is intentionally permissive — the cue should always
 * render when present.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class FamilySharingPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        // Always return true — no server-side suppression for this kind.
        // Slice 2 (IAP) creates this cue when the condition is detected.
        return true;
    }
}
