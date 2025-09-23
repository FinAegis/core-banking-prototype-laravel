<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReputationPenaltyApplied extends ShouldBeStored
{
    public function __construct(
        public readonly string $reputationId,
        public readonly string $agentId,
        public readonly string $disputeId,
        public readonly float $previousScore,
        public readonly float $newScore,
        public readonly float $penalty,
        public readonly string $severity,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {
    }
}
