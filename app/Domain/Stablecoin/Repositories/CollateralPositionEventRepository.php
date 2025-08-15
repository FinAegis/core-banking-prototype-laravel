<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Repositories;

use App\Domain\Stablecoin\Models\CollateralPositionEvent;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class CollateralPositionEventRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = CollateralPositionEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}
