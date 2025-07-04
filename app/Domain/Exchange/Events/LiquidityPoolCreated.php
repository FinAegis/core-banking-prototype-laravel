<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityPoolCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly string $feeRate,
        public readonly array $metadata = []
    ) {}
}