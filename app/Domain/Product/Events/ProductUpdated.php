<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductUpdated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public array $updates,
        public string $updatedBy,
        public DateTimeImmutable $updatedAt
    ) {
    }
}
