<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class FeeTierUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly float $oldFee,
        public readonly float $newFee,
        public readonly \DateTimeImmutable|\Illuminate\Support\Carbon $timestamp,
    ) {
    }
}
