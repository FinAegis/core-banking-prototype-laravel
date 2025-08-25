<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PriceUpdated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $price,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
