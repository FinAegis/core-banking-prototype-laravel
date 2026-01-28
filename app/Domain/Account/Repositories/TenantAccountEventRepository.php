<?php

declare(strict_types=1);

namespace App\Domain\Account\Repositories;

use App\Domain\Account\Models\TenantAccountEvent;
use App\Domain\Shared\EventSourcing\TenantAwareStoredEventRepository;

/**
 * Tenant-aware event repository for the Account domain.
 *
 * This repository stores account events in the tenant database,
 * using the TenantAccountEvent model for data isolation.
 */
final class TenantAccountEventRepository extends TenantAwareStoredEventRepository
{
    public function __construct()
    {
        parent::__construct(TenantAccountEvent::class);
    }
}
