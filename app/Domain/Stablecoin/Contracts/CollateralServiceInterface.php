<?php

namespace App\Domain\Stablecoin\Contracts;

use Illuminate\Support\Collection;

interface CollateralServiceInterface
{
    /**
     * Convert collateral value to peg asset.
     *
     * @param  string $fromAsset
     * @param  float  $amount
     * @param  string $pegAsset
     * @return float
     */
    public function convertToPegAsset(string $fromAsset, float $amount, string $pegAsset): float;

    /**
     * Calculate total collateral value in the system.
     *
     * @param  string $stablecoinCode
     * @return float
     */
    public function calculateTotalCollateralValue(string $stablecoinCode): float;

    /**
     * Get positions at risk (collateral ratio below warning threshold).
     *
     * @param  float $bufferRatio
     * @return Collection
     */
    public function getPositionsAtRisk(float $bufferRatio = 0.05): Collection;

    /**
     * Get positions eligible for liquidation.
     *
     * @return Collection
     */
    public function getPositionsForLiquidation(): Collection;

    /**
     * Update position collateral ratio.
     *
     * @param  \App\Domain\Stablecoin\Models\StablecoinCollateralPosition $position
     * @return void
     */
    public function updatePositionCollateralRatio(\App\Domain\Stablecoin\Models\StablecoinCollateralPosition $position): void;

    /**
     * Calculate position health score.
     *
     * @param  \App\Domain\Stablecoin\Models\StablecoinCollateralPosition $position
     * @return float
     */
    public function calculatePositionHealthScore(\App\Domain\Stablecoin\Models\StablecoinCollateralPosition $position): float;

    /**
     * Get collateral distribution analysis.
     *
     * @param  string $stablecoinCode
     * @return array
     */
    public function getCollateralDistribution(string $stablecoinCode): array;

    /**
     * Get system-wide collateralization metrics.
     *
     * @return array
     */
    public function getSystemCollateralizationMetrics(): array;
}
