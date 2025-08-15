<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class YieldOptimizationStarted extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $optimizationId,
        public readonly string $strategy,
        public readonly float $targetYield,
        public readonly string $riskProfile,
        public readonly array $constraints,
        public readonly string $startedBy
    ) {
    }
}
