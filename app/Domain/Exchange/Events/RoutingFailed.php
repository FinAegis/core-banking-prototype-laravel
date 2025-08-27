<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RoutingFailed extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $reason,
        public readonly DateTimeImmutable|\Illuminate\Support\Carbon $timestamp,
    ) {
    }
}
