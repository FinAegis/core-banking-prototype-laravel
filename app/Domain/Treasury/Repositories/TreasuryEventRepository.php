<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Repositories;

use App\Domain\Treasury\Models\TreasuryEvent;
use InvalidArgumentException;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

final class TreasuryEventRepository extends EloquentStoredEventRepository
{
    public function __construct(
        protected string $storedEventModel = TreasuryEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidArgumentException("The class {$this->storedEventModel} must extend EloquentStoredEvent");
        }
    }
}
