<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ImpermanentLossProtectionEnabled extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $protectionThreshold,
        public readonly string $maxCoverage,
        public readonly int $minHoldingPeriodHours,
        public readonly string $fundSize,
        public readonly array $metadata = []
    ) {
    }
}
