<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class QuotesUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly array $bids,
        public readonly array $asks,
        public readonly float $spread,
        public readonly \DateTimeInterface $timestamp,
    ) {
    }
}
