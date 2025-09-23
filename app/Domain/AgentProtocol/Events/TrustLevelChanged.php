<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TrustLevelChanged extends ShouldBeStored
{
    public function __construct(
        public readonly string $reputationId,
        public readonly string $agentId,
        public readonly string $previousLevel,
        public readonly string $newLevel,
        public readonly float $score,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {
    }
}
