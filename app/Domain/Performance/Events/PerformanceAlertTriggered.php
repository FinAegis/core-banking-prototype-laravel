<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PerformanceAlertTriggered extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $alertType,
        public string $metricName,
        public float $value,
        public float $threshold,
        public string $severity,
        public string $message,
        public DateTimeImmutable $timestamp
    ) {
    }
}
