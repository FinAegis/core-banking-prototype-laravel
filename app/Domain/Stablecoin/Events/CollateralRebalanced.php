<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\ValueObjects\Hash;
use DateTimeImmutable;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralRebalanced extends ShouldBeStored
{
    public function __construct(
        public readonly string $positionId,
        public readonly array $oldAllocation,
        public readonly array $newAllocation,
        public readonly string $rebalanceReason,
        public readonly Hash $hash,
        public readonly DateTimeImmutable $rebalancedAt
    ) {
    }
}
