<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentKycRequiresReview extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly int $riskScore,
        public readonly string $reason,
        public readonly Carbon $reviewRequiredAt
    ) {
    }
}
