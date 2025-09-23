<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReputationBoosted extends ShouldBeStored
{
    public function __construct(
        public readonly string $reputationId,
        public readonly string $agentId,
        public readonly float $previousScore,
        public readonly float $newScore,
        public readonly float $boostAmount,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {
    }
}
