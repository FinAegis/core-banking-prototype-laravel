<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

/**
 * A projector that claims its stored events in applied_stored_events.
 *
 * A replay that resets such a projector's state (Spatie calls resetState when
 * it replays from the first event) first makes it forget the claims of the
 * events it is about to replay. Otherwise the rebuild would skip every event
 * the projector had already applied. A projector without resetState keeps its
 * claims, so a replay is a catch-up that applies only what is missing.
 */
interface ClaimsAppliedStoredEvents
{
    /**
     * Forget the claims of this projector, all of them, or only those of the
     * given stored events.
     *
     * @param  list<int|string>|null  $storedEventIds
     */
    public function forgetAppliedStoredEvents(?array $storedEventIds = null): void;
}
