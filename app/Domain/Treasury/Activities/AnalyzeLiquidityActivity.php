<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use Workflow\Activity;

class AnalyzeLiquidityActivity extends Activity
{
    public function execute(array $input): array
    {
        $accountId = $input['account_id'];
        $amount = $input['amount'];

        // Analyze current liquidity position
        // In production, this would query actual treasury positions
        $currentLiquidity = $this->getCurrentLiquidity($accountId);
        $availableCash = $this->getAvailableCash($accountId);
        $commitments = $this->getUpcomingCommitments($accountId);

        $liquidityRatio = $availableCash / max($commitments, 1);
        $isHealthy = $liquidityRatio >= 1.2; // 20% buffer

        return [
            'account_id'           => $accountId,
            'current_liquidity'    => $currentLiquidity,
            'available_cash'       => $availableCash,
            'upcoming_commitments' => $commitments,
            'liquidity_ratio'      => $liquidityRatio,
            'is_healthy'           => $isHealthy,
            'max_allocatable'      => min($amount, $availableCash * 0.8), // Keep 20% reserve
            'analysis_timestamp'   => now()->toIso8601String(),
        ];
    }

    private function getCurrentLiquidity(string $accountId): float
    {
        // In production, query actual treasury balances
        // For demo, return simulated value
        return 10000000.0; // $10M
    }

    private function getAvailableCash(string $accountId): float
    {
        // In production, query actual available cash
        // For demo, return simulated value
        return 8000000.0; // $8M
    }

    private function getUpcomingCommitments(string $accountId): float
    {
        // In production, query actual commitments
        // For demo, return simulated value
        return 2000000.0; // $2M
    }
}
