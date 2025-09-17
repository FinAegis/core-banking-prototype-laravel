<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityAdvertised extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $capability,
        public readonly string $version,
        public readonly array $parameters = [],
        public readonly array $metadata = []
    ) {
    }
}
