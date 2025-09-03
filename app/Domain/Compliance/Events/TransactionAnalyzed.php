<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TransactionAnalyzed extends ShouldBeStored
{
    public function __construct(
        public readonly string $transactionId,
        public readonly float $riskScore,
        public readonly array $analysisResults,
        public readonly array $ruleResults,
        public readonly DateTimeImmutable $analyzedAt
    ) {
    }
}
