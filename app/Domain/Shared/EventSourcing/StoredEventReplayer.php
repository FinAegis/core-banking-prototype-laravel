<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\EventSourcing\EventHandlers\EventHandler;
use Spatie\EventSourcing\EventHandlers\EventHandlerCollection;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEventQueryBuilder;

/**
 * Replays events from the stored_events table through projectors.
 *
 * A full replay, of every event or of one aggregate's events, goes through
 * Spatie's replay, which resets each projector that has resetState. Before
 * that, such a projector forgets its claims on the events being replayed, so
 * its rebuild applies them again. A projector without resetState keeps its
 * claims and only applies what it has not applied yet.
 *
 * A filtered replay (event type or dates) replays only the matching events and
 * resets nothing, because the other events would not be replayed.
 *
 * No replay opens a transaction of its own. Each projector applies an event in
 * its own transaction on its own connection.
 */
final class StoredEventReplayer
{
    public function __construct(
        private readonly Projectionist $projectionist,
    ) {
    }

    /**
     * @param  Collection<array-key, covariant Projector>  $projectors
     */
    public function replay(Collection $projectors, ?string $aggregateUuid = null): void
    {
        $this->forgetClaimsOfResetProjectors($projectors, $aggregateUuid);

        $this->projectionist->replay($projectors, aggregateUuid: $aggregateUuid);
    }

    /**
     * Replay the matching stored events in the order they were stored.
     *
     * @param  Collection<array-key, covariant Projector>  $projectors
     * @param  list<string>|null  $eventClasses  null for every event the projectors handle
     * @return int the number of stored events replayed
     */
    public function replayMatching(
        Collection $projectors,
        ?array $eventClasses,
        ?string $aggregateUuid = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): int {
        $handlers = new EventHandlerCollection($projectors);
        $replayed = 0;

        $handlers->call('onStartingEventReplay');

        $this->matching($projectors, $eventClasses, $aggregateUuid, $from, $to)
            ->lazyById(1000)
            ->each(function (EloquentStoredEvent $model) use ($handlers, &$replayed): void {
                $storedEvent = $model->toStoredEvent();

                $handlers->forEvent($storedEvent)
                    ->sortBy(fn (EventHandler $handler): int => $handler->getWeight($storedEvent))
                    ->each(fn (EventHandler $handler) => $handler->handle($storedEvent));

                $replayed++;
            });

        $handlers->call('onFinishedEventReplay');

        return $replayed;
    }

    /**
     * @param  Collection<array-key, covariant Projector>  $projectors
     * @param  list<string>|null  $eventClasses  null for every event the projectors handle
     */
    public function countMatching(
        Collection $projectors,
        ?array $eventClasses,
        ?string $aggregateUuid = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): int {
        return $this->matching($projectors, $eventClasses, $aggregateUuid, $from, $to)->count();
    }

    /**
     * The event classes the projectors handle whose class name, short class
     * name or stored alias is $eventType.
     *
     * @param  Collection<array-key, covariant Projector>  $projectors
     * @return list<string>
     */
    public function eventClassesNamed(Collection $projectors, string $eventType): array
    {
        /** @var array<string, string> $aliases */
        $aliases = (array) config('event-sourcing.event_class_map', []);

        return array_values(array_filter(
            $this->handledEventClasses($projectors),
            fn (string $class): bool => $class === $eventType
                || class_basename($class) === $eventType
                || array_search($class, $aliases, true) === $eventType,
        ));
    }

    /**
     * @param  Collection<array-key, covariant Projector>  $projectors
     * @param  list<string>|null  $eventClasses
     * @return EloquentStoredEventQueryBuilder<EloquentStoredEvent>
     */
    private function matching(
        Collection $projectors,
        ?array $eventClasses,
        ?string $aggregateUuid,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): EloquentStoredEventQueryBuilder {
        $eventClasses ??= $this->handledEventClasses($projectors);

        /** @var EloquentStoredEventQueryBuilder<EloquentStoredEvent> $query */
        $query = EloquentStoredEvent::query();

        if ($eventClasses === []) {
            // Nothing the projectors handle can match.
            $query->whereRaw('1 = 0');

            return $query;
        }

        $query->whereEvent(...$eventClasses);

        if ($aggregateUuid !== null) {
            $query->whereAggregateRoot($aggregateUuid);
        }

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  Collection<array-key, covariant Projector>  $projectors
     * @return list<string>
     */
    private function handledEventClasses(Collection $projectors): array
    {
        // toBase(): Spatie's EventHandlerCollection only holds handlers.
        return array_values($projectors->toBase()
            ->flatMap(fn (Projector $projector): array => $projector->getEventHandlingMethods()->keys()->all())
            ->filter(fn (mixed $class): bool => is_string($class) && class_exists($class))
            ->unique()
            ->all());
    }

    /**
     * @param  Collection<array-key, covariant Projector>  $projectors
     */
    private function forgetClaimsOfResetProjectors(Collection $projectors, ?string $aggregateUuid): void
    {
        $resetting = $projectors->toBase()->filter(
            fn (Projector $projector): bool => $projector instanceof ClaimsAppliedStoredEvents && method_exists($projector, 'resetState'),
        );

        if ($resetting->isEmpty()) {
            return;
        }

        $storedEventIds = null;

        if ($aggregateUuid !== null) {
            /** @var list<int|string> $storedEventIds */
            $storedEventIds = EloquentStoredEvent::query()->whereAggregateRoot($aggregateUuid)->pluck('id')->all();
        }

        $resetting->each(function (Projector $projector) use ($storedEventIds): void {
            if ($projector instanceof ClaimsAppliedStoredEvents) {
                $projector->forgetAppliedStoredEvents($storedEventIds);
            }
        });
    }
}
