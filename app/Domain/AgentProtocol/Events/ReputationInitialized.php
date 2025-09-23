<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReputationInitialized extends ShouldBeStored
{
    public function __construct(
        public readonly string $reputationId,
        public readonly string $agentId,
        public readonly float $initialScore,
        public readonly string $trustLevel,
        public readonly array $metadata = []
    ) {
    }
}
