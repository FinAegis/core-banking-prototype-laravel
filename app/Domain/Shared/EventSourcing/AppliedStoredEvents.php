<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Illuminate\Database\ConnectionInterface;
use LogicException;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Records which stored events a projection has already applied, so that a
 * replay or a second delivery of the same stored event does not move money
 * again.
 *
 * A projection claims the stored event inside the transaction that applies it,
 * on the connection of the rows it changes. The claim is a row keyed by the
 * projection name and the stored event id under a unique index, so it commits
 * or rolls back together with the balance change. A second claim of the same
 * event finds the row and returns false. When two deliveries run at the same
 * time, the second insert waits for the first transaction and then sees its
 * row. Keys are stored-event ids, so one projection name must only take events
 * from one stored-events table.
 */
final class AppliedStoredEvents
{
    public const TABLE = 'applied_stored_events';

    /**
     * Claim the stored event for the projection. Returns false when the
     * projection already applied it, and the caller then changes nothing.
     * An event that was never stored has no id to recognise it by, so it is
     * always applied, as before.
     */
    public static function claim(ConnectionInterface $connection, string $projection, ShouldBeStored $event): bool
    {
        $storedEventId = $event->storedEventId();

        if ($storedEventId === null) {
            return true;
        }

        self::assertInTransaction($connection);

        return $connection->table(self::TABLE)->insertOrIgnore([
            'projection'      => $projection,
            'stored_event_id' => (string) $storedEventId,
            'created_at'      => now(),
        ]) === 1;
    }

    /**
     * Give up a claim in the same transaction when the projection decided not
     * to apply the event, so a later delivery can still apply it.
     */
    public static function release(ConnectionInterface $connection, string $projection, ShouldBeStored $event): void
    {
        $storedEventId = $event->storedEventId();

        if ($storedEventId === null) {
            return;
        }

        self::assertInTransaction($connection);

        $connection->table(self::TABLE)
            ->where('projection', $projection)
            ->where('stored_event_id', (string) $storedEventId)
            ->delete();
    }

    /**
     * Forget the claims of a projection, all of them or only those of the given
     * stored events, so a rebuild of the projection applies the events again.
     * Only a projection whose rows are reset at the same time may forget its
     * claims; otherwise the next delivery books the money again.
     *
     * @param  list<int|string>|null  $storedEventIds
     */
    public static function forget(ConnectionInterface $connection, string $projection, ?array $storedEventIds = null): void
    {
        if ($storedEventIds === null) {
            $connection->table(self::TABLE)->where('projection', $projection)->delete();

            return;
        }

        foreach (array_chunk($storedEventIds, 1000) as $chunk) {
            $connection->table(self::TABLE)
                ->where('projection', $projection)
                ->whereIn('stored_event_id', array_map('strval', $chunk))
                ->delete();
        }
    }

    private static function assertInTransaction(ConnectionInterface $connection): void
    {
        if ($connection->transactionLevel() === 0) {
            throw new LogicException('A stored event must be claimed inside the transaction that applies it.');
        }
    }
}
