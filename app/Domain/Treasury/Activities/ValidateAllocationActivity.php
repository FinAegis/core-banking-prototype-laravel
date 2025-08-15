<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use Workflow\Activity;

class ValidateAllocationActivity extends Activity
{
    public function execute(array $input): array
    {
        $strategy = $input['strategy'];
        $amount = $input['amount'];
        $liquidity = $input['liquidity'];
        $constraints = $input['constraints'] ?? [];

        // Validate allocation against constraints
        $isValid = true;
        $validationErrors = [];

        // Check liquidity constraints
        if ($amount > $liquidity['max_allocatable']) {
            $isValid = false;
            $validationErrors[] = sprintf(
                'Amount exceeds max allocatable: $%.2f > $%.2f',
                $amount,
                $liquidity['max_allocatable']
            );
        }

        // Check minimum allocation threshold
        $minAllocation = $constraints['min_allocation'] ?? 100000; // $100K minimum
        if ($amount < $minAllocation) {
            $isValid = false;
            $validationErrors[] = sprintf(
                'Amount below minimum allocation: $%.2f < $%.2f',
                $amount,
                $minAllocation
            );
        }

        // Calculate risk score based on strategy
        $riskScore = $this->calculateRiskScore($strategy, $amount, $liquidity);
        $riskProfile = RiskProfile::fromScore($riskScore);

        // Check risk constraints
        if ($riskProfile->requiresApproval() && ! ($constraints['approved'] ?? false)) {
            $isValid = false;
            $validationErrors[] = 'High risk allocation requires approval';
        }

        return [
            'is_valid'        => $isValid,
            'reason'          => implode('; ', $validationErrors),
            'risk_score'      => $riskScore,
            'risk_profile'    => $riskProfile->getLevel(),
            'risk_factors'    => $this->getRiskFactors($strategy, $amount, $liquidity),
            'recommendations' => $this->getRecommendations($strategy, $riskProfile),
        ];
    }

    private function calculateRiskScore(string $strategy, float $amount, array $liquidity): float
    {
        $baseScore = match ($strategy) {
            AllocationStrategy::CONSERVATIVE => 20.0,
            AllocationStrategy::BALANCED     => 45.0,
            AllocationStrategy::AGGRESSIVE   => 70.0,
            default                          => 50.0,
        };

        // Adjust based on liquidity ratio
        if ($liquidity['liquidity_ratio'] < 1.5) {
            $baseScore += 15.0;
        }

        // Adjust based on amount size
        if ($amount > 5000000) { // Large allocation
            $baseScore += 10.0;
        }

        return min(100.0, $baseScore);
    }

    private function getRiskFactors(string $strategy, float $amount, array $liquidity): array
    {
        $factors = [];

        if ($liquidity['liquidity_ratio'] < 1.5) {
            $factors[] = 'Low liquidity buffer';
        }

        if ($amount > 5000000) {
            $factors[] = 'Large allocation size';
        }

        if ($strategy === AllocationStrategy::AGGRESSIVE) {
            $factors[] = 'Aggressive strategy selected';
        }

        if (! $liquidity['is_healthy']) {
            $factors[] = 'Unhealthy liquidity position';
        }

        return $factors;
    }

    private function getRecommendations(string $strategy, RiskProfile $riskProfile): array
    {
        $recommendations = [];

        if ($riskProfile->getScore() > 60) {
            $recommendations[] = 'Consider more conservative allocation';
            $recommendations[] = 'Increase liquidity buffer';
        }

        if ($strategy === AllocationStrategy::AGGRESSIVE) {
            $recommendations[] = 'Monitor positions daily';
            $recommendations[] = 'Set stop-loss limits';
        }

        $recommendations[] = 'Review allocation quarterly';
        $recommendations[] = 'Maintain minimum 20% cash reserve';

        return $recommendations;
    }
}
