<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowExpired extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $expiredAt,
        public readonly float $returnAmount,
        public readonly string $returnTo
    ) {
    }
}
