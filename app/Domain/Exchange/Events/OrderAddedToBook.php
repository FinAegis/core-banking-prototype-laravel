<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderAddedToBook extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderBookId,
        public readonly string $orderId,
        public readonly string $type,
        public readonly string $price,
        public readonly string $amount,
        public readonly array $metadata = []
    ) {}
}