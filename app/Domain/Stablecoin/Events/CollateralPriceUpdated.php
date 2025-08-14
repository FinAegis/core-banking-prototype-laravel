<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralPriceUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly float $oldPrice,
        public readonly float $newPrice,
        public readonly float $priceChange,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $updatedAt
    ) {
    }
}
