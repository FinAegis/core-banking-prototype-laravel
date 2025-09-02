<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Aggregates\TransactionMonitoringAggregate;
use App\Domain\Shared\Events\DomainEvent;
use Illuminate\Support\Facades\DB;

class TransactionMonitoringRepository
{
    public function save(TransactionMonitoringAggregate $aggregate): void
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

    public function find(string $transactionId): ?TransactionMonitoringAggregate
    {
        $events = DB::table('monitoring_events')
            ->where('aggregate_id', $transactionId)
            ->where('aggregate_type', 'TransactionMonitoring')
            ->orderBy('event_version')
            ->get()
            ->map(function ($row) {
                return $this->deserializeEvent($row);
            })
            ->toArray();

        if (empty($events)) {
            return null;
        }

        return TransactionMonitoringAggregate::reconstituteFromEvents($events);
    }

    private function persistEvent(DomainEvent $event): void
    {
        DB::table('monitoring_events')->insert([
            'aggregate_id' => $event->getAggregateId(),
            'aggregate_type' => $event->getAggregateType(),
            'event_type' => $event->getEventType(),
            'event_version' => $this->getNextVersion($event->getAggregateId()),
            'payload' => json_encode($event->toArray()),
            'metadata' => json_encode($event->metadata),
            'correlation_id' => $event->correlationId,
            'causation_id' => $event->causationId,
            'user_id' => auth()->id(),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateProjection(DomainEvent $event): void
    {
        $eventType = $event->getEventType();
        $data = $event->toArray()['payload'];
        
        switch ($eventType) {
            case 'RiskScoreCalculated':
                Transaction::where('id', $event->getAggregateId())
                    ->update([
                        'risk_score' => $data['risk_score'],
                        'risk_level' => $data['risk_level'],
                        'compliance_status' => 'analyzed',
                    ]);
                break;
                
            case 'TransactionFlagged':
                Transaction::where('id', $event->getAggregateId())
                    ->update([
                        'compliance_status' => 'flagged',
                        'risk_level' => $data['severity'],
                        'risk_score' => $data['risk_score'],
                        'flagged_at' => now(),
                        'flagged_by' => auth()->id(),
                        'flag_reason' => $data['reason'],
                        'patterns_detected' => json_encode($data['patterns']),
                    ]);
                break;
                
            case 'TransactionCleared':
                Transaction::where('id', $event->getAggregateId())
                    ->update([
                        'compliance_status' => 'cleared',
                        'risk_level' => 'low',
                        'cleared_at' => now(),
                        'cleared_by' => auth()->id(),
                        'clear_reason' => $data['reason'],
                    ]);
                break;
                
            case 'PatternDetected':
                $transaction = Transaction::find($event->getAggregateId());
                if ($transaction) {
                    $patterns = json_decode($transaction->patterns_detected ?? '[]', true);
                    $patterns[] = [
                        'type' => $data['pattern_type'],
                        'confidence' => $data['confidence'],
                        'detected_at' => now(),
                    ];
                    $transaction->update([
                        'patterns_detected' => json_encode($patterns),
                    ]);
                }
                break;
                
            case 'MonitoringRuleTriggered':
                // Store rule trigger in a separate tracking table or in transaction metadata
                $transaction = Transaction::find($event->getAggregateId());
                if ($transaction) {
                    $metadata = json_decode($transaction->metadata ?? '{}', true);
                    $metadata['triggered_rules'] = $metadata['triggered_rules'] ?? [];
                    $metadata['triggered_rules'][] = [
                        'rule_id' => $data['rule_id'],
                        'rule_name' => $data['rule_name'],
                        'severity' => $data['severity'],
                        'triggered_at' => now(),
                    ];
                    $transaction->update(['metadata' => json_encode($metadata)]);
                }
                break;
        }
    }

    private function getNextVersion(string $aggregateId): int
    {
        $lastVersion = DB::table('monitoring_events')
            ->where('aggregate_id', $aggregateId)
            ->max('event_version');
            
        return ($lastVersion ?? 0) + 1;
    }

    private function deserializeEvent($row): DomainEvent
    {
        $payload = json_decode($row->payload, true);
        $eventClass = 'App\\Domain\\Compliance\\Events\\' . $row->event_type;
        
        // Reconstruct the event from stored data
        return new $eventClass(...array_values($payload['payload']));
    }

    public function findByStatus(string $status): array
    {
        return Transaction::where('compliance_status', $status)
            ->get()
            ->map(function ($transaction) {
                return $this->find($transaction->id);
            })
            ->filter()
            ->toArray();
    }

    public function findFlagged(): array
    {
        return $this->findByStatus('flagged');
    }

    public function findHighRisk(): array
    {
        return Transaction::whereIn('risk_level', ['high', 'critical'])
            ->get()
            ->map(function ($transaction) {
                return $this->find($transaction->id);
            })
            ->filter()
            ->toArray();
    }
}
