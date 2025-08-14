<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly array $collateral,
        public readonly array $newTotalCollateral,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $addedAt
    ) {
    }
}
