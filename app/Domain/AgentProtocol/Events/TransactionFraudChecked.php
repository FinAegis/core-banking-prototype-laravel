<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionFraudChecked extends ShouldBeStored
{
    public function __construct(
        public readonly string $securityId,
        public readonly string $transactionId,
        public readonly string $agentId,
        public readonly float $riskScore,
        public readonly array $riskFactors,
        public readonly string $decision,
        public readonly Carbon $timestamp,
        public readonly array $metadata = []
    ) {
    }
}
