<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Risk;

use App\Models\User;
use Workflow\Activity;

/**
 * Analyze Transaction Velocity Activity.
 *
 * Detects unusual transaction velocity patterns.
 */
class AnalyzeTransactionVelocityActivity extends Activity
{
    /**
     * Execute transaction velocity analysis.
     *
     * @param array{user_id: string, amount: float} $input
     *
     * @return array{violated: bool, severity: float, details: array<string, mixed>}
     */
    public function execute(array $input): array
    {
        $userId = $input['user_id'] ?? '';
        $amount = $input['amount'] ?? 0;

        $user = User::find($userId);
        if (! $user) {
            return [
                'violated' => false,
                'severity' => 0.0,
                'details'  => ['error' => 'User not found'],
            ];
        }

        // Analyze recent transaction patterns
        $recentTransactions = $this->getRecentTransactions($user);
        $velocityMetrics = $this->calculateVelocityMetrics($recentTransactions, $amount);

        $violated = $this->checkVelocityViolation($velocityMetrics);
        $severity = $this->calculateSeverity($velocityMetrics);

        return [
            'violated' => $violated,
            'severity' => $severity,
            'details'  => $velocityMetrics,
        ];
    }

    /**
     * @return array{hourly_count: int, daily_count: int, daily_amount: float}
     */
    private function getRecentTransactions(User $user): array
    {
        $hourlyCount = $user->transactions()
            ->where('transactions.created_at', '>=', now()->subHour())
            ->count();

        $dailyCount = $user->transactions()
            ->where('transactions.created_at', '>=', now()->subDay())
            ->count();

        $dailyAmount = (float) $user->transactions()
            ->where('transactions.created_at', '>=', now()->subDay())
            ->sum('amount');

        return [
            'hourly_count' => $hourlyCount,
            'daily_count'  => $dailyCount,
            'daily_amount' => $dailyAmount,
        ];
    }

    /**
     * @param array{hourly_count: int, daily_count: int, daily_amount: float} $recentTransactions
     * @return array{transactions_per_hour: int, transactions_per_day: int, daily_amount_total: float, current_transaction_size: float, exceeds_hourly_limit: bool, exceeds_daily_limit: bool, exceeds_amount_limit: bool}
     */
    private function calculateVelocityMetrics(array $recentTransactions, float $currentAmount): array
    {
        return [
            'transactions_per_hour'    => $recentTransactions['hourly_count'],
            'transactions_per_day'     => $recentTransactions['daily_count'],
            'daily_amount_total'       => $recentTransactions['daily_amount'],
            'current_transaction_size' => $currentAmount,
            'exceeds_hourly_limit'     => $recentTransactions['hourly_count'] > 5,
            'exceeds_daily_limit'      => $recentTransactions['daily_count'] > 20,
            'exceeds_amount_limit'     => $recentTransactions['daily_amount'] + $currentAmount > 10000,
        ];
    }

    /**
     * @param array{exceeds_hourly_limit: bool, exceeds_daily_limit: bool, exceeds_amount_limit: bool} $metrics
     */
    private function checkVelocityViolation(array $metrics): bool
    {
        return $metrics['exceeds_hourly_limit'] ||
               $metrics['exceeds_daily_limit'] ||
               $metrics['exceeds_amount_limit'];
    }

    /**
     * @param array{exceeds_hourly_limit: bool, exceeds_daily_limit: bool, exceeds_amount_limit: bool} $metrics
     */
    private function calculateSeverity(array $metrics): float
    {
        $severity = 0.0;

        if ($metrics['exceeds_hourly_limit']) {
            $severity += 0.3;
        }

        if ($metrics['exceeds_daily_limit']) {
            $severity += 0.3;
        }

        if ($metrics['exceeds_amount_limit']) {
            $severity += 0.4;
        }

        return min($severity, 1.0);
    }
}
