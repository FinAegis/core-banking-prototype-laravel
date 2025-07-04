<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PoolParametersUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly array $changes,
        public readonly array $metadata = []
    ) {}
}