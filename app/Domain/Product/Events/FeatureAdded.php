<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FeatureAdded extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $feature,
        public string $addedBy,
        public DateTimeImmutable $addedAt
    ) {
    }
}
