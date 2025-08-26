<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AssetsAllocated extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $allocationId,
        public readonly array $allocations,
        public readonly float $totalAmount,
        public readonly string $allocatedBy
    ) {
    }
}
