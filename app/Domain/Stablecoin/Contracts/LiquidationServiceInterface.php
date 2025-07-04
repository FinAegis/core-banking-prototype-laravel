<?php

namespace App\Domain\Stablecoin\Contracts;

use App\Models\Account;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Collection;

interface LiquidationServiceInterface
{
    /**
     * Liquidate a single position
     *
     * @param StablecoinCollateralPosition $position
     * @param Account|null $liquidator
     * @return array
     */
    public function liquidatePosition(StablecoinCollateralPosition $position, ?Account $liquidator = null): array;

    /**
     * Batch liquidate multiple positions
     *
     * @param Collection $positions
     * @param Account|null $liquidator
     * @return array
     */
    public function batchLiquidate(Collection $positions, ?Account $liquidator = null): array;

    /**
     * Auto-liquidate all eligible positions
     *
     * @param Account|null $liquidator
     * @return array
     */
    public function liquidateEligiblePositions(?Account $liquidator = null): array;

    /**
     * Calculate liquidation reward
     *
     * @param StablecoinCollateralPosition $position
     * @return array
     */
    public function calculateLiquidationReward(StablecoinCollateralPosition $position): array;

    /**
     * Get profitable liquidation opportunities
     *
     * @param int $limit
     * @return Collection
     */
    public function getLiquidationOpportunities(int $limit = 50): Collection;

    /**
     * Simulate mass liquidation scenario
     *
     * @param string $stablecoinCode
     * @param float $priceDropPercentage
     * @return array
     */
    public function simulateMassLiquidation(string $stablecoinCode, float $priceDropPercentage): array;

    /**
     * Emergency liquidation for system protection
     *
     * @param string $stablecoinCode
     * @return array
     */
    public function emergencyLiquidation(string $stablecoinCode): array;
}