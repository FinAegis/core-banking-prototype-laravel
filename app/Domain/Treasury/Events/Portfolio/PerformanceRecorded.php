<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PerformanceRecorded extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $metricsId,
        public readonly array $metrics,
        public readonly string $period,
        public readonly string $recordedBy
    ) {
    }
}
