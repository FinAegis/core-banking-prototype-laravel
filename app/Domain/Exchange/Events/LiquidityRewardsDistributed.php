<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityRewardsDistributed extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $rewardAmount,
        public readonly string $rewardCurrency,
        public readonly string $totalShares,
        public readonly array $metadata = []
    ) {}
}