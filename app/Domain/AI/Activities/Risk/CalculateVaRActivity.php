<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use Workflow\Activity;

/**
 * Calculate Value at Risk (VaR) Activity.
 *
 * Atomic activity for calculating portfolio VaR.
 */
class CalculateVaRActivity extends Activity
{
    /**
     * Execute VaR calculation.
     *
     * @param array{returns: array<float>, confidence_level: float, holding_period: int} $input
     *
     * @return array{var: float, cvar: float, risk_level: string}
     */
    public function execute(array $input): array
    {
        $returns = $input['returns'] ?? [];
        $confidenceLevel = $input['confidence_level'] ?? 0.95;
        $holdingPeriod = $input['holding_period'] ?? 1;

        if (empty($returns)) {
            return [
                'var'        => 0.0,
                'cvar'       => 0.0,
                'risk_level' => 'unknown',
            ];
        }

        // Sort returns in ascending order
        sort($returns);

        // Calculate VaR using historical simulation
        $index = (int) ((1 - $confidenceLevel) * count($returns));
        $var = abs($returns[$index] ?? 0);

        // Calculate Conditional VaR (Expected Shortfall)
        $tailReturns = array_slice($returns, 0, max(1, $index));
        $cvar = empty($tailReturns) ? $var : abs(array_sum($tailReturns) / count($tailReturns));

        // Adjust for holding period
        $var *= sqrt($holdingPeriod);
        $cvar *= sqrt($holdingPeriod);

        $riskLevel = $this->determineRiskLevel($var, $cvar);

        return [
            'var'        => round($var, 4),
            'cvar'       => round($cvar, 4),
            'risk_level' => $riskLevel,
        ];
    }

    private function determineRiskLevel(float $var, float $cvar): string
    {
        $avgRisk = ($var + $cvar) / 2;

        return match (true) {
            $avgRisk < 0.02 => 'low',
            $avgRisk < 0.05 => 'moderate',
            $avgRisk < 0.10 => 'high',
            default         => 'very_high',
        };
    }
}
