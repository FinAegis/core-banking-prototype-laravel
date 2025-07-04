<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralizationRatioUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $oldTargetRatio,
        public readonly string $newTargetRatio,
        public readonly string $oldMinimumRatio,
        public readonly string $newMinimumRatio,
        public readonly string $approvedBy
    ) {}
}