<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityRegistered extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly string $agentId,
        public readonly string $name,
        public readonly string $description,
        public readonly array $capabilities,
        public readonly string $version,
        public readonly ?string $category,
        public readonly array $metadata
    ) {
    }
}
