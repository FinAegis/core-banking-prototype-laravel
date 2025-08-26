<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RebalancingFailed extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $rebalanceId,
        public readonly string $reason,
        public readonly array $plannedActions,
        public readonly string $failedBy,
        public readonly array $metadata
    ) {
    }
}
