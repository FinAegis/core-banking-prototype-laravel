<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OracleDeviationDetected extends ShouldBeStored
{
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly float $deviation,
        public readonly array $prices,
        public readonly ?string $aggregateUuid = null
    ) {}
}