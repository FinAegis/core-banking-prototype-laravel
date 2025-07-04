<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderMatched extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $matchedOrderId,
        public readonly string $tradeId,
        public readonly string $executedPrice,
        public readonly string $executedAmount,
        public readonly string $makerFee,
        public readonly string $takerFee,
        public readonly array $metadata = []
    ) {}
}