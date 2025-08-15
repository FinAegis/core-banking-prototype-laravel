<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EnhancedCollateralPositionClosed extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly string $ownerId,
        public readonly array $finalCollateral,
        public readonly float $finalDebt,
        public readonly string $closureReason,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $closedAt
    ) {
    }
}
