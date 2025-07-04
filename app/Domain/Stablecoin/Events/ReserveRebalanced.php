<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReserveRebalanced extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly array $targetAllocations,
        public readonly string $executedBy,
        public readonly array $swaps,
        public readonly array $previousAllocations
    ) {}
}