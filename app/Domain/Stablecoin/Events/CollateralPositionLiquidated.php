<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralPositionLiquidated extends ShouldBeStored
{
    public function __construct(
        public readonly string $position_uuid,
        public readonly string $liquidator_account_uuid,
        public readonly int $collateral_seized,
        public readonly int $debt_repaid,
        public readonly int $liquidation_penalty,
        public readonly array $metadata = []
    ) {}
}