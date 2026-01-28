<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use InvalidArgumentException;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

/**
 * Base repository for tenant-aware snapshots.
 *
 * This repository ensures snapshots are stored in the tenant database
 * using the TenantAwareSnapshot model or its subclasses.
 *
 * Usage:
 * ```php
 * class TransactionSnapshotRepository extends TenantAwareSnapshotRepository
 * {
 *     public function __construct()
 *     {
 *         parent::__construct(TransactionSnapshot::class);
 *     }
 * }
 * ```
 */
class TenantAwareSnapshotRepository extends EloquentSnapshotRepository
{
    public function __construct(
        protected string $snapshotModel = TenantAwareSnapshot::class
    ) {
        if (! is_subclass_of($this->snapshotModel, EloquentSnapshot::class)) {
            throw new InvalidArgumentException(
                "The class {$this->snapshotModel} must extend EloquentSnapshot"
            );
        }
    }
}
