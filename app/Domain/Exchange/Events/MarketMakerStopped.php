<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use DateTimeInterface;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MarketMakerStopped extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $reason, // 'completed', 'stopped', 'error', 'risk_limit'
        public readonly DateTimeInterface $stoppedAt,
    ) {
    }
}
