<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReportDistributed extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $reportId,
        public readonly array $distributionResults,
        public readonly array $recipients,
        public readonly array $metadata
    ) {
    }
}
