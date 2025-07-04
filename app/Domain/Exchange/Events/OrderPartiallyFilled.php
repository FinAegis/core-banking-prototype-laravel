<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderPartiallyFilled extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $filledAmount,
        public readonly string $remainingAmount,
        public readonly array $metadata = []
    ) {}
}