<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CollateralPositionCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $position_uuid,
        public readonly string $account_uuid,
        public readonly string $stablecoin_code,
        public readonly string $collateral_asset_code,
        public readonly int $collateral_amount,
        public readonly int $debt_amount,
        public readonly float $collateral_ratio,
        public readonly string $status = 'active',
        public readonly array $metadata = []
    ) {}
}