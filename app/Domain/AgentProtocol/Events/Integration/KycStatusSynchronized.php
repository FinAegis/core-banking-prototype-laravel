<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Illuminate\Support\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class KycStatusSynchronized extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $status,
        public readonly string $level,
        public readonly Carbon $syncedAt
    ) {
    }
}
