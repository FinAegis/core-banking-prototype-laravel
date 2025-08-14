<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralLiquidationStarted extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly string $ownerId,
        public readonly float $collateralValue,
        public readonly float $debtAmount,
        public readonly string $liquidationReason,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $startedAt
    ) {
    }
}
