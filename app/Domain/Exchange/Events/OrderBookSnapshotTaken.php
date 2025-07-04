<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderBookSnapshotTaken extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderBookId,
        public readonly array $buyOrders,
        public readonly array $sellOrders,
        public readonly ?string $bestBid,
        public readonly ?string $bestAsk,
        public readonly ?string $lastPrice,
        public readonly array $metadata = []
    ) {}
}