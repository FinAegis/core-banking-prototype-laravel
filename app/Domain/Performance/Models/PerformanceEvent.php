<?php

declare(strict_types=1);

namespace App\Domain\Performance\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class PerformanceEvent extends EloquentStoredEvent
{
    protected $table = 'performance_events';
}
