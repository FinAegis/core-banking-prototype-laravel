<?php

declare(strict_types=1);

namespace App\Domain\Account\Repositories;

use App\Domain\Account\Models\TenantAccountSnapshot;
use App\Domain\Shared\EventSourcing\TenantAwareSnapshotRepository;

/**
 * Tenant-aware snapshot repository for the Account domain.
 *
 * This repository stores account aggregate snapshots in the tenant database,
 * using the TenantAccountSnapshot model for data isolation.
 */
final class TenantAccountSnapshotRepository extends TenantAwareSnapshotRepository
{
    public function __construct()
    {
        parent::__construct(TenantAccountSnapshot::class);
    }
}
