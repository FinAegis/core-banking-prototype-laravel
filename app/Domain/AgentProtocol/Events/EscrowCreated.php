<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EscrowCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $escrowId,
        public readonly string $transactionId,
        public readonly string $senderAgentId,
        public readonly string $receiverAgentId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $conditions = [],
        public readonly ?string $expiresAt = null,
        public readonly array $metadata = []
    ) {
    }
}
