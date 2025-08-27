<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use DateTimeInterface;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MarketVolatilityChanged extends ShouldBeStored
{
    public function __construct(
        public readonly string $assetCode,
        public readonly float $oldVolatility,
        public readonly float $newVolatility,
        public readonly string $level, // 'low', 'normal', 'high', 'extreme'
        public readonly DateTimeInterface $timestamp,
    ) {
    }
}
