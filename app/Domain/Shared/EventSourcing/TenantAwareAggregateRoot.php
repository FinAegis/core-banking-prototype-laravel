<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use RuntimeException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Base class for tenant-aware aggregate roots.
 *
 * Extend this class for any aggregate that should store its events
 * in the tenant database. Override getStoredEventRepository() and
 * getSnapshotRepository() to provide the appropriate tenant-aware repositories.
 *
 * Usage:
 * ```php
 * class TransactionAggregate extends TenantAwareAggregateRoot
 * {
 *     protected function getStoredEventRepository(): StoredEventRepository
 *     {
 *         return app(TenantTransactionEventRepository::class);
 *     }
 *
 *     protected function getSnapshotRepository(): SnapshotRepository
 *     {
 *         return app(TenantTransactionSnapshotRepository::class);
 *     }
 * }
 * ```
 */
abstract class TenantAwareAggregateRoot extends AggregateRoot
{
    /**
     * Record that an event happened.
     *
     * Adds tenant context to the event metadata if tenancy is active.
     *
     * @param  ShouldBeStored  $domainEvent
     * @return static
     */
    public function recordThat(ShouldBeStored $domainEvent): static
    {
        // Add tenant context to the event if available
        if (function_exists('tenant') && tenant()) {
            $this->metaData = array_merge($this->metaData ?? [], [
                'tenant_id'   => tenant()->id,
                'recorded_at' => now()->toIso8601String(),
            ]);
        }

        /** @var static */
        return parent::recordThat($domainEvent);
    }

    /**
     * Verify that the aggregate is being used within valid tenant context.
     *
     * @throws RuntimeException If tenancy context is required but not available
     */
    protected function requireTenantContext(): void
    {
        if (! function_exists('tenant') || ! tenant()) {
            throw new RuntimeException(
                'This aggregate requires an active tenant context. ' .
                'Ensure tenancy is initialized before using tenant-aware aggregates.'
            );
        }
    }
}
