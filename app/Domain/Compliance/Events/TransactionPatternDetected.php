<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionPatternDetected extends ShouldBeStored
{
    public function __construct(
        public readonly string $patternId,
        public readonly string $patternType,
        public readonly array $transactionIds,
        public readonly float $confidenceScore,
        public readonly array $details,
        public readonly DateTimeImmutable $detectedAt
    ) {
    }
}
