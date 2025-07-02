<?php

declare(strict_types=1);

namespace App\Domain\Batch\Repositories;

use App\Models\BatchEvent;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class BatchRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws \Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = BatchEvent::class
    )
    {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}