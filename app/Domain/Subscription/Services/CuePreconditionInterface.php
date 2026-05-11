<?php

/**
 * CuePreconditionInterface — server-side reaping gate for the pending-cues endpoint.
 *
 * Called per-cue during GET /api/v1/me/pending-cues. Returning false suppresses
 * the cue from the mobile client response without dismissing it — the cue stays
 * in the table and may become visible again if conditions change.
 *
 * Implementations must be cheap (no external HTTP; no N+1 queries — caller
 * must have already eager-loaded subscription state).
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Models\Cue;
use App\Models\User;

interface CuePreconditionInterface
{
    public function isMet(User $user, Cue $cue): bool;
}
