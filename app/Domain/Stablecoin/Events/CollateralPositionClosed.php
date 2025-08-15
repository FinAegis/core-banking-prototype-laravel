<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralPositionClosed extends ShouldBeStored
{
    public function __construct(
        public readonly string $position_uuid,
        public readonly string $reason
    ) {
    }
}
