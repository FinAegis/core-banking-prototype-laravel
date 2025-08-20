<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderExecuted extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly string $side,
        public readonly float $amount,
        public readonly float $price,
        public readonly Carbon $executedAt,
        public readonly array $metadata = []
    ) {
    }
}
