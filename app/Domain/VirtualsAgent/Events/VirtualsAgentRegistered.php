<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class VirtualsAgentRegistered extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentProfileId,
        public readonly string $virtualsAgentId,
        public readonly int $employerUserId,
        public readonly string $agentName,
    ) {
    }
}
