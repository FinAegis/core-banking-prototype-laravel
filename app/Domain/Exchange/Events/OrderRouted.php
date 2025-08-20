<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderRouted extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $poolId,
        public readonly float $amount,
        public readonly float $estimatedPrice,
        public readonly float $feeTier,
        public readonly \DateTimeImmutable|\Illuminate\Support\Carbon $timestamp,
    ) {
    }
}
