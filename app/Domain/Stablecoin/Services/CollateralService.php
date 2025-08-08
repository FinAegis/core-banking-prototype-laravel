<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\CollateralServiceInterface;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use Illuminate\Support\Collection;

class CollateralService implements CollateralServiceInterface
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {
    }

    /**
     * Convert collateral amount to peg asset value.
     */
    public function convertToPegAsset(string $fromAsset, float $amount, string $pegAsset): float
    {
        if ($fromAsset === $pegAsset) {
            return $amount;
        }

        $rateObject = $this->exchangeRateService->getRate($fromAsset, $pegAsset);
        if (! $rateObject) {
            throw new \RuntimeException("Exchange rate not found for {$fromAsset} to {$pegAsset}");
        }

        $rate = $rateObject->rate;

        return round($amount * $rate, 2);
    }

    /**
     * Calculate the total collateral value across all positions for a stablecoin.
     */
    public function calculateTotalCollateralValue(string $stablecoinCode): float
    {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        $positions = StablecoinCollateralPosition::where('stablecoin_code', $stablecoinCode)
            ->where('status', 'active')
            ->get();

        $totalValue = 0.0;
        foreach ($positions as $position) {
            $value = $this->convertToPegAsset(
                $position->collateral_asset_code,
                $position->collateral_amount,
                $stablecoin->peg_asset_code
            );
            $totalValue += $value;
        }

        return $totalValue;
    }

    /**
     * Get positions that are at risk of liquidation.
     */
    public function getPositionsAtRisk(float $bufferRatio = 0.05): Collection
    {
        return StablecoinCollateralPosition::with(['stablecoin', 'account', 'collateralAsset'])
            ->active()
            ->get()
            ->filter(
                function ($position) use ($bufferRatio) {
                    // Update position's collateral ratio with current exchange rates
                    $this->updatePositionCollateralRatio($position);

                    // Check if at risk (within buffer of minimum ratio)
                    $riskThreshold = $position->stablecoin->min_collateral_ratio + $bufferRatio;

                    return $position->collateral_ratio <= $riskThreshold;
                }
            );
    }

    /**
     * Get positions that should be liquidated immediately.
     */
    public function getPositionsForLiquidation(): Collection
    {
        return StablecoinCollateralPosition::with(['stablecoin', 'account', 'collateralAsset'])
            ->shouldAutoLiquidate()
            ->get()
            ->filter(
                function ($position) {
                    // Double-check with current exchange rates
                    $this->updatePositionCollateralRatio($position);

                    return $position->shouldAutoLiquidate();
                }
            );
    }

    /**
     * Update a position's collateral ratio based on current exchange rates.
     */
    public function updatePositionCollateralRatio(StablecoinCollateralPosition $position): void
    {
        if ($position->debt_amount == 0) {
            $position->collateral_ratio = 0;

            return;
        }

        $collateralValueInPegAsset = $this->convertToPegAsset(
            $position->collateral_asset_code,
            $position->collateral_amount,
            $position->stablecoin->peg_asset_code
        );

        $newRatio = $collateralValueInPegAsset / $position->debt_amount;

        // Only update if ratio has changed significantly (to avoid constant DB writes)
        if (abs($position->collateral_ratio - $newRatio) > 0.001) {
            $position->collateral_ratio = $newRatio;
            $position->liquidation_price = $position->calculateLiquidationPrice();
            $position->save();
        }
    }

    /**
     * Calculate the health score for a position (higher is better).
     */
    public function calculatePositionHealthScore(StablecoinCollateralPosition $position): float
    {
        if ($position->debt_amount == 0) {
            return 1.0; // Perfect health for zero debt
        }

        $minRatio = $position->stablecoin->min_collateral_ratio;
        $currentRatio = $position->collateral_ratio;

        // Health score: 0 = at liquidation threshold, 1 = at 2x minimum ratio
        return min(1.0, max(0.0, ($currentRatio - $minRatio) / $minRatio));
    }

    /**
     * Get collateral distribution by asset for a stablecoin.
     */
    public function getCollateralDistribution(string $stablecoinCode): array
    {
        $positions = StablecoinCollateralPosition::where('stablecoin_code', $stablecoinCode)
            ->where('status', 'active')
            ->get()
            ->groupBy('collateral_asset_code');

        $distribution = [];
        $totalValue = 0;

        foreach ($positions as $assetCode => $assetPositions) {
            $assetValue = $assetPositions->sum(
                function ($position) use ($stablecoinCode) {
                    $stablecoin = Stablecoin::find($stablecoinCode);

                    return $this->convertToPegAsset(
                        $position->collateral_asset_code,
                        $position->collateral_amount,
                        $stablecoin->peg_asset_code
                    );
                }
            );

            $distribution[$assetCode] = [
                'asset_code'     => $assetCode,
                'total_amount'   => $assetPositions->sum('collateral_amount'),
                'total_value'    => $assetValue,
                'position_count' => $assetPositions->count(),
            ];

            $totalValue += $assetValue;
        }

        // Add percentage calculations
        foreach ($distribution as &$asset) {
            $asset['percentage'] = $totalValue > 0 ? ($asset['total_value'] / $totalValue) * 100 : 0;
        }

        return $distribution;
    }

    /**
     * Calculate system-wide collateralization metrics.
     */
    public function getSystemCollateralizationMetrics(): array
    {
        $stablecoins = Stablecoin::active()->get();
        $metrics = [];

        foreach ($stablecoins as $stablecoin) {
            $totalCollateralValue = $this->calculateTotalCollateralValue($stablecoin->code);
            $globalRatio = $stablecoin->total_supply > 0
                ? $totalCollateralValue / $stablecoin->total_supply
                : 0;

            $activePositions = $stablecoin->activePositions()->count();
            $atRiskPositions = $this->getPositionsAtRisk()->where('stablecoin_code', $stablecoin->code)->count();

            $metrics[$stablecoin->code] = [
                'stablecoin_code'         => $stablecoin->code,
                'total_supply'            => $stablecoin->total_supply,
                'total_collateral_value'  => $totalCollateralValue,
                'global_ratio'            => $globalRatio,
                'target_ratio'            => $stablecoin->collateral_ratio,
                'min_ratio'               => $stablecoin->min_collateral_ratio,
                'active_positions'        => $activePositions,
                'at_risk_positions'       => $atRiskPositions,
                'is_healthy'              => $globalRatio >= $stablecoin->min_collateral_ratio,
                'collateral_distribution' => $this->getCollateralDistribution($stablecoin->code),
            ];
        }

        return $metrics;
    }

    /**
     * Calculate the liquidation priority score for positions.
     * Higher score = should be liquidated first.
     */
    public function calculateLiquidationPriority(StablecoinCollateralPosition $position): float
    {
        $healthScore = $this->calculatePositionHealthScore($position);
        $debtSize = $position->debt_amount;
        $timeSinceLastInteraction = $position->last_interaction_at
            ? now()->diffInHours($position->last_interaction_at)
            : 0;

        // Priority factors:
        // - Lower health score = higher priority
        // - Larger debt = higher priority
        // - Longer time since interaction = higher priority
        $priority = (1 - $healthScore) * 0.6 +
                   min(1.0, $debtSize / 1000000) * 0.3 + // Normalize debt to 0-1 scale
                   min(1.0, $timeSinceLastInteraction / 168) * 0.1; // 1 week = 1.0

        return $priority;
    }

    /**
     * Get recommended actions for position management.
     */
    public function getPositionRecommendations(StablecoinCollateralPosition $position): array
    {
        $this->updatePositionCollateralRatio($position);
        $healthScore = $this->calculatePositionHealthScore($position);
        $recommendations = [];

        if ($position->shouldAutoLiquidate()) {
            $recommendations[] = [
                'action'  => 'liquidate',
                'urgency' => 'critical',
                'message' => 'Position must be liquidated immediately',
            ];
        } elseif ($healthScore < 0.2) {
            $recommendations[] = [
                'action'           => 'add_collateral',
                'urgency'          => 'high',
                'message'          => 'Add collateral to avoid liquidation',
                'suggested_amount' => $this->calculateSuggestedCollateralAmount($position),
            ];
        } elseif ($healthScore < 0.4) {
            $recommendations[] = [
                'action'  => 'monitor',
                'urgency' => 'medium',
                'message' => 'Position health is declining, consider adding collateral',
            ];
        } elseif ($healthScore > 0.8) {
            $maxMintAmount = $position->calculateMaxMintAmount();
            if ($maxMintAmount > 0) {
                $recommendations[] = [
                    'action'          => 'mint_more',
                    'urgency'         => 'low',
                    'message'         => 'Position is over-collateralized, you can mint more stablecoins',
                    'max_mint_amount' => $maxMintAmount,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calculate suggested collateral amount to bring position to healthy state.
     */
    private function calculateSuggestedCollateralAmount(StablecoinCollateralPosition $position): int
    {
        $targetRatio = $position->stablecoin->collateral_ratio;
        $currentCollateralValue = $this->convertToPegAsset(
            $position->collateral_asset_code,
            $position->collateral_amount,
            $position->stablecoin->peg_asset_code
        );

        $requiredCollateralValue = $position->debt_amount * $targetRatio;
        $additionalValueNeeded = $requiredCollateralValue - $currentCollateralValue;

        if ($additionalValueNeeded <= 0) {
            return 0;
        }

        // Convert back to collateral asset
        // If same currency, no conversion needed
        if ($position->stablecoin->peg_asset_code === $position->collateral_asset_code) {
            return (int) round($additionalValueNeeded);
        }

        $rateObject = $this->exchangeRateService->getRate(
            $position->stablecoin->peg_asset_code,
            $position->collateral_asset_code
        );

        if (! $rateObject) {
            throw new \RuntimeException('Exchange rate not available');
        }

        return (int) round($additionalValueNeeded * $rateObject->rate);
    }
}
