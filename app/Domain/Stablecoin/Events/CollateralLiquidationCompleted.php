<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralLiquidationCompleted extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly float $liquidatedAmount,
        public readonly float $remainingDebt,
        public readonly array $liquidationDetails,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $completedAt
    ) {
    }
}
