<?php

namespace App\Domain\Lending\Services;

use App\Models\User;
use InvalidArgumentException;

class MockCreditScoringService implements CreditScoringService
{
    public function getScore(string $borrowerId): array
    {
        $user = User::find($borrowerId);

        if (! $user) {
            throw new InvalidArgumentException("User not found: {$borrowerId}");
        }

        // Mock credit score based on user attributes
        // In production, this would integrate with actual credit bureaus
        $baseScore = 650;

        // Adjust based on account age
        $accountAge = $user->created_at->diffInMonths(now());
        if ($accountAge > 24) {
            $baseScore += 50;
        } elseif ($accountAge > 12) {
            $baseScore += 25;
        }

        // Adjust based on KYC status
        if ($user->kyc_status === 'approved') {
            $baseScore += 30;
        }

        // Add some randomness for testing
        $variance = rand(-50, 50);
        $score = max(300, min(850, $baseScore + $variance));

        return [
            'score'  => $score,
            'bureau' => 'MockBureau',
            'report' => [
                'inquiries'         => rand(0, 5),
                'openAccounts'      => rand(1, 10),
                'totalDebt'         => rand(0, 50000),
                'paymentHistory'    => $this->generatePaymentHistory(),
                'creditUtilization' => rand(10, 90) / 100,
            ],
        ];
    }

    public function meetsMinimumRequirements(string $borrowerId, int $minimumScore = 600): bool
    {
        $score = $this->getScore($borrowerId);

        return $score['score'] >= $minimumScore;
    }

    public function getCreditHistory(string $borrowerId): array
    {
        // Mock credit history
        return [
            'scores' => [
                ['date' => now()->subMonths(6)->toDateString(), 'score' => rand(600, 750)],
                ['date' => now()->subMonths(3)->toDateString(), 'score' => rand(600, 750)],
                ['date' => now()->toDateString(), 'score' => $this->getScore($borrowerId)['score']],
            ],
            'accounts' => [
                [
                    'type'    => 'credit_card',
                    'status'  => 'active',
                    'limit'   => rand(1000, 10000),
                    'balance' => rand(0, 5000),
                ],
                [
                    'type'           => 'auto_loan',
                    'status'         => 'closed',
                    'originalAmount' => rand(10000, 30000),
                    'paidOff'        => true,
                ],
            ],
        ];
    }

    private function generatePaymentHistory(): array
    {
        $history = [];
        for ($i = 0; $i < 12; $i++) {
            $history[] = [
                'month'  => now()->subMonths($i)->format('Y-m'),
                'status' => rand(1, 100) > 10 ? 'on_time' : 'late',
            ];
        }

        return $history;
    }
}
