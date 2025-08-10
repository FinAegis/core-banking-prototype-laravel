<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use App\Models\User;
use Workflow\Activity;

/**
 * Calculate Credit Score Activity.
 *
 * Atomic activity for calculating user credit scores.
 */
class CalculateCreditScoreActivity extends Activity
{
    /**
     * Execute credit score calculation.
     *
     * @param array{user_id: string, payment_history: array, credit_utilization: float, credit_mix: array} $input
     *
     * @return array{score: int, rating: string, factors: array}
     */
    public function execute(array $input): array
    {
        $userId = $input['user_id'] ?? '';
        $paymentHistory = $input['payment_history'] ?? [];
        $creditUtilization = $input['credit_utilization'] ?? 0.0;
        $creditMix = $input['credit_mix'] ?? [];

        $score = 300; // Base score

        // Payment history (35% weight)
        $paymentScore = $this->calculatePaymentHistoryScore($paymentHistory);
        $score += (int) ($paymentScore * 3.5);

        // Credit utilization (30% weight)
        $utilizationScore = $this->calculateUtilizationScore($creditUtilization);
        $score += (int) ($utilizationScore * 3.0);

        // Length of credit history (15% weight)
        $historyLength = count($paymentHistory);
        $historyScore = min(100, $historyLength * 10);
        $score += (int) ($historyScore * 1.5);

        // Credit mix (10% weight)
        $mixScore = $this->calculateCreditMixScore($creditMix);
        $score += (int) ($mixScore * 1.0);

        // New credit (10% weight)
        $newCreditScore = $this->calculateNewCreditScore($creditMix);
        $score += (int) ($newCreditScore * 1.0);

        // Ensure score is within valid range
        $score = max(300, min(850, $score));

        return [
            'score'   => $score,
            'rating'  => $this->getRating($score),
            'factors' => [
                'payment_history'    => $paymentScore,
                'credit_utilization' => $utilizationScore,
                'history_length'     => $historyScore,
                'credit_mix'         => $mixScore,
                'new_credit'         => $newCreditScore,
            ],
        ];
    }

    private function calculatePaymentHistoryScore(array $paymentHistory): float
    {
        if (empty($paymentHistory)) {
            return 50.0;
        }

        $onTimePayments = array_filter($paymentHistory, fn ($p) => $p['status'] === 'on_time');
        $ratio = count($onTimePayments) / count($paymentHistory);

        return $ratio * 100;
    }

    private function calculateUtilizationScore(float $utilization): float
    {
        return match (true) {
            $utilization < 0.1 => 100,
            $utilization < 0.3 => 90,
            $utilization < 0.5 => 70,
            $utilization < 0.7 => 50,
            $utilization < 0.9 => 30,
            default            => 10,
        };
    }

    private function calculateCreditMixScore(array $creditMix): float
    {
        $types = array_unique(array_column($creditMix, 'type'));
        $diversity = count($types);

        return min(100, $diversity * 25);
    }

    private function calculateNewCreditScore(array $creditMix): float
    {
        $recentAccounts = array_filter($creditMix, function ($account) {
            $opened = strtotime($account['opened_date'] ?? 'now');
            $threeMonthsAgo = strtotime('-3 months');

            return $opened > $threeMonthsAgo;
        });

        $recentCount = count($recentAccounts);

        return match (true) {
            $recentCount === 0 => 100,
            $recentCount === 1 => 90,
            $recentCount === 2 => 70,
            $recentCount === 3 => 50,
            default            => 30,
        };
    }

    private function getRating(int $score): string
    {
        return match (true) {
            $score >= 800 => 'excellent',
            $score >= 740 => 'very_good',
            $score >= 670 => 'good',
            $score >= 580 => 'fair',
            default       => 'poor',
        };
    }
}
