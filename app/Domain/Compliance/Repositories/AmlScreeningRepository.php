<?php

/**
 * AML Screening Repository for managing AML screening events.
 */

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Models\AmlScreeningEvent;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

/**
 * Repository for managing AML screening events using event sourcing.
 */
class AmlScreeningRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->storedEventModel = AmlScreeningEvent::class;

        parent::__construct();
    }

    /**
     * Retrieve all screening events for a specific entity.
     *
     * @param  string  $entityType  The entity type (e.g., 'user', 'account', 'transaction')
     * @param  string  $entityId  The entity ID
     */
    public function getByEntity(string $entityType, string $entityId): Collection
    {
        return $this->storedEventModel::query()
            ->where('meta_data->entity_type', $entityType)
            ->where('meta_data->entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve screening events by aggregate UUID.
     *
     * @param  string  $aggregateUuid  The aggregate UUID
     */
    public function getByAggregateUuid(string $aggregateUuid): Collection
    {
        return $this->storedEventModel::query()
            ->where('aggregate_uuid', $aggregateUuid)
            ->orderBy('aggregate_version')
            ->get();
    }

    /**
     * Retrieve screening events by screening status.
     *
     * @param  string  $status  The screening status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->storedEventModel::query()
            ->where('meta_data->status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve screening events that require review.
     */
    public function getPendingReview(): Collection
    {
        return $this->storedEventModel::query()
            ->where('meta_data->requires_review', true)
            ->whereNull('meta_data->reviewed_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Retrieve high-risk screening events.
     */
    public function getHighRiskScreenings(): Collection
    {
        return $this->storedEventModel::query()
            ->whereIn('meta_data->risk_level', ['high', 'critical'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve screening events by date range.
     *
     * @param  DateTimeInterface  $startDate  The start date
     * @param  DateTimeInterface  $endDate  The end date
     */
    public function getByDateRange(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): Collection {
        return $this->storedEventModel::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve screening events by provider.
     *
     * @param  string  $provider  The screening provider
     */
    public function getByProvider(string $provider): Collection
    {
        return $this->storedEventModel::query()
            ->where('meta_data->provider', $provider)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Count screening events by entity.
     *
     * @param  string  $entityType  The entity type
     * @param  string  $entityId  The entity ID
     */
    public function countByEntity(string $entityType, string $entityId): int
    {
        return $this->storedEventModel::query()
            ->where('meta_data->entity_type', $entityType)
            ->where('meta_data->entity_id', $entityId)
            ->count();
    }

    /**
     * Check if entity has been screened within a time period.
     *
     * @param  string  $entityType  The entity type
     * @param  string  $entityId  The entity ID
     * @param  DateTimeInterface  $since  The date to check from
     */
    public function hasRecentScreening(
        string $entityType,
        string $entityId,
        DateTimeInterface $since
    ): bool {
        return $this->storedEventModel::query()
            ->where('meta_data->entity_type', $entityType)
            ->where('meta_data->entity_id', $entityId)
            ->where('created_at', '>=', $since)
            ->exists();
    }
}
