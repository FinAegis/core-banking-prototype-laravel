<?php

declare(strict_types=1);

namespace App\Domain\Performance\Repositories;

use App\Domain\Performance\Models\PerformanceEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

class PerformanceEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = PerformanceEvent::class;
}
