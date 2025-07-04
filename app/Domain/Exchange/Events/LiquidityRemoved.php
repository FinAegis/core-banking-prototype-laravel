<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityRemoved extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly string $sharesBurned,
        public readonly string $baseAmount,
        public readonly string $quoteAmount,
        public readonly string $newBaseReserve,
        public readonly string $newQuoteReserve,
        public readonly string $newTotalShares,
        public readonly array $metadata = []
    ) {}
}