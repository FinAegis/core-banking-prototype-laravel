<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RebalancingTriggered extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly array $driftLevels,
        public readonly string $reason,
        public readonly string $triggeredBy
    ) {
    }
}
