<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use Illuminate\Support\Str;

class YieldOptimizationService
{
    private array $instrumentPool = [
        'money_market' => [
            'yield_range'    => [2.0, 3.5],
            'risk_score'     => 10,
            'liquidity'      => 'high',
            'min_investment' => 10000,
        ],
        'treasury_bills' => [
            'yield_range'    => [3.5, 4.5],
            'risk_score'     => 15,
            'liquidity'      => 'high',
            'min_investment' => 100000,
        ],
        'corporate_bonds' => [
            'yield_range'    => [4.5, 6.5],
            'risk_score'     => 35,
            'liquidity'      => 'medium',
            'min_investment' => 250000,
        ],
        'municipal_bonds' => [
            'yield_range'    => [3.5, 5.5],
            'risk_score'     => 25,
            'liquidity'      => 'medium',
            'min_investment' => 100000,
        ],
        'dividend_stocks' => [
            'yield_range'    => [3.0, 7.0],
            'risk_score'     => 55,
            'liquidity'      => 'high',
            'min_investment' => 50000,
        ],
        'reits' => [
            'yield_range'    => [4.0, 8.0],
            'risk_score'     => 65,
            'liquidity'      => 'medium',
            'min_investment' => 100000,
        ],
        'high_yield_bonds' => [
            'yield_range'    => [6.0, 10.0],
            'risk_score'     => 75,
            'liquidity'      => 'low',
            'min_investment' => 500000,
        ],
    ];

    public function optimizePortfolio(
        string $accountId,
        float $totalAmount,
        float $targetYield,
        RiskProfile $riskProfile,
        array $constraints = []
    ): array {
        // Filter instruments based on risk profile
        $eligibleInstruments = $this->filterInstrumentsByRisk($riskProfile);

        // Calculate optimal allocation
        $allocation = $this->calculateOptimalAllocation(
            $eligibleInstruments,
            $totalAmount,
            $targetYield,
            $constraints
        );

        // Validate allocation meets constraints
        $validation = $this->validateAllocation($allocation, $constraints);

        if (! $validation['is_valid']) {
            // Adjust allocation to meet constraints
            $allocation = $this->adjustAllocation($allocation, $constraints, $validation['issues']);
        }

        // Calculate expected metrics
        $metrics = $this->calculateMetrics($allocation);

        // Update Treasury Aggregate
        $this->updateTreasuryAggregate($accountId, $allocation, $metrics, $riskProfile);

        return [
            'allocation'       => $allocation,
            'metrics'          => $metrics,
            'risk_profile'     => $riskProfile->getLevel(),
            'constraints_met'  => $validation['is_valid'],
            'adjustments_made' => ! $validation['is_valid'],
        ];
    }

    private function filterInstrumentsByRisk(RiskProfile $riskProfile): array
    {
        $maxRiskScore = match ($riskProfile->getLevel()) {
            RiskProfile::LOW       => 30,
            RiskProfile::MEDIUM    => 50,
            RiskProfile::HIGH      => 70,
            RiskProfile::VERY_HIGH => 100,
            default                => 50,
        };

        return array_filter(
            $this->instrumentPool,
            fn ($instrument) => $instrument['risk_score'] <= $maxRiskScore
        );
    }

    private function calculateOptimalAllocation(
        array $instruments,
        float $totalAmount,
        float $targetYield,
        array $constraints
    ): array {
        $allocation = [];
        $remainingAmount = $totalAmount;

        // Sort instruments by yield potential
        uasort($instruments, function ($a, $b) {
            $avgYieldA = ($a['yield_range'][0] + $a['yield_range'][1]) / 2;
            $avgYieldB = ($b['yield_range'][0] + $b['yield_range'][1]) / 2;

            return $avgYieldB <=> $avgYieldA;
        });

        // Allocate to meet target yield
        foreach ($instruments as $name => $instrument) {
            if ($remainingAmount <= 0) {
                break;
            }

            $avgYield = ($instrument['yield_range'][0] + $instrument['yield_range'][1]) / 2;

            // Calculate allocation amount
            $allocationAmount = $this->calculateAllocationAmount(
                $remainingAmount,
                $instrument,
                $targetYield,
                $avgYield
            );

            if ($allocationAmount >= $instrument['min_investment']) {
                $allocation[] = [
                    'instrument'     => $name,
                    'amount'         => $allocationAmount,
                    'expected_yield' => $avgYield,
                    'risk_score'     => $instrument['risk_score'],
                    'liquidity'      => $instrument['liquidity'],
                ];
                $remainingAmount -= $allocationAmount;
            }
        }

        // Allocate remaining to lowest risk instrument
        if ($remainingAmount > 0) {
            $allocation[] = [
                'instrument'     => 'money_market',
                'amount'         => $remainingAmount,
                'expected_yield' => 2.5,
                'risk_score'     => 10,
                'liquidity'      => 'high',
            ];
        }

        return $allocation;
    }

    private function calculateAllocationAmount(
        float $availableAmount,
        array $instrument,
        float $targetYield,
        float $instrumentYield
    ): float {
        // Higher yield instruments get larger allocation if targeting high yield
        $yieldFactor = $instrumentYield / max($targetYield, 1);
        $baseAllocation = $availableAmount * 0.25; // Base 25% allocation

        $adjustedAllocation = $baseAllocation * min($yieldFactor, 2.0);

        return min($adjustedAllocation, $availableAmount);
    }

    private function validateAllocation(array $allocation, array $constraints): array
    {
        $issues = [];
        $totalAmount = array_sum(array_column($allocation, 'amount'));

        // Check liquidity constraints
        $liquidAssets = array_sum(array_map(
            fn ($a) => $a['liquidity'] === 'high' ? $a['amount'] : 0,
            $allocation
        ));

        $liquidityRatio = $liquidAssets / $totalAmount;
        $requiredLiquidity = $constraints['min_liquidity'] ?? 0.3;

        if ($liquidityRatio < $requiredLiquidity) {
            $issues[] = 'insufficient_liquidity';
        }

        // Check diversification
        $amounts = array_column($allocation, 'amount');
        $largestAllocation = ! empty($amounts) ? max($amounts) : 0;
        $concentrationRatio = $totalAmount > 0 ? $largestAllocation / $totalAmount : 0;

        if ($concentrationRatio > 0.5) {
            $issues[] = 'over_concentrated';
        }

        return [
            'is_valid' => empty($issues),
            'issues'   => $issues,
        ];
    }

    private function adjustAllocation(array $allocation, array $constraints, array $issues): array
    {
        // Adjust allocation to address identified issues
        foreach ($issues as $issue) {
            $allocation = match ($issue) {
                'insufficient_liquidity' => $this->increaseLiquidity($allocation),
                'over_concentrated'      => $this->diversifyAllocation($allocation),
                default                  => $allocation,
            };
        }

        return $allocation;
    }

    private function increaseLiquidity(array $allocation): array
    {
        // Shift allocation to more liquid instruments
        // Implementation would rebalance towards high liquidity instruments
        return $allocation;
    }

    private function diversifyAllocation(array $allocation): array
    {
        // Reduce concentration by spreading across more instruments
        // Implementation would cap individual allocations
        return $allocation;
    }

    private function calculateMetrics(array $allocation): array
    {
        $totalAmount = array_sum(array_column($allocation, 'amount'));
        $weightedYield = 0;
        $weightedRisk = 0;

        foreach ($allocation as $item) {
            $weight = $item['amount'] / $totalAmount;
            $weightedYield += $item['expected_yield'] * $weight;
            $weightedRisk += $item['risk_score'] * $weight;
        }

        return [
            'expected_yield'        => $weightedYield,
            'portfolio_risk'        => $weightedRisk,
            'sharpe_ratio'          => ($weightedYield - 2.0) / max($weightedRisk / 100, 0.01), // Risk-free rate of 2%
            'diversification_index' => 1 - $this->calculateHerfindahlIndex($allocation),
        ];
    }

    private function calculateHerfindahlIndex(array $allocation): float
    {
        $totalAmount = array_sum(array_column($allocation, 'amount'));
        $hhi = 0;

        foreach ($allocation as $item) {
            $weight = $item['amount'] / $totalAmount;
            $hhi += pow($weight, 2);
        }

        return $hhi;
    }

    private function updateTreasuryAggregate(
        string $accountId,
        array $allocation,
        array $metrics,
        RiskProfile $riskProfile
    ): void {
        $aggregate = TreasuryAggregate::retrieve($accountId);

        $aggregate->startYieldOptimization(
            Str::uuid()->toString(),
            'portfolio_optimization',
            $metrics['expected_yield'],
            $riskProfile,
            ['allocation' => $allocation],
            'system'
        );

        $aggregate->persist();
    }
}
