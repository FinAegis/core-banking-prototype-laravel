<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentFailed extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $failedAt,
        public readonly string $reason,
        public readonly string $errorCode
    ) {
    }
}
