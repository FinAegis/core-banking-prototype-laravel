<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MetricRecorded extends ShouldBeStored
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $metricId,
        public readonly string $type,
        public readonly string $name,
        public readonly float $value,
        public readonly array $labels = [],
        public readonly ?string $unit = null,
        public readonly ?\DateTimeImmutable $recordedAt = null
    ) {
    }
}
