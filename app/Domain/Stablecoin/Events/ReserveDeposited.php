<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ReserveDeposited extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $asset,
        public readonly string $amount,
        public readonly string $custodianId,
        public readonly string $transactionHash,
        public readonly array $metadata = []
    ) {}
}