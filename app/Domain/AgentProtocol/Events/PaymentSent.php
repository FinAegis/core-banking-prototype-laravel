<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentSent extends ShouldBeStored
{
    public function __construct(
        public readonly string $walletId,
        public readonly string $transactionId,
        public readonly string $toAgentId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {
    }
}
