<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReportGenerated extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $reportId,
        public readonly string $reportType,
        public readonly string $period,
        public readonly string $filePath,
        public readonly string $format,
        public readonly array $metadata
    ) {
    }
}
