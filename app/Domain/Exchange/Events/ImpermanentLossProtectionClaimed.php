<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ImpermanentLossProtectionClaimed extends ShouldBeStored
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly string $positionId,
        public readonly string $impermanentLoss,
        public readonly string $impermanentLossPercent,
        public readonly string $compensation,
        public readonly string $compensationCurrency,
        public readonly array $metadata = []
    ) {
    }
}
