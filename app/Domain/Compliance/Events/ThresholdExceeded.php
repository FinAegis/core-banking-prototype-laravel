<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ThresholdExceeded extends ShouldBeStored
{
    public function __construct(
        public readonly string $entityId,
        public readonly string $entityType,
        public readonly string $thresholdType,
        public readonly float $currentValue,
        public readonly float $threshold,
        public readonly array $metadata,
        public readonly DateTimeImmutable $exceededAt
    ) {
    }
}
