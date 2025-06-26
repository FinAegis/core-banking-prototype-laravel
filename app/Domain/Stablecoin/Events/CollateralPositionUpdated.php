<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralPositionUpdated extends ShouldBeStored
{
    public function __construct(
        public readonly string $position_uuid,
        public readonly int $collateral_amount,
        public readonly int $debt_amount,
        public readonly float $collateral_ratio,
        public readonly string $status,
        public readonly array $metadata = []
    ) {}
}