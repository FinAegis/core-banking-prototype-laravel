<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

/**
 * Base class for tenant-aware snapshots.
 *
 * Extend this class for any snapshot model that should be isolated per tenant.
 * The model will automatically use the 'tenant' database connection when tenancy
 * is initialized, and falls back to the default connection otherwise.
 *
 * Usage:
 * ```php
 * class TransactionSnapshot extends TenantAwareSnapshot
 * {
 *     public $table = 'transaction_snapshots';
 * }
 * ```
 */
abstract class TenantAwareSnapshot extends EloquentSnapshot
{
    use UsesTenantConnection;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    public $casts = [
        'state' => 'array',
    ];
}
