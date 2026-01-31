<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Repositories;

use App\Domain\Mobile\Models\MobileEvent;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class MobileEventRepository extends EloquentStoredEventRepository
{
    /**
     * @param string $storedEventModel
     *
     * @throws InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $storedEventModel = MobileEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}
