<?php

namespace App\Domain\Stablecoin\Contracts;

use App\Models\Account;
use App\Models\StablecoinCollateralPosition;

interface StablecoinIssuanceServiceInterface
{
    /**
     * Mint stablecoins by locking collateral
     *
     * @param Account $account
     * @param string $stablecoinCode
     * @param string $collateralAssetCode
     * @param int $collateralAmount
     * @param int $mintAmount
     * @return StablecoinCollateralPosition
     */
    public function mint(
        Account $account,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount,
        int $mintAmount
    ): StablecoinCollateralPosition;

    /**
     * Burn stablecoins and release collateral
     *
     * @param Account $account
     * @param string $stablecoinCode
     * @param int $burnAmount
     * @param int|null $collateralReleaseAmount
     * @return StablecoinCollateralPosition
     */
    public function burn(
        Account $account,
        string $stablecoinCode,
        int $burnAmount,
        ?int $collateralReleaseAmount = null
    ): StablecoinCollateralPosition;

    /**
     * Add collateral to an existing position
     *
     * @param Account $account
     * @param string $stablecoinCode
     * @param string $collateralAssetCode
     * @param int $collateralAmount
     * @return StablecoinCollateralPosition
     */
    public function addCollateral(
        Account $account,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount
    ): StablecoinCollateralPosition;
}