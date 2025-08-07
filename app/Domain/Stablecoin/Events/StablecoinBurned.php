<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class StablecoinBurned extends ShouldBeStored
{
    public function __construct(
        public readonly string $position_uuid,
        public readonly string $account_uuid,
        public readonly string $stablecoin_code,
        public readonly float $amount,
        public readonly array $metadata = []
    ) {
    }
}
