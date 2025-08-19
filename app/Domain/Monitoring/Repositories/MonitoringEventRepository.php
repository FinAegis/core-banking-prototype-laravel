<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Repositories;

use App\Domain\Monitoring\Models\MonitoringEvent;
use InvalidArgumentException;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class MonitoringEventRepository extends EloquentStoredEventRepository
{
    public function __construct(
        protected string $storedEventModel = MonitoringEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidArgumentException("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}
