<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderCancelled extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {}
}