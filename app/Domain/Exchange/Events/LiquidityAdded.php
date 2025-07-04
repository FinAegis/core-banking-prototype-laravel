<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly string $baseAmount,
        public readonly string $quoteAmount,
        public readonly string $sharesMinted,
        public readonly string $newBaseReserve,
        public readonly string $newQuoteReserve,
        public readonly string $newTotalShares,
        public readonly array $metadata = []
    ) {}
}