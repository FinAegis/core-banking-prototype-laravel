<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowFundsDeposited extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly float $amount,
        public readonly string $depositedBy,
        public readonly string $depositedAt,
        public readonly float $totalFunded,
        public readonly array $depositDetails = []
    ) {
    }
}
