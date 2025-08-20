<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class SpreadAdjusted extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly float $oldSpread,
        public readonly float $newSpread,
        public readonly string $reason,
        public readonly \DateTimeInterface $timestamp,
    ) {
    }
}
