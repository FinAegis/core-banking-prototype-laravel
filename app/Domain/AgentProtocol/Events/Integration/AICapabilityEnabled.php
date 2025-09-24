<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AICapabilityEnabled extends ShouldBeStored
{
    public function __construct(
        public readonly string $aiAgentId,
        public readonly string $protocolAgentId,
        public readonly string $capability,
        public readonly array $settings
    ) {
    }
}
