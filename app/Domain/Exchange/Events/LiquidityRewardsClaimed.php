<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityRewardsClaimed extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly array $rewards, // ['EUR' => '100.50', 'GCU' => '50.25']
        public readonly array $metadata = []
    ) {}
}