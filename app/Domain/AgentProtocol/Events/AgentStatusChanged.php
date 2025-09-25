<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentStatusChanged extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $newStatus,
        public readonly Carbon $changedAt,
        public readonly string $reason
    ) {
    }
}
