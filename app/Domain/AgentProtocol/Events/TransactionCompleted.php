<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status, // 'success', 'partial', 'reversed'
        public readonly float $finalAmount,
        public readonly string $currency,
        public readonly array $fees = [],
        public readonly array $metadata = []
    ) {
    }
}
