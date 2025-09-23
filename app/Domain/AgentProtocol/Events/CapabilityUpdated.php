<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly array $updates,
        public readonly string $updatedBy,
        public readonly string $updatedAt
    ) {
    }
}
