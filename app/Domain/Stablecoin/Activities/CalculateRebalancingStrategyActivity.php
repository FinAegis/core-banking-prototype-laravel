<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Activities;

use App\Domain\Stablecoin\Services\PriceOracleService;
use Brick\Math\BigDecimal;
use Workflow\Activity;

class CalculateRebalancingStrategyActivity extends Activity
{
    private const DEFAULT_SLIPPAGE = 0.005; // 0.5% slippage

    private const DEFAULT_GAS_ESTIMATE = 50.0; // $50 gas estimate

    public function __construct(
        private readonly PriceOracleService $priceOracle
    ) {
    }

    /**
     * Calculate optimal rebalancing strategy.
     */
    public function execute(
        array $currentPortfolio,
        array $targetAllocation,
        array $params = []
    ): array {
        $totalValue = BigDecimal::of($currentPortfolio['total_value']);
        $swaps = [];
        $estimatedCost = BigDecimal::zero();

        // Calculate target values for each asset
        $targetValues = [];
        foreach ($targetAllocation as $asset => $targetPercentage) {
            $targetValue = $totalValue->multipliedBy($targetPercentage)->dividedBy(100, 2);
            $targetValues[$asset] = $targetValue;
        }

        // Determine required swaps
        $surplusAssets = [];
        $deficitAssets = [];

        foreach ($targetValues as $asset => $targetValue) {
            $currentValue = BigDecimal::of($currentPortfolio['portfolio'][$asset]['value'] ?? 0);
            $difference = $targetValue->minus($currentValue);

            if ($difference->isPositive()) {
                $deficitAssets[$asset] = $difference->toFloat();
            } elseif ($difference->isNegative()) {
                $surplusAssets[$asset] = $difference->abs()->toFloat();
            }
        }

        // Create swap pairs
        foreach ($deficitAssets as $toAsset => $deficitAmount) {
            foreach ($surplusAssets as $fromAsset => $surplusAmount) {
                if ($surplusAmount <= 0) {
                    continue;
                }

                $swapAmount = min($deficitAmount, $surplusAmount);

                $swaps[] = [
                    'from_asset'         => $fromAsset,
                    'to_asset'           => $toAsset,
                    'amount'             => $swapAmount,
                    'estimated_slippage' => $params['slippage'] ?? self::DEFAULT_SLIPPAGE,
                ];

                // Update remaining amounts
                $surplusAssets[$fromAsset] -= $swapAmount;
                $deficitAssets[$toAsset] -= $swapAmount;

                // Calculate estimated cost (gas + slippage)
                $slippageCost = BigDecimal::of($swapAmount)->multipliedBy(self::DEFAULT_SLIPPAGE);
                $estimatedCost = $estimatedCost->plus($slippageCost)->plus(self::DEFAULT_GAS_ESTIMATE);

                if ($deficitAssets[$toAsset] <= 0) {
                    break;
                }
            }
        }

        // Calculate final allocation after swaps
        $finalAllocation = $this->calculateFinalAllocation(
            $currentPortfolio['portfolio'],
            $swaps
        );

        return [
            'swaps'              => $swaps,
            'final_allocation'   => $finalAllocation,
            'estimated_cost'     => $estimatedCost->toFloat(),
            'swap_count'         => count($swaps),
            'optimization_score' => $this->calculateOptimizationScore(
                $currentPortfolio['allocation'],
                $targetAllocation,
                $finalAllocation
            ),
        ];
    }

    private function calculateFinalAllocation(array $portfolio, array $swaps): array
    {
        $finalPortfolio = $portfolio;

        foreach ($swaps as $swap) {
            $fromAsset = $swap['from_asset'];
            $toAsset = $swap['to_asset'];
            $amount = $swap['amount'];

            // Deduct from source asset
            if (isset($finalPortfolio[$fromAsset])) {
                $finalPortfolio[$fromAsset]['value'] -= $amount;
            }

            // Add to target asset (accounting for slippage)
            $receivedAmount = $amount * (1 - $swap['estimated_slippage']);
            if (! isset($finalPortfolio[$toAsset])) {
                $finalPortfolio[$toAsset] = [
                    'amount' => 0,
                    'price'  => $this->priceOracle->getPrice($toAsset)->toFloat(),
                    'value'  => 0,
                ];
            }
            $finalPortfolio[$toAsset]['value'] += $receivedAmount;
        }

        // Recalculate allocation percentages
        $totalValue = array_sum(array_column($finalPortfolio, 'value'));
        $allocation = [];

        foreach ($finalPortfolio as $asset => $data) {
            if ($totalValue > 0) {
                $allocation[$asset] = ($data['value'] / $totalValue) * 100;
            } else {
                $allocation[$asset] = 0;
            }
        }

        return $allocation;
    }

    private function calculateOptimizationScore(
        array $currentAllocation,
        array $targetAllocation,
        array $finalAllocation
    ): float {
        $currentDeviation = $this->calculateDeviation($currentAllocation, $targetAllocation);
        $finalDeviation = $this->calculateDeviation($finalAllocation, $targetAllocation);

        if ($currentDeviation == 0) {
            return 100.0; // Already optimal
        }

        $improvement = ($currentDeviation - $finalDeviation) / $currentDeviation;

        return round($improvement * 100, 2);
    }

    private function calculateDeviation(array $actual, array $target): float
    {
        $deviation = 0;

        foreach ($target as $asset => $targetPercentage) {
            $actualPercentage = $actual[$asset] ?? 0;
            $deviation += abs($targetPercentage - $actualPercentage);
        }

        return $deviation;
    }
}
