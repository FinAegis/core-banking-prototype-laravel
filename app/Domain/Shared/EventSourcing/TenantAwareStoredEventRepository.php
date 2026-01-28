<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

/**
 * Base repository for tenant-aware stored events.
 *
 * This repository ensures events are stored in the tenant database
 * using the TenantAwareStoredEvent model or its subclasses.
 *
 * Usage:
 * ```php
 * class TransactionEventRepository extends TenantAwareStoredEventRepository
 * {
 *     public function __construct()
 *     {
 *         parent::__construct(TransactionEvent::class);
 *     }
 * }
 * ```
 */
class TenantAwareStoredEventRepository extends EloquentStoredEventRepository
{
    /**
     * @throws InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = TenantAwareStoredEvent::class
    ) {
        if (! is_subclass_of($this->storedEventModel, EloquentStoredEvent::class)) {
            throw new InvalidEloquentStoredEventModel(
                "The class {$this->storedEventModel} must extend EloquentStoredEvent"
            );
        }
    }
}
