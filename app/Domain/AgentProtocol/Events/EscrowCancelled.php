<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowCancelled extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $cancelledBy,
        public readonly string $cancelledAt,
        public readonly string $reason,
        public readonly float $refundAmount,
        public readonly array $cancellationDetails = []
    ) {
    }
}
