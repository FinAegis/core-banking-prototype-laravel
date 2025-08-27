<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use DateTimeInterface;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MarketMakerStarted extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly array $config,
        public readonly DateTimeInterface $startedAt,
    ) {
    }
}
