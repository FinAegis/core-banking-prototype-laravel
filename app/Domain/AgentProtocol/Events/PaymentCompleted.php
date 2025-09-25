<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $completedAt,
        public readonly string $reference,
        public readonly float $processingTime
    ) {
    }
}
