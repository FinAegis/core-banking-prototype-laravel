<?php

declare(strict_types=1);

namespace App\Domain\VirtualsAgent\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class VirtualsAgentSuspended extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentProfileId,
        public readonly string $virtualsAgentId,
        public readonly string $reason,
    ) {
    }
}
