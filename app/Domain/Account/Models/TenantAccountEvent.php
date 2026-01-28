<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

/**
 * Tenant-aware stored event model for the Account domain.
 *
 * This model stores account-related events in the tenant database,
 * ensuring complete data isolation between tenants.
 */
class TenantAccountEvent extends TenantAwareStoredEvent
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $table = 'account_events';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    public $casts = [
        'event_properties' => 'array',
        'meta_data'        => 'array',
    ];
}
