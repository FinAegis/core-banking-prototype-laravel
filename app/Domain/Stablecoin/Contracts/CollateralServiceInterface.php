<?php

namespace App\Domain\Stablecoin\Contracts;

use Illuminate\Support\Collection;

interface CollateralServiceInterface
{
    /**
     * Convert collateral value to peg asset
     *
     * @param string $fromAsset
     * @param int $amount
     * @param string $pegAsset
     * @return int
     */
    public function convertToPegAsset(string $fromAsset, int $amount, string $pegAsset): int;

    /**
     * Calculate total collateral value in the system
     *
     * @param string $stablecoinCode
     * @return int
     */
    public function calculateTotalCollateralValue(string $stablecoinCode): int;

    /**
     * Get positions at risk (collateral ratio below warning threshold)
     *
     * @param float $bufferRatio
     * @return Collection
     */
    public function getPositionsAtRisk(float $bufferRatio = 0.05): Collection;

    /**
     * Get positions eligible for liquidation
     *
     * @return Collection
     */
    public function getPositionsForLiquidation(): Collection;

    /**
     * Update position collateral ratio
     *
     * @param \App\Models\StablecoinCollateralPosition $position
     * @return void
     */
    public function updatePositionCollateralRatio(\App\Models\StablecoinCollateralPosition $position): void;

    /**
     * Calculate position health score
     *
     * @param \App\Models\StablecoinCollateralPosition $position
     * @return float
     */
    public function calculatePositionHealthScore(\App\Models\StablecoinCollateralPosition $position): float;

    /**
     * Get collateral distribution analysis
     *
     * @param string $stablecoinCode
     * @return array
     */
    public function getCollateralDistribution(string $stablecoinCode): array;

    /**
     * Get system-wide collateralization metrics
     *
     * @return array
     */
    public function getSystemCollateralizationMetrics(): array;
}