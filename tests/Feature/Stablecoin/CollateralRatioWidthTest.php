<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use Database\Factories\StablecoinCollateralPositionFactory;

/*
 * decimal(8,4) stopped at 9999.9999, so a well-collateralised position with a
 * small debt could not store its recalculated ratio (strict MySQL/MariaDB
 * refuses the write). SQLite does not enforce the width; the feature CI jobs
 * run this on MySQL.
 */
it('stores a collateral ratio above 9999.9999', function (): void {
    Stablecoin::create([
        'code'                   => 'WIDEUSD',
        'name'                   => 'Wide USD',
        'symbol'                 => 'WIDEUSD',
        'peg_asset_code'         => 'USD',
        'peg_ratio'              => 1.0,
        'target_price'           => 1.0,
        'stability_mechanism'    => 'collateralized',
        'collateral_ratio'       => 1.5,
        'min_collateral_ratio'   => 1.2,
        'liquidation_penalty'    => 0.1,
        'total_supply'           => 0,
        'max_supply'             => 10000000,
        'total_collateral_value' => 0,
        'mint_fee'               => 0.005,
        'burn_fee'               => 0.003,
        'precision'              => 2,
        'is_active'              => true,
        'minting_enabled'        => true,
        'burning_enabled'        => true,
    ]);

    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    $account = Account::factory()->create();

    $position = StablecoinCollateralPositionFactory::new()->create([
        'account_uuid'          => $account->uuid,
        'stablecoin_code'       => 'WIDEUSD',
        'collateral_asset_code' => 'USD',
        'collateral_ratio'      => '25000.1234',
    ]);

    assert($position instanceof StablecoinCollateralPosition);

    expect((string) $position->fresh()?->collateral_ratio)->toBe('25000.1234');
});
