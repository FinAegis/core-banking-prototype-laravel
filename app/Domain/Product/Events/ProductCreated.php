<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductCreated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $description,
        public string $category,
        public string $type,
        public array $metadata,
        public DateTimeImmutable $createdAt
    ) {
    }
}
