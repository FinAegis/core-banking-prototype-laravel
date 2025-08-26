<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PortfolioRebalanced extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $rebalanceId,
        public readonly array $targetAllocations,
        public readonly array $previousAllocations,
        public readonly string $reason,
        public readonly string $initiatedBy
    ) {
    }
}
