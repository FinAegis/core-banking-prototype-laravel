<?php

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CustodianAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $custodianId,
        public readonly string $name,
        public readonly string $type,
        public readonly array $config
    ) {}
}