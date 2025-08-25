<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ProductDeactivated extends ShouldBeStored
{
    public function __construct(
        public string $productId,
        public string $reason,
        public string $deactivatedBy,
        public DateTimeImmutable $deactivatedAt
    ) {
    }
}
