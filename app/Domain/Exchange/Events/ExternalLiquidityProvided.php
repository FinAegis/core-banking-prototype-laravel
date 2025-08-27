<?php

namespace App\Domain\Exchange\Events;

use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ExternalLiquidityProvided extends ShouldBeStored
{
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly int $buyOrdersAdded,
        public readonly int $sellOrdersAdded,
        public readonly DateTimeImmutable $timestamp
    ) {
    }
}
