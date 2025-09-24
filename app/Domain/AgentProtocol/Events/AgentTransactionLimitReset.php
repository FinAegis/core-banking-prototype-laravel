<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentTransactionLimitReset extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $period,
        public readonly Carbon $resetAt
    ) {
    }
}
