<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionScored extends ShouldBeStored
{
    public function __construct(
        public readonly string $agentId,
        public readonly string $transactionId,
        public readonly string $outcome // 'success', 'failure', 'dispute'
    ) {
    }
}
