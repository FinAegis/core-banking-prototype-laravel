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
            'type'        => $event->type,
            'severity'    => $event->severity,
            'status'      => 'open',
            'entity_type' => $event->entityType,
            'entity_id'   => $event->entityId,
            'description' => $event->description,
            'details'     => $event->details,
            'created_by'  => $event->userId,
            'created_at'  => $event->occurredAt ?? now(),
            'updated_at'  => $event->occurredAt ?? now(),
        ]);
    }

    public function onAlertAssigned(AlertAssigned $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'assigned_to' => $event->assignedTo,
                'assigned_by' => $event->assignedBy,
                'assigned_at' => $event->occurredAt ?? now(),
                'updated_at'  => $event->occurredAt ?? now(),
            ]);

            if ($event->notes) {
                $alert->notes()->create([
                    'content'    => $event->notes,
                    'created_by' => $event->assignedBy,
                    'created_at' => $event->occurredAt ?? now(),
                ]);
            }
        }
    }

    public function onAlertStatusChanged(AlertStatusChanged $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'status'     => $event->newStatus,
                'updated_at' => $event->occurredAt ?? now(),
            ]);

            // Log status change
            $alert->statusHistory()->create([
                'from_status' => $event->oldStatus,
                'to_status'   => $event->newStatus,
                'reason'      => $event->reason,
                'changed_by'  => $event->userId,
                'changed_at'  => $event->occurredAt ?? now(),
            ]);
        }
    }

    public function onAlertNoteAdded(AlertNoteAdded $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->notes()->create([
                'content'     => $event->note,
                'created_by'  => $event->addedBy,
                'attachments' => $event->attachments,
                'created_at'  => $event->occurredAt ?? now(),
            ]);

            $alert->touch();
        }
    }

    public function onAlertResolved(AlertResolved $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'status'      => 'closed',
                'resolution'  => $event->resolution,
                'resolved_by' => $event->resolvedBy,
                'resolved_at' => $event->occurredAt ?? now(),
                'updated_at'  => $event->occurredAt ?? now(),
            ]);

            if ($event->notes) {
                $alert->notes()->create([
                    'content'    => $event->notes,
                    'created_by' => $event->resolvedBy,
                    'created_at' => $event->occurredAt ?? now(),
                ]);
            }
        }
    }

    public function onAlertLinked(AlertLinked $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            foreach ($event->linkedAlertIds as $linkedAlertId) {
                $alert->linkedAlerts()->attach($linkedAlertId, [
                    'link_type' => $event->linkType,
                    'linked_by' => $event->userId,
                    'linked_at' => $event->occurredAt ?? now(),
                ]);
            }

            $alert->touch();
        }
    }

    public function onAlertEscalatedToCase(AlertEscalatedToCase $event): void
    {
        $alert = ComplianceAlert::find($event->alertId);
        if ($alert) {
            $alert->update([
                'status'            => 'escalated',
                'case_id'           => $event->caseId,
                'escalated_by'      => $event->escalatedBy,
                'escalated_at'      => $event->occurredAt ?? now(),
                'escalation_reason' => $event->reason,
                'updated_at'        => $event->occurredAt ?? now(),
            ]);
        }
    }
}
