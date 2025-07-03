<?php

declare(strict_types=1);

namespace App\Domain\Cgo\Repositories;

use App\Models\CgoEvent;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class CgoEventRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws \Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = CgoEvent::class
    )
    {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}