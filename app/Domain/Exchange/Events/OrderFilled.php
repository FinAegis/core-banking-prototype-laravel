<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderFilled extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $totalExecutedAmount,
        public readonly string $averagePrice,
        public readonly array $metadata = []
    ) {}
}