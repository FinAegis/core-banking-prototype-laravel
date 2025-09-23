<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityEnabled extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly string $enabledBy,
        public readonly string $reason,
        public readonly string $enabledAt
    ) {
    }
}
