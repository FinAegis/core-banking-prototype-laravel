<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentTransactionLimitExceeded extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly float $amount,
        public readonly string $period,
        public readonly float $currentTotal,
        public readonly float $limit,
        public readonly Carbon $exceededAt
    ) {
    }
}
