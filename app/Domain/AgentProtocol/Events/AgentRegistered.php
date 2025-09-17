<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentRegistered extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $did,
        public readonly string $name,
        public readonly string $type,
        public readonly array $metadata = []
    ) {
    }
}
