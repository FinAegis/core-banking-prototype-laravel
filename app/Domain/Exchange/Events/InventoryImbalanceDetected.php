<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class InventoryImbalanceDetected extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly float $baseCurrencyRatio,
        public readonly string $severity, // 'low', 'moderate', 'critical'
        public readonly string $recommendedAction, // 'monitor', 'rebalance', 'rebalance_urgent'
    ) {
    }
}
