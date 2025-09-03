<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;
use App\Domain\Compliance\Models\ComplianceAlert;
use Exception;
use Illuminate\Support\Collection;

class ComplianceAlertRepository
{
    public function save(ComplianceAlertAggregate $aggregate): void
    {
        // Spatie Event Sourcing handles this automatically when calling persist()
        $aggregate->persist();
    }

    public function find(string $alertId): ?ComplianceAlertAggregate
    {
        // Use Spatie's retrieve method to get the aggregate with all its events
        $aggregate = ComplianceAlertAggregate::retrieve($alertId);

        // Check if the aggregate actually exists (has events)
        // This is a workaround since Spatie always returns an aggregate instance
        try {
            // Check if the aggregate has been initialized (has a status)
            if (empty($aggregate->getStatus())) {
                return null;
            }
        } catch (Exception $e) {
            return null;
        }

        return $aggregate;
    }

    public function findByStatus(string $status): Collection
    {
        // Get alerts from the projection/read model
        return ComplianceAlert::where('status', $status)
            ->get()
            ->map(function ($alert) {
                // Return the projection model, not the aggregate
                // Aggregates should only be loaded when needed for state changes
                return $alert;
            });
    }

    public function search(array $criteria): Collection
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

        if (isset($criteria['entity_id'])) {
            $query->where('entity_id', $criteria['entity_id']);
        }

        if (isset($criteria['assigned_to'])) {
            $query->where('assigned_to', $criteria['assigned_to']);
        }

        if (isset($criteria['search'])) {
            $searchTerm = $criteria['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', '%' . $searchTerm . '%')
                  ->orWhere('id', 'like', '%' . $searchTerm . '%')
                  ->orWhere('entity_id', 'like', '%' . $searchTerm . '%');
            });
        }

        if (isset($criteria['from_date'])) {
            $query->where('created_at', '>=', $criteria['from_date']);
        }

        if (isset($criteria['to_date'])) {
            $query->where('created_at', '<=', $criteria['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findOpenAlerts(): Collection
    {
        return $this->findByStatus('open');
    }

    public function findHighSeverityAlerts(): Collection
    {
        return ComplianceAlert::whereIn('severity', ['high', 'critical'])
            ->where('status', '!=', 'closed')
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countByStatus(): array
    {
        // Use collection methods to avoid PHPStan issues with selectRaw
        return ComplianceAlert::all()
            ->groupBy('status')
            ->map->count()
            ->toArray();
    }

    public function getRecentAlerts(int $limit = 10): Collection
    {
        return ComplianceAlert::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
