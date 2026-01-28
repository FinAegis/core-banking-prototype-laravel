<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Shared\EventSourcing\TenantAwareSnapshot;

/**
 * Tenant-aware snapshot model for the Account domain.
 *
 * This model stores account aggregate snapshots in the tenant database,
 * ensuring complete data isolation between tenants.
 */
class TenantAccountSnapshot extends TenantAwareSnapshot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $table = 'account_snapshots';
}
