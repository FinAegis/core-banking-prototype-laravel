<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $fromAgentId,
        public readonly string $toAgentId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly array $metadata,
        public readonly string $initiatedAt
    ) {
    }
}
