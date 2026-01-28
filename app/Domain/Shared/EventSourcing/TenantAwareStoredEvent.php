<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

/**
 * Base class for tenant-aware stored events.
 *
 * Extend this class for any event store model that should be isolated per tenant.
 * The model will automatically use the 'tenant' database connection.
 *
 * Usage:
 * ```php
 * class TransactionEvent extends TenantAwareStoredEvent
 * {
 *     public $table = 'transaction_events';
 * }
 * ```
 */
abstract class TenantAwareStoredEvent extends EloquentStoredEvent
{
    use UsesTenantConnection;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    public $casts = [
        'event_properties' => 'array',
        'meta_data'        => 'array',
    ];

    /**
     * Boot the model and add tenant context to metadata.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $event) {
            // Add tenant context to event metadata using array access
            if (function_exists('tenant') && tenant()) {
                /** @phpstan-ignore-next-line */
                $event->meta_data['tenant_id'] = tenant()->id;
                /** @phpstan-ignore-next-line */
                $event->meta_data['tenant_created_at'] = now()->toIso8601String();
            }
        });
    }
}
