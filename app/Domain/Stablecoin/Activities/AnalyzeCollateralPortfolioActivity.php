<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Activities;

use App\Domain\Stablecoin\Aggregates\CollateralPositionAggregate;
use App\Domain\Stablecoin\Services\PriceOracleService;
use Brick\Math\BigDecimal;
use Workflow\Activity;

class AnalyzeCollateralPortfolioActivity extends Activity
{
    public function __construct(
        private readonly PriceOracleService $priceOracle
    ) {
    }

    /**
     * Analyze current collateral portfolio composition.
     */
    public function execute(string $positionId): array
    {
        $aggregate = CollateralPositionAggregate::retrieve($positionId);
        $state = $aggregate->getState();

        $portfolio = [];
        $totalValue = BigDecimal::zero();

        foreach ($state['collateral'] as $asset => $amount) {
            $price = $this->priceOracle->getPrice($asset);
            $value = $price->multipliedBy($amount);

            $portfolio[$asset] = [
                'amount' => $amount,
                'price'  => $price->toFloat(),
                'value'  => $value->toFloat(),
            ];

            $totalValue = $totalValue->plus($value);
        }

        // Calculate allocation percentages
        $allocation = [];
        foreach ($portfolio as $asset => $data) {
            $percentage = BigDecimal::of($data['value'])
                ->dividedBy($totalValue, 4)
                ->multipliedBy(100)
                ->toFloat();

            $allocation[$asset] = $percentage;
        }

        return [
            'position_id'        => $positionId,
            'portfolio'          => $portfolio,
            'allocation'         => $allocation,
            'total_value'        => $totalValue->toFloat(),
            'asset_count'        => count($portfolio),
            'concentration_risk' => $this->calculateConcentrationRisk($allocation),
        ];
    }

    private function calculateConcentrationRisk(array $allocation): string
    {
        if (empty($allocation)) {
            return 'UNKNOWN';
        }

        $maxAllocation = max($allocation);

        if ($maxAllocation > 80) {
            return 'HIGH';
        } elseif ($maxAllocation > 60) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
}
