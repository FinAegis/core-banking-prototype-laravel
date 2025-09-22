<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowStatusNotificationSent extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $agentDid,
        public readonly string $status,
        public readonly Carbon $timestamp
    ) {
    }
}
