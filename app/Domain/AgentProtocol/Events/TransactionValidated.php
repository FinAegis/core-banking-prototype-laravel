<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionValidated extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $validatedAt,
        public readonly array $validationData = []
    ) {
    }
}
