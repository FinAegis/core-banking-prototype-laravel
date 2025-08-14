<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralWithdrawn extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly array $withdrawn,
        public readonly array $remainingCollateral,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $withdrawnAt
    ) {
    }
}
