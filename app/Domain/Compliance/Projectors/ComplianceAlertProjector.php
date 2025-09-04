<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Projectors;

use App\Domain\Compliance\Events\AlertAssigned;
use App\Domain\Compliance\Events\AlertCreated;
use App\Domain\Compliance\Events\AlertEscalatedToCase;
use App\Domain\Compliance\Events\AlertLinked;
use App\Domain\Compliance\Events\AlertNoteAdded;
use App\Domain\Compliance\Events\AlertResolved;
use App\Domain\Compliance\Events\AlertStatusChanged;
use App\Domain\Compliance\Models\ComplianceAlert;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class ComplianceAlertProjector extends Projector
{
    public function onAlertCreated(AlertCreated $event): void
    {
        ComplianceAlert::create([
            'id'          => $event->alertId,
            'alert_id'    => $this->generateAlertId($event->type),
            'type'        => $event->type,
            'severity'    => $event->severity,
            'status'      => 'open',
            'entity_type' => $event->entityType,
            'entity_id'   => $event->entityId,
            'description' => $event->description,
            'metadata'    => $event->details,  // Store details in metadata field
            'details'     => $event->details,  // Also store in details field for compatibility
            'detected_at' => $event->occurredAt ?? now(),  // Add detected_at field
            'user_id'     => $event->metadata['user_id'] ?? null,
            'title'       => $this->generateTitle($event->type, $event->severity),
            'created_at'  => $event->occurredAt ?? now(),
            'updated_at'  => $event->occurredAt ?? now(),
        ]);
    }

    private function generateAlertId(string $type): string
    {
        $prefix = match ($type) {
            'transaction' => 'TXN',
            'pattern'     => 'PTN',
            'account'     => 'ACC',
            'user'        => 'USR',
            default       => 'ALT',
        };

        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    private function generateTitle(string $type, string $severity): string
    {
        $typeFormatted = ucfirst(str_replace('_', ' ', $type));
        $severityFormatted = ucfirst($severity);

        return "{$severityFormatted} {$typeFormatted} Alert";
    }

    public function onAlertAssigned(AlertAssigned $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'assigned_to' => $event->assignedTo,
                'assigned_by' => $event->assignedBy,
                'assigned_at' => $event->assignedAt ?? now(),
                'updated_at'  => $event->assignedAt ?? now(),
            ]);

            // Store notes in investigation_notes array field if provided
            if (isset($event->metadata['notes']) && $event->metadata['notes']) {
                $notes = $alert->investigation_notes ?? [];
                $notes[] = [
                    'content'    => $event->metadata['notes'],
                    'created_by' => $event->assignedBy,
                    'created_at' => ($event->assignedAt ?? now())->format('c'),
                ];
                $alert->update(['investigation_notes' => $notes]);
            }
        }
    }

    public function onAlertStatusChanged(AlertStatusChanged $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            // Store status history in the history array field
            $history = $alert->history ?? [];
            $history[] = [
                'type'        => 'status_change',
                'from_status' => $event->previousStatus,
                'to_status'   => $event->newStatus,
                'reason'      => $event->metadata['reason'] ?? null,
                'changed_by'  => $event->changedBy,
                'changed_at'  => $event->changedAt->format('c'),
            ];

            $alert->update([
                'status'            => $event->newStatus,
                'status_changed_by' => $event->changedBy,
                'status_changed_at' => $event->changedAt,
                'history'           => $history,
                'updated_at'        => now(),
            ]);
        }
    }

    public function onAlertNoteAdded(AlertNoteAdded $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            // Store notes in investigation_notes array field
            $notes = $alert->investigation_notes ?? [];
            $notes[] = [
                'note'        => $event->note,  // Changed from 'content' to 'note'
                'user_id'     => $event->addedBy,  // Changed from 'created_by' to 'user_id'
                'attachments' => $event->attachments,
                'created_at'  => ($event->occurredAt ?? now())->format('c'),
            ];

            $alert->update([
                'investigation_notes' => $notes,
                'updated_at'          => $event->occurredAt ?? now(),
            ]);
        }
    }

    public function onAlertResolved(AlertResolved $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'status'           => 'resolved',
                'resolution_notes' => $event->resolution,
                'resolved_by'      => $event->resolvedBy,
                'resolved_at'      => $event->occurredAt ?? now(),
                'updated_at'       => $event->occurredAt ?? now(),
            ]);

            // Add resolution notes to investigation_notes if provided
            if ($event->notes) {
                $notes = $alert->investigation_notes ?? [];
                $notes[] = [
                    'type'       => 'resolution',
                    'content'    => $event->notes,
                    'created_by' => $event->resolvedBy,
                    'created_at' => ($event->occurredAt ?? now())->format('c'),
                ];
                $alert->update(['investigation_notes' => $notes]);
            }
        }
    }

    public function onAlertLinked(AlertLinked $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            // Store linked alerts in the linked_alerts array field
            $linkedAlerts = $alert->linked_alerts ?? [];
            foreach ($event->linkedAlertIds as $linkedAlertId) {
                $linkedAlerts[] = [
                    'alert_id'  => $linkedAlertId,
                    'link_type' => $event->linkType,
                    'linked_by' => $event->linkedBy,
                    'linked_at' => ($event->linkedAt ?? now())->format('c'),
                ];
            }

            $alert->update([
                'linked_alerts' => $linkedAlerts,
                'updated_at'    => $event->linkedAt ?? now(),
            ]);
        }
    }

    public function onAlertEscalatedToCase(AlertEscalatedToCase $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            // Store escalation history in history array
            $history = $alert->history ?? [];
            $history[] = [
                'type'         => 'escalation',
                'case_id'      => $event->caseId,
                'escalated_by' => $event->escalatedBy,
                'reason'       => $event->reason,
                'escalated_at' => ($event->occurredAt ?? now())->format('c'),
            ];

            $alert->update([
                'status'            => 'escalated',
                'case_id'           => $event->caseId,
                'escalated_at'      => $event->occurredAt ?? now(),
                'escalation_reason' => $event->reason,
                'history'           => $history,
                'updated_at'        => $event->occurredAt ?? now(),
            ]);
        }
    }
}
