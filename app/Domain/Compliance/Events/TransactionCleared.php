<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionCleared extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $clearedBy,
        public readonly string $reason,
        public readonly array $metadata,
        public readonly DateTimeImmutable $clearedAt
    ) {
    }
}
