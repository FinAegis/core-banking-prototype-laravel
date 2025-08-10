<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use Workflow\Activity;

/**
 * Calculate Debt Ratios Activity.
 *
 * Calculates debt-to-income ratio and related metrics.
 */
class CalculateDebtRatiosActivity extends Activity
{
    /**
     * Execute debt ratio calculation.
     *
     * @param array{financial_data: array} $input
     *
     * @return array{dti_ratio: float, monthly_income: float, monthly_debt: float}
     */
    public function execute(array $input): array
    {
        $financialData = $input['financial_data'] ?? [];

        $monthlyIncome = $this->calculateMonthlyIncome($financialData);
        $monthlyDebt = $this->calculateMonthlyDebt($financialData);

        $dtiRatio = $monthlyIncome > 0 ? ($monthlyDebt / $monthlyIncome) : 1.0;

        return [
            'dti_ratio'      => round($dtiRatio, 3),
            'monthly_income' => $monthlyIncome,
            'monthly_debt'   => $monthlyDebt,
        ];
    }

    private function calculateMonthlyIncome(array $financialData): float
    {
        // Calculate from recent transactions
        $transactions = $financialData['transactions'] ?? collect();

        if ($transactions instanceof \Illuminate\Support\Collection) {
            return (float) $transactions
                ->where('type', 'credit')
                ->where('created_at', '>=', now()->subMonth())
                ->sum('amount');
        }

        return 0.0;
    }

    private function calculateMonthlyDebt(array $financialData): float
    {
        // Sum of all loan monthly payments
        $loans = $financialData['loans'] ?? collect();

        if ($loans instanceof \Illuminate\Support\Collection) {
            return (float) $loans->sum('monthly_payment');
        }

        return 0.0;
    }
}
