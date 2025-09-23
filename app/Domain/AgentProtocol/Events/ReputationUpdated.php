<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReputationUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $reputationId,
        public readonly string $agentId,
        public readonly string $transactionId,
        public readonly float $previousScore,
        public readonly float $newScore,
        public readonly float $scoreChange,
        public readonly string $outcome,
        public readonly float $value,
        public readonly array $metadata = []
    ) {
    }
}
