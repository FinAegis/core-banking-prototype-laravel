<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityPoolRebalanced extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $oldRatio,
        public readonly string $newRatio,
        public readonly string $rebalanceAmount,
        public readonly string $rebalanceCurrency,
        public readonly array $metadata = []
    ) {}
}