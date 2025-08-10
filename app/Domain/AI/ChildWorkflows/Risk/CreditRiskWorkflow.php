<?php

declare(strict_types=1);

namespace App\Domain\AI\ChildWorkflows\Risk;

use App\Domain\AI\Activities\Risk\CalculateCreditScoreActivity;
use App\Domain\AI\Activities\Risk\CalculateDebtRatiosActivity;
use App\Domain\AI\Activities\Risk\EvaluateLoanAffordabilityActivity;
use App\Domain\AI\Events\Risk\CreditAssessedEvent;
use App\Models\User;
use Workflow\Workflow;

/**
 * Credit Risk Assessment Child Workflow.
 *
 * Orchestrates credit risk evaluation activities.
 */
class CreditRiskWorkflow extends Workflow
{
    /**
     * Execute credit risk assessment.
     *
     * @param string $conversationId
     * @param User $user
     * @param array $financialData
     * @param array $parameters
     *
     * @return \Generator
     */
    public function execute(
        string $conversationId,
        User $user,
        array $financialData,
        array $parameters
    ): \Generator {
        // Calculate credit score
        // Prepare credit score inputs from financial data
        $paymentHistory = $this->extractPaymentHistory($financialData);
        $creditUtilization = $this->calculateCreditUtilization($financialData);
        $creditMix = $this->extractCreditMix($financialData);

        $creditScore = yield app(CalculateCreditScoreActivity::class)->execute([
            'user_id'            => (string) $user->id,
            'payment_history'    => $paymentHistory,
            'credit_utilization' => $creditUtilization,
            'credit_mix'         => $creditMix,
        ]);

        // Calculate debt ratios
        $debtRatios = yield app(CalculateDebtRatiosActivity::class)->execute([
            'financial_data' => $financialData,
        ]);

        // Evaluate loan affordability if loan parameters provided
        $affordability = null;
        if (isset($parameters['loan_amount'])) {
            $affordability = yield app(EvaluateLoanAffordabilityActivity::class)->execute([
                'loan_amount'    => $parameters['loan_amount'],
                'loan_term'      => $parameters['loan_term'] ?? 12,
                'monthly_income' => $debtRatios['monthly_income'],
                'credit_score'   => $creditScore['score'],
            ]);
        }

        // Compile assessment
        $assessment = [
            'credit_score'    => $creditScore['score'],
            'credit_rating'   => $creditScore['rating'],
            'dti_ratio'       => $debtRatios['dti_ratio'],
            'monthly_income'  => $debtRatios['monthly_income'],
            'monthly_debt'    => $debtRatios['monthly_debt'],
            'affordability'   => $affordability,
            'risk_level'      => $this->determineRiskLevel($creditScore['score'], $debtRatios['dti_ratio']),
            'approved'        => $this->isApproved($creditScore['score'], $debtRatios['dti_ratio']),
            'max_loan_amount' => $this->calculateMaxLoanAmount($debtRatios['monthly_income'], $creditScore['score']),
        ];

        // Emit event
        event(new CreditAssessedEvent(
            $conversationId,
            (string) $user->id,
            $assessment
        ));

        return $assessment;
    }

    private function determineRiskLevel(int $creditScore, float $dtiRatio): string
    {
        $riskScore = 0;

        // Credit score component
        $riskScore += match (true) {
            $creditScore >= 750 => 0,
            $creditScore >= 650 => 20,
            $creditScore >= 550 => 50,
            default             => 80
        };

        // DTI component
        $riskScore += min($dtiRatio * 50, 50);

        return match (true) {
            $riskScore < 30 => 'low',
            $riskScore < 60 => 'medium',
            default         => 'high'
        };
    }

    private function isApproved(int $creditScore, float $dtiRatio): bool
    {
        return $creditScore >= 650 && $dtiRatio < 0.4;
    }

    private function calculateMaxLoanAmount(float $monthlyIncome, int $creditScore): float
    {
        $baseMultiplier = match (true) {
            $creditScore >= 750 => 5,
            $creditScore >= 650 => 4,
            $creditScore >= 550 => 3,
            default             => 2
        };

        return $monthlyIncome * $baseMultiplier * 12;
    }

    private function extractPaymentHistory(array $financialData): array
    {
        // Extract payment history from transactions
        $transactions = $financialData['transactions'] ?? collect();
        $history = [];

        if ($transactions instanceof \Illuminate\Support\Collection) {
            foreach ($transactions->take(12) as $transaction) {
                $history[] = [
                    'on_time' => true, // Simplified - would check actual payment dates
                    'amount'  => $transaction->amount ?? 0,
                ];
            }
        }

        return $history;
    }

    private function calculateCreditUtilization(array $financialData): float
    {
        // Calculate credit utilization ratio
        $totalCredit = $financialData['total_credit'] ?? 10000.0; // Get from credit accounts or use default
        $usedCredit = $financialData['total_debt'] ?? 0;

        return $totalCredit > 0 ? ($usedCredit / $totalCredit) : 0.0;
    }

    private function extractCreditMix(array $financialData): array
    {
        // Extract types of credit accounts
        return [
            'credit_cards' => 2,
            'loans'        => 1,
            'mortgages'    => 0,
        ];
    }
}
