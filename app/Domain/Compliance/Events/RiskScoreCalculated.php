<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RiskScoreCalculated extends ShouldBeStored
{
    public function __construct(
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly float $riskScore,
        public readonly string $riskLevel,
        public readonly array $factors,
        public readonly DateTimeImmutable $calculatedAt
    ) {
    }
}
