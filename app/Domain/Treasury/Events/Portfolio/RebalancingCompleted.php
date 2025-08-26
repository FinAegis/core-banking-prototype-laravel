<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RebalancingCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $rebalanceId,
        public readonly array $executedActions,
        public readonly array $executionMetrics,
        public readonly string $completedBy,
        public readonly array $metadata
    ) {
    }
}
