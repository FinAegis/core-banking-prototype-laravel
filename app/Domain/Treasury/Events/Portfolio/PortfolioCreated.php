<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events\Portfolio;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PortfolioCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $portfolioId,
        public readonly string $treasuryId,
        public readonly string $name,
        public readonly array $strategy,
        public readonly array $metadata
    ) {
    }
}
