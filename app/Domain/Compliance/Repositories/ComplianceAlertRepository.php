<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Shared\Events\DomainEvent;
use Illuminate\Support\Facades\DB;

class ComplianceAlertRepository
{
    public function save(ComplianceAlertAggregate $aggregate): void
    {
        $events = $aggregate->releaseEvents();

        DB::transaction(function () use ($events, $aggregate) {
            foreach ($events as $event) {
                $this->persistEvent($event);
                $this->updateProjection($event);
            }
        });

        $aggregate->markEventsAsCommitted();
    }

    public function find(string $alertId): ?ComplianceAlertAggregate
    {
        $events = DB::table('compliance_events')
            ->where('aggregate_id', $alertId)
            ->where('aggregate_type', 'ComplianceAlert')
            ->orderBy('event_version')
            ->get()
            ->map(function ($row) {
                return $this->deserializeEvent($row);
            })
            ->toArray();

        if (empty($events)) {
            return null;
        }

        return ComplianceAlertAggregate::reconstituteFromEvents($events);
    }

    private function persistEvent(DomainEvent $event): void
    {
        DB::table('compliance_events')->insert([
            'aggregate_id'   => $event->getAggregateId(),
            'aggregate_type' => $event->getAggregateType(),
            'event_type'     => $event->getEventType(),
            'event_version'  => $this->getNextVersion($event->getAggregateId()),
            'payload'        => json_encode($event->toArray()),
            'metadata'       => json_encode($event->metadata),
            'correlation_id' => $event->correlationId,
            'causation_id'   => $event->causationId,
            'user_id'        => auth()->id(),
            'occurred_at'    => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function updateProjection(DomainEvent $event): void
    {
        $eventType = $event->getEventType();
        $data = $event->toArray()['payload'];

        switch ($eventType) {
            case 'AlertCreated':
                ComplianceAlert::create([
                    'id'          => $event->getAggregateId(),
                    'type'        => $data['type'],
                    'severity'    => $data['severity'],
                    'status'      => 'open',
                    'entity_type' => $data['entity_type'],
                    'entity_id'   => $data['entity_id'],
                    'description' => $data['description'],
                    'details'     => $data['details'],
                    'created_by'  => auth()->id(),
                ]);
                break;

            case 'AlertAssigned':
                ComplianceAlert::where('id', $event->getAggregateId())
                    ->update([
                        'assigned_to' => $data['assigned_to'],
                        'assigned_at' => now(),
                    ]);
                break;

            case 'AlertStatusChanged':
                ComplianceAlert::where('id', $event->getAggregateId())
                    ->update([
                        'status' => $data['new_status'],
                    ]);
                break;

            case 'AlertResolved':
                ComplianceAlert::where('id', $event->getAggregateId())
                    ->update([
                        'status'      => 'closed',
                        'resolution'  => $data['resolution'],
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ]);
                break;

            case 'AlertEscalatedToCase':
                ComplianceAlert::where('id', $event->getAggregateId())
                    ->update([
                        'status'  => 'escalated',
                        'case_id' => $data['case_id'],
                    ]);
                break;
        }
    }

    private function getNextVersion(string $aggregateId): int
    {
        $lastVersion = DB::table('compliance_events')
            ->where('aggregate_id', $aggregateId)
            ->max('event_version');

        return ($lastVersion ?? 0) + 1;
    }

    private function deserializeEvent($row): DomainEvent
    {
        $payload = json_decode($row->payload, true);
        $eventClass = 'App\\Domain\\Compliance\\Events\\' . $row->event_type;

        // Reconstruct the event from stored data
        // This is simplified - in production you'd have proper deserialization
        return new $eventClass(...array_values($payload['payload']));
    }

    public function findByStatus(string $status): array
    {
        return ComplianceAlert::where('status', $status)
            ->get()
            ->map(function ($alert) {
                return $this->find($alert->id);
            })
            ->filter()
            ->toArray();
    }

    public function search(array $criteria): array
    {
        $query = ComplianceAlert::query();

        if (isset($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (isset($criteria['severity'])) {
            $query->where('severity', $criteria['severity']);
        }

        if (isset($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (isset($criteria['entity_type'])) {
            $query->where('entity_type', $criteria['entity_type']);
        }

        if (isset($criteria['search'])) {
            $query->where('description', 'like', '%' . $criteria['search'] . '%');
        }

        return $query->get()->toArray();
    }
}
