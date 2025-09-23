<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CapabilityVersionAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $capabilityId,
        public readonly string $version,
        public readonly string $previousVersion,
        public readonly array $changes,
        public readonly bool $isBackwardCompatible,
        public readonly ?string $migrationPath,
        public readonly string $addedAt
    ) {
    }
}
