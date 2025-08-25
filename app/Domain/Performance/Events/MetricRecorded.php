<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MetricRecorded extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $name,
        public float $value,
        public string $type,
        public array $tags,
        public DateTimeImmutable $timestamp
    ) {
    }
}
