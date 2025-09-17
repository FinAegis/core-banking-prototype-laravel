<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowDisputed extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $disputedBy,
        public readonly string $disputedAt,
        public readonly string $reason,
        public readonly array $evidence = []
    ) {
    }
}
