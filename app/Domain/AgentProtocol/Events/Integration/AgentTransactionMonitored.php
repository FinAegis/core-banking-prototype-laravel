<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events\Integration;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AgentTransactionMonitored extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $agentId,
        public readonly int $riskScore,
        public readonly array $flags,
        public readonly bool $actionRequired
    ) {
    }
}
