<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionSecurityInitialized extends ShouldBeStored
{
    public function __construct(
        public readonly string $securityId,
        public readonly string $transactionId,
        public readonly string $agentId,
        public readonly string $securityLevel,
        public readonly array $requirements,
        public readonly array $metadata = []
    ) {
    }
}
