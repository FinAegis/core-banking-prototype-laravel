<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ThresholdExceeded extends ShouldBeStored
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $metricName,
        public readonly float $value,
        public readonly float $threshold,
        public readonly string $level,
        public readonly ?\DateTimeImmutable $exceededAt = null
    ) {
    }
}
