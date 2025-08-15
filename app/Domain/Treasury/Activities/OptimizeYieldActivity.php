<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use Workflow\Activity;

class OptimizeYieldActivity extends Activity
{
    public function execute(array $input): array
    {
        $accountId = $input['account_id'];
        $allocations = $input['allocations'];
        $riskProfile = $input['risk_profile'];
        $targetYield = $input['target_yield'];

        // Calculate current weighted average yield
        $totalAmount = array_sum(array_column($allocations, 'amount'));
        $currentYield = 0;

        foreach ($allocations as $allocation) {
            $weight = $allocation['amount'] / $totalAmount;
            $currentYield += $allocation['instrument']['yield'] * $weight;
        }

        // Optimize allocations to reach target yield
        $optimizedAllocations = $this->optimizeAllocations(
            $allocations,
            $targetYield,
            $riskProfile
        );

        // Calculate new yield after optimization
        $optimizedYield = 0;
        foreach ($optimizedAllocations as $allocation) {
            $weight = $allocation['amount'] / $totalAmount;
            $optimizedYield += $allocation['instrument']['yield'] * $weight;
        }

        return [
            'account_id'             => $accountId,
            'strategy'               => $this->determineStrategy($optimizedYield, $riskProfile),
            'current_yield'          => $currentYield,
            'expected_yield'         => $optimizedYield,
            'target_yield'           => $targetYield,
            'target_achieved'        => $optimizedYield >= $targetYield,
            'optimized_allocations'  => $optimizedAllocations,
            'optimization_timestamp' => now()->toIso8601String(),
        ];
    }

    private function optimizeAllocations(array $allocations, float $targetYield, string $riskProfile): array
    {
        $optimized = $allocations;

        // Simple optimization: shift allocation towards higher yield instruments
        // while respecting risk constraints
        if ($targetYield > 5.0 && in_array($riskProfile, ['medium', 'high'])) {
            // Increase equity allocation
            foreach ($optimized as &$allocation) {
                if ($allocation['type'] === 'equities') {
                    $allocation['percentage'] = min(70, $allocation['percentage'] * 1.2);
                } elseif ($allocation['type'] === 'cash') {
                    $allocation['percentage'] = max(10, $allocation['percentage'] * 0.8);
                }
            }
        }

        // Recalculate amounts based on new percentages
        $totalPercentage = array_sum(array_column($optimized, 'percentage'));
        foreach ($optimized as &$allocation) {
            $allocation['percentage'] = ($allocation['percentage'] / $totalPercentage) * 100;
            $allocation['optimized'] = true;
        }

        return $optimized;
    }

    private function determineStrategy(float $yield, string $riskProfile): string
    {
        if ($yield < 3.0) {
            return 'capital_preservation';
        } elseif ($yield < 5.0) {
            return 'income_generation';
        } elseif ($yield < 8.0) {
            return 'balanced_growth';
        } else {
            return 'aggressive_growth';
        }
    }
}
