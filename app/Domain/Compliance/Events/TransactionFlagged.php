<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionFlagged extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $flagType,
        public readonly string $severity,
        public readonly string $reason,
        public readonly array $details,
        public readonly DateTimeImmutable $flaggedAt
    ) {
    }
}
