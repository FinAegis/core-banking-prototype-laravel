<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralHealthChecked extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly float $healthRatio,
        public readonly bool $isHealthy,
        public readonly bool $requiresAction,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $checkedAt
    ) {
    }
}
