<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use Illuminate\Support\Str;

class ComplianceAlertService
{
    /**
     * Create a new compliance alert.
     */
    public function createAlert(
        string $type,
        string $severity,
        string $entityType,
        string $entityId,
        string $description,
        array $details = []
    ): string {
        $alertId = Str::uuid()->toString();

        $aggregate = ComplianceAlertAggregate::create(
            type: $type,
            severity: $severity,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            details: $details
        );

        $aggregate->persist();

        return $alertId;
    }

    /**
     * Assign an alert to a compliance officer.
     */
    public function assignAlert(string $alertId, string $officerId): void
    {
        $aggregate = ComplianceAlertAggregate::retrieve($alertId);
        $aggregate->assign($officerId);
        $aggregate->persist();
    }

    /**
     * Add a note to an alert.
     */
    public function addAlertNote(string $alertId, string $note, string $officerId): void
    {
        $aggregate = ComplianceAlertAggregate::retrieve($alertId);
        $aggregate->addNote($note, $officerId);
        $aggregate->persist();
    }

    /**
     * Change alert status.
     */
    public function changeAlertStatus(string $alertId, string $status): void
    {
        $aggregate = ComplianceAlertAggregate::retrieve($alertId);
        $aggregate->changeStatus($status);
        $aggregate->persist();
    }

    /**
     * Resolve an alert.
     */
    public function resolveAlert(
        string $alertId,
        string $resolution,
        string $officerId,
        string $notes
    ): void {
        $aggregate = ComplianceAlertAggregate::retrieve($alertId);
        $aggregate->resolve($resolution, $officerId, $notes);
        $aggregate->persist();
    }
}
