<?php

/**
 * ExportReadyPrecondition — always show export_ready cue.
 *
 * The cue is created when an export job completes. No server-side suppression
 * needed — precondition reaping is not applicable to this kind.
 *
 * Note: export_ready cue creation is deferred (not in slice 4 scope).
 * This precondition is registered so the CueKindRegistry is complete.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services\Preconditions;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Models\User;

final class ExportReadyPrecondition implements CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool
    {
        return true;
    }
}
