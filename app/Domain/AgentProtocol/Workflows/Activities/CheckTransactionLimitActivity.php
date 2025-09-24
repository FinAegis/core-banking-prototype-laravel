<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentComplianceAggregate;
use App\Models\Agent;
use App\Models\AgentTransactionTotal;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Workflow\Activity;

class CheckTransactionLimitActivity extends Activity
{
    /**
     * Check if transaction is within agent's limits.
     */
    public function execute(
        string $agentId,
        float $amount,
        string $currency = 'USD'
    ): array {
        try {
            // Get agent's current limits from the aggregate
            $aggregate = AgentComplianceAggregate::retrieve($agentId);

            // Check if agent is KYC verified
            if (! $aggregate->isKycVerified()) {
                return [
                    'allowed'   => false,
                    'reason'    => 'Agent KYC verification required',
                    'kycStatus' => $aggregate->getKycStatus()->value,
                ];
            }

            // Get current transaction totals
            $totals = $this->getCurrentTransactionTotals($agentId);

            // Check daily limit
            $dailyLimit = $aggregate->getDailyTransactionLimit();
            $newDailyTotal = $totals['daily'] + $amount;

            if ($newDailyTotal > $dailyLimit) {
                // Record limit exceeded event
                $aggregate->recordLimitExceeded($amount, 'daily');
                $aggregate->persist();

                return [
                    'allowed'         => false,
                    'reason'          => 'Daily transaction limit exceeded',
                    'limit'           => $dailyLimit,
                    'currentTotal'    => $totals['daily'],
                    'requestedAmount' => $amount,
                    'wouldBeTotal'    => $newDailyTotal,
                    'period'          => 'daily',
                ];
            }

            // Check weekly limit
            $weeklyLimit = $aggregate->getWeeklyTransactionLimit();
            $newWeeklyTotal = $totals['weekly'] + $amount;

            if ($newWeeklyTotal > $weeklyLimit) {
                // Record limit exceeded event
                $aggregate->recordLimitExceeded($amount, 'weekly');
                $aggregate->persist();

                return [
                    'allowed'         => false,
                    'reason'          => 'Weekly transaction limit exceeded',
                    'limit'           => $weeklyLimit,
                    'currentTotal'    => $totals['weekly'],
                    'requestedAmount' => $amount,
                    'wouldBeTotal'    => $newWeeklyTotal,
                    'period'          => 'weekly',
                ];
            }

            // Check monthly limit
            $monthlyLimit = $aggregate->getMonthlyTransactionLimit();
            $newMonthlyTotal = $totals['monthly'] + $amount;

            if ($newMonthlyTotal > $monthlyLimit) {
                // Record limit exceeded event
                $aggregate->recordLimitExceeded($amount, 'monthly');
                $aggregate->persist();

                return [
                    'allowed'         => false,
                    'reason'          => 'Monthly transaction limit exceeded',
                    'limit'           => $monthlyLimit,
                    'currentTotal'    => $totals['monthly'],
                    'requestedAmount' => $amount,
                    'wouldBeTotal'    => $newMonthlyTotal,
                    'period'          => 'monthly',
                ];
            }

            // Update transaction totals (will be committed after successful payment)
            $this->updateTransactionTotals($agentId, $amount);

            return [
                'allowed'          => true,
                'reason'           => 'Transaction within limits',
                'dailyRemaining'   => $dailyLimit - $newDailyTotal,
                'weeklyRemaining'  => $weeklyLimit - $newWeeklyTotal,
                'monthlyRemaining' => $monthlyLimit - $newMonthlyTotal,
                'limits'           => [
                    'daily'   => $dailyLimit,
                    'weekly'  => $weeklyLimit,
                    'monthly' => $monthlyLimit,
                ],
                'currentTotals' => [
                    'daily'   => $newDailyTotal,
                    'weekly'  => $newWeeklyTotal,
                    'monthly' => $newMonthlyTotal,
                ],
            ];
        } catch (Exception $e) {
            logger()->error('Transaction limit check failed', [
                'agent_id' => $agentId,
                'amount'   => $amount,
                'error'    => $e->getMessage(),
            ]);

            // Conservative approach - deny on error
            return [
                'allowed' => false,
                'reason'  => 'Unable to verify transaction limits',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current transaction totals for the agent.
     */
    private function getCurrentTransactionTotals(string $agentId): array
    {
        $now = Carbon::now();

        // Get or create transaction totals record
        $totals = AgentTransactionTotal::firstOrCreate(
            ['agent_id' => $agentId],
            [
                'daily_total'        => 0,
                'weekly_total'       => 0,
                'monthly_total'      => 0,
                'last_daily_reset'   => $now->copy()->startOfDay(),
                'last_weekly_reset'  => $now->copy()->startOfWeek(),
                'last_monthly_reset' => $now->copy()->startOfMonth(),
            ]
        );

        // Check if we need to reset any periods
        if ($totals->last_daily_reset->lt($now->copy()->startOfDay())) {
            $totals->daily_total = 0;
            $totals->last_daily_reset = $now->copy()->startOfDay();
        }

        if ($totals->last_weekly_reset->lt($now->copy()->startOfWeek())) {
            $totals->weekly_total = 0;
            $totals->last_weekly_reset = $now->copy()->startOfWeek();
        }

        if ($totals->last_monthly_reset->lt($now->copy()->startOfMonth())) {
            $totals->monthly_total = 0;
            $totals->last_monthly_reset = $now->copy()->startOfMonth();
        }

        $totals->save();

        return [
            'daily'   => (float) $totals->daily_total,
            'weekly'  => (float) $totals->weekly_total,
            'monthly' => (float) $totals->monthly_total,
        ];
    }

    /**
     * Update transaction totals after successful transaction.
     */
    private function updateTransactionTotals(string $agentId, float $amount): void
    {
        DB::transaction(function () use ($agentId, $amount) {
            $totals = AgentTransactionTotal::lockForUpdate()
                ->where('agent_id', $agentId)
                ->first();

            if ($totals) {
                $totals->increment('daily_total', $amount);
                $totals->increment('weekly_total', $amount);
                $totals->increment('monthly_total', $amount);
                $totals->last_transaction_at = now();
                $totals->save();
            }
        });
    }
}
