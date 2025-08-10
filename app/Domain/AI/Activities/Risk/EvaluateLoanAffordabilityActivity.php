<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use Workflow\Activity;

/**
 * Evaluate Loan Affordability Activity.
 *
 * Determines if a loan is affordable based on financial metrics.
 */
class EvaluateLoanAffordabilityActivity extends Activity
{
    /**
     * Execute loan affordability evaluation.
     *
     * @param array{loan_amount: float, loan_term: int, monthly_income: float, credit_score: int} $input
     *
     * @return array{affordable: bool, monthly_payment: float, affordability_ratio: float}
     */
    public function execute(array $input): array
    {
        $loanAmount = $input['loan_amount'] ?? 0;
        $loanTerm = $input['loan_term'] ?? 12;
        $monthlyIncome = $input['monthly_income'] ?? 0;
        $creditScore = $input['credit_score'] ?? 600;

        $monthlyPayment = $this->calculateMonthlyPayment($loanAmount, $loanTerm, $creditScore);
        $affordabilityRatio = $monthlyIncome > 0 ? ($monthlyPayment / $monthlyIncome) : 1.0;

        // Generally, a payment should not exceed 28% of monthly income
        $affordable = $affordabilityRatio <= 0.28;

        return [
            'affordable'          => $affordable,
            'monthly_payment'     => round($monthlyPayment, 2),
            'affordability_ratio' => round($affordabilityRatio, 3),
        ];
    }

    private function calculateMonthlyPayment(float $loanAmount, int $termMonths, int $creditScore): float
    {
        if ($termMonths === 0 || $loanAmount < 0.01) {
            return 0;
        }

        // Interest rate based on credit score
        $annualRate = $this->getInterestRate($creditScore);
        $monthlyRate = $annualRate / 12;

        // PMT formula
        return ($loanAmount * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$termMonths));
    }

    private function getInterestRate(int $creditScore): float
    {
        return match (true) {
            $creditScore >= 750 => 0.03,  // 3% APR
            $creditScore >= 700 => 0.04,  // 4% APR
            $creditScore >= 650 => 0.05,  // 5% APR
            $creditScore >= 600 => 0.07,  // 7% APR
            default             => 0.10,               // 10% APR
        };
    }
}
