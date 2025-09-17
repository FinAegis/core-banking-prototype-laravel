<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowFundsReleased extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $releasedTo,
        public readonly float $amount,
        public readonly string $releasedBy,
        public readonly string $releasedAt,
        public readonly string $reason,
        public readonly array $releaseDetails = []
    ) {
    }
}
