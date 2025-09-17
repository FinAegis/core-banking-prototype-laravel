<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $fromAgentId,
        public readonly string $toAgentId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $type, // 'direct', 'escrow', 'split'
        public readonly ?string $escrowId = null,
        public readonly array $metadata = []
    ) {
    }
}
