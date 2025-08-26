<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AllocationDriftDetected extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly array $driftLevels,
        public readonly float $maxDrift,
        public readonly float $threshold,
        public readonly string $detectedBy
    ) {
    }
}
