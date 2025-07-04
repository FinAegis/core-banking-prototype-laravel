<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderBookInitialized extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderBookId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly array $metadata = []
    ) {}
}