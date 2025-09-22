<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentStatusChanged extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $paymentId,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $reason,
        public readonly array $details,
        public readonly string $changedAt
    ) {
    }
}
