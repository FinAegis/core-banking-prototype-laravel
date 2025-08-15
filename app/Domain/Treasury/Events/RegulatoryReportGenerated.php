<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RegulatoryReportGenerated extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $reportId,
        public readonly string $reportType,
        public readonly string $period,
        public readonly array $data,
        public readonly string $status,
        public readonly string $generatedBy
    ) {
    }
}
