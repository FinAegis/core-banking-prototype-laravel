<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\VisaCli\Models\VisaCliSpendingLimit;
use Illuminate\Support\Facades\DB;

/**
 * Budget control for Visa CLI agent payments.
 */
class VisaCliSpendingLimitService
{
    /**
     * Check if an agent can spend the given amount.
     */
    public function canSpend(string $agentId, int $amountCents): bool
    {
        $limit = $this->getOrCreateLimit($agentId);

        return $limit->canSpend($amountCents);
    }

    /**
     * Check if an agent can auto-pay the given amount.
     */
    public function canAutoPay(string $agentId, int $amountCents): bool
    {
        $limit = $this->getOrCreateLimit($agentId);

        return $limit->canAutoPay($amountCents);
    }

    /**
     * Record spending for an agent with row-level locking.
     */
    public function recordSpending(string $agentId, int $amountCents): void
    {
        DB::transaction(function () use ($agentId, $amountCents): void {
            /** @var VisaCliSpendingLimit $limit */
            $limit = VisaCliSpendingLimit::where('agent_id', $agentId)
                ->lockForUpdate()
                ->first();

            if ($limit === null) {
                $limit = $this->createDefaultLimit($agentId);
            }

            $limit->recordSpending($amountCents);
        });
    }

    /**
     * Get or create a spending limit for an agent.
     */
    public function getOrCreateLimit(string $agentId): VisaCliSpendingLimit
    {
        $limit = VisaCliSpendingLimit::where('agent_id', $agentId)->first();

        if ($limit === null) {
            $limit = $this->createDefaultLimit($agentId);
        }

        return $limit;
    }

    /**
     * Update spending limits for an agent.
     */
    public function updateLimit(
        string $agentId,
        ?int $dailyLimit = null,
        ?int $perTransactionLimit = null,
        ?bool $autoPayEnabled = null,
    ): VisaCliSpendingLimit {
        $limit = $this->getOrCreateLimit($agentId);

        $updates = [];
        if ($dailyLimit !== null) {
            $updates['daily_limit'] = $dailyLimit;
        }
        if ($perTransactionLimit !== null) {
            $updates['per_transaction_limit'] = $perTransactionLimit;
        }
        if ($autoPayEnabled !== null) {
            $updates['auto_pay_enabled'] = $autoPayEnabled;
        }

        if ($updates !== []) {
            $limit->update($updates);
            $limit->refresh();
        }

        return $limit;
    }

    private function createDefaultLimit(string $agentId): VisaCliSpendingLimit
    {
        return VisaCliSpendingLimit::create([
            'agent_id'              => $agentId,
            'agent_type'            => 'ai',
            'daily_limit'           => (int) config('visacli.spending_limits.daily', 10000),
            'per_transaction_limit' => (int) config('visacli.spending_limits.per_tx', 1000),
            'auto_pay_enabled'      => (bool) config('visacli.spending_limits.auto_pay', false),
            'limit_resets_at'       => now()->addDay(),
        ]);
    }
}
