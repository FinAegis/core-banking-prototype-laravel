<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowDisputeResolved extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $resolvedBy,
        public readonly string $resolvedAt,
        public readonly string $resolutionType,
        public readonly array $resolutionAllocation = [],
        public readonly array $resolutionDetails = []
    ) {
    }
}
