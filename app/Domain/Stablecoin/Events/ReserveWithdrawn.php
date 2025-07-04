<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReserveWithdrawn extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $asset,
        public readonly string $amount,
        public readonly string $custodianId,
        public readonly string $destinationAddress,
        public readonly string $reason,
        public readonly array $metadata = []
    ) {}
}