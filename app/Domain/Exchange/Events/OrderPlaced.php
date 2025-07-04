<?php

namespace App\Domain\Exchange\Events;


use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderPlaced extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $accountId,
        public readonly string $type, // 'buy' or 'sell'
        public readonly string $orderType, // 'market' or 'limit'
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly string $amount,
        public readonly ?string $price = null, // null for market orders
        public readonly ?string $stopPrice = null,
        public readonly array $metadata = []
    ) {}
}