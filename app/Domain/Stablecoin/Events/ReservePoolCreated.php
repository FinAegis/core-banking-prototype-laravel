<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReservePoolCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $stablecoinSymbol,
        public readonly string $targetCollateralizationRatio,
        public readonly string $minimumCollateralizationRatio
    ) {}
}