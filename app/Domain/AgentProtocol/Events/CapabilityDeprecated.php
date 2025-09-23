<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityDeprecated extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly string $deprecatedBy,
        public readonly string $reason,
        public readonly ?string $replacementCapabilityId,
        public readonly ?string $sunsetDate,
        public readonly string $deprecatedAt
    ) {
    }
}
