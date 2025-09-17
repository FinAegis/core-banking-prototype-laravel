<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowHeld extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $escrowId,
        public readonly float $amount,
        public readonly string $heldAt,
        public readonly array $escrowDetails = []
    ) {
    }
}
