<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentTransactionLimitSet extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly float $dailyLimit,
        public readonly float $weeklyLimit,
        public readonly float $monthlyLimit,
        public readonly Carbon $effectiveAt
    ) {
    }
}
