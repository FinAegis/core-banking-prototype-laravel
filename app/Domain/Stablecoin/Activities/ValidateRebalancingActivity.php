<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Activities;

use App\Domain\Stablecoin\Aggregates\CollateralPositionAggregate;
use App\Domain\Stablecoin\Services\PriceOracleService;
use App\Domain\Stablecoin\ValueObjects\LiquidationThreshold;
use App\Domain\Stablecoin\ValueObjects\PositionHealth;
use Brick\Math\BigDecimal;
use Workflow\Activity;

class ValidateRebalancingActivity extends Activity
{
    public function __construct(
        private readonly PriceOracleService $priceOracle
    ) {
    }

    /**
     * Validate that rebalancing won't cause position to become unhealthy.
     */
    public function execute(string $positionId, array $rebalancingData): array
    {
        $aggregate = CollateralPositionAggregate::retrieve($positionId);
        $state = $aggregate->getState();

        // Get current position details
        $currentDebt = BigDecimal::of($state['totalDebt'] ?? 0);
        $liquidationThreshold = new LiquidationThreshold($state['liquidationThreshold']['value'] ?? 150);

        // Calculate current health
        $currentCollateralValue = $this->calculateCollateralValue($state['collateral']);
        $currentHealth = PositionHealth::calculate(
            $currentCollateralValue,
            $currentDebt,
            $liquidationThreshold
        );

        // Calculate projected health after rebalancing
        $projectedCollateral = $rebalancingData['final_allocation'] ?? $rebalancingData['swaps'] ?? [];
        $projectedValue = $this->calculateProjectedValue($projectedCollateral, $currentCollateralValue);
        $projectedHealth = PositionHealth::calculate(
            $projectedValue,
            $currentDebt,
            $liquidationThreshold
        );

        // Validate health requirements
        $isSafe = $projectedHealth->ratio()->isGreaterThan($liquidationThreshold->marginCallLevel());
        $improves = $projectedHealth->ratio()->isGreaterThanOrEqualTo($currentHealth->ratio());

        return [
            'position_id'           => $positionId,
            'is_safe'               => $isSafe,
            'improves_position'     => $improves,
            'current_health_ratio'  => $currentHealth->ratioPercentage(),
            'health_ratio_after'    => $projectedHealth->ratioPercentage(),
            'current_status'        => $currentHealth->status(),
            'projected_status'      => $projectedHealth->status(),
            'liquidation_threshold' => $liquidationThreshold->liquidationPercentage(),
            'margin_call_threshold' => $liquidationThreshold->marginCallPercentage(),
            'safe_threshold'        => $liquidationThreshold->safePercentage(),
            'warnings'              => $this->generateWarnings($currentHealth, $projectedHealth),
        ];
    }

    private function calculateCollateralValue(array $collateral): BigDecimal
    {
        $totalValue = BigDecimal::zero();

        foreach ($collateral as $asset => $amount) {
            $price = $this->priceOracle->getPrice($asset);
            $value = $price->multipliedBy($amount);
            $totalValue = $totalValue->plus($value);
        }

        return $totalValue;
    }

    private function calculateProjectedValue(array $projectedData, BigDecimal $currentValue): BigDecimal
    {
        // If we have final allocation percentages
        if (isset($projectedData['final_allocation'])) {
            // The total value remains roughly the same (minus costs)
            return $currentValue->multipliedBy('0.995'); // Account for 0.5% costs
        }

        // If we have swap details
        if (isset($projectedData['swaps'])) {
            $totalCost = BigDecimal::zero();
            foreach ($projectedData['swaps'] as $swap) {
                $slippage = BigDecimal::of($swap['estimated_slippage'] ?? 0.005);
                $swapCost = BigDecimal::of($swap['amount'])->multipliedBy($slippage);
                $totalCost = $totalCost->plus($swapCost);
            }

            return $currentValue->minus($totalCost);
        }

        // Default: return current value
        return $currentValue;
    }

    private function generateWarnings(PositionHealth $current, PositionHealth $projected): array
    {
        $warnings = [];

        if ($projected->requiresMarginCall() && ! $current->requiresMarginCall()) {
            $warnings[] = 'Rebalancing will trigger margin call';
        }

        if ($projected->requiresLiquidation()) {
            $warnings[] = 'CRITICAL: Rebalancing will trigger liquidation';
        }

        if (! $projected->isHealthy() && $current->isHealthy()) {
            $warnings[] = 'Position will become unhealthy after rebalancing';
        }

        $ratioDecrease = $current->ratio()->minus($projected->ratio());
        if ($ratioDecrease->isPositive() && $ratioDecrease->isGreaterThan(BigDecimal::of('0.1'))) {
            $warnings[] = sprintf(
                'Significant health ratio decrease: %.2f%%',
                $ratioDecrease->multipliedBy(100)->toFloat()
            );
        }

        return $warnings;
    }
}
