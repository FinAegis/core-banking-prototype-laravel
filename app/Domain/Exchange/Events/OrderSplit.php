<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderSplit extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly array $splits,
        public readonly float $totalAmount,
        public readonly DateTimeImmutable|\Illuminate\Support\Carbon $timestamp,
    ) {
    }
}
