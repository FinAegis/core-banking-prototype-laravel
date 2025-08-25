<?php

declare(strict_types=1);

namespace App\Domain\Performance\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PerformanceReportGenerated extends ShouldBeStored
{
    public function __construct(
        public string $metricId,
        public string $systemId,
        public string $reportType,
        public array $reportData,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public DateTimeImmutable $generatedAt
    ) {
    }
}
