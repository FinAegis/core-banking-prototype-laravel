<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PoolFeeCollected extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $currency,
        public readonly string $feeAmount,
        public readonly string $swapVolume,
        public readonly array $metadata = []
    ) {}
}