<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Repositories;

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use Illuminate\Support\Facades\DB;

class MonitoringAggregateRepository
{
    protected string $aggregateRootClass = MonitoringAggregate::class;

    protected string $storedEventTable = 'monitoring_events';

    protected string $snapshotTable = 'monitoring_snapshots';

    /**
     * Find a monitoring aggregate by session ID.
     */
    public function findBySessionId(string $sessionId): ?MonitoringAggregate
    {
        $event = DB::table($this->storedEventTable)
            ->where('event_class', 'App\Domain\Monitoring\Events\MonitoringSessionStartedEvent')
            ->whereRaw("JSON_EXTRACT(event_properties, '$.sessionId') = ?", [$sessionId])
            ->first();

        if (! $event) {
            return null;
        }

        return MonitoringAggregate::retrieve($event->aggregate_uuid);
    }

    /**
     * Store an aggregate.
     */
    public function store(MonitoringAggregate $aggregate): void
    {
        $aggregate->persist();
    }
}
