<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class EnhancedCollateralPositionCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly string $ownerId,
        public readonly array $collateral,
        public readonly float $initialDebt,
        public readonly string $collateralType,
        public readonly float $liquidationThreshold,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $createdAt
    ) {
    }
}
