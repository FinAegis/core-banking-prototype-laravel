<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityAdvertised extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly string $agentId,
        public readonly array $endpoints,
        public readonly array $parameters,
        public readonly array $requiredPermissions,
        public readonly array $supportedProtocols,
        public readonly string $advertisedAt
    ) {
    }
}
