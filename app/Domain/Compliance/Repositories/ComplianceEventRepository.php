<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Models\ComplianceEvent;
use InvalidArgumentException;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class ComplianceEventRepository extends EloquentStoredEventRepository
{
    public function __construct(
        protected string $storedEventModel = ComplianceEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidArgumentException("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}
