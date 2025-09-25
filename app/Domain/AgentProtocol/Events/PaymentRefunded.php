<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentRefunded extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $refundedAt,
        public readonly float $refundAmount,
        public readonly string $reason,
        public readonly string $refundReference
    ) {
    }
}
