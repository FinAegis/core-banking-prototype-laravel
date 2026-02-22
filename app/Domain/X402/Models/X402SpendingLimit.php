<?php

declare(strict_types=1);

namespace App\Domain\X402\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent spending limit — controls automated x402 payment budgets.
 *
 * @property string $id
 * @property string $agent_id
 * @property string $agent_type
 * @property numeric-string $daily_limit
 * @property numeric-string $spent_today
 * @property numeric-string|null $per_transaction_limit
 * @property bool $auto_pay_enabled
 * @property \Carbon\Carbon $limit_resets_at
 * @property int|null $team_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class X402SpendingLimit extends Model
{
    use HasUuids;

    protected $table = 'x402_spending_limits';

    protected $fillable = [
        'agent_id',
        'agent_type',
        'daily_limit',
        'spent_today',
        'per_transaction_limit',
        'auto_pay_enabled',
        'limit_resets_at',
        'team_id',
    ];

    protected $casts = [
        'auto_pay_enabled' => 'boolean',
        'limit_resets_at'  => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Determine whether this agent can auto-pay the given amount.
     *
     * Checks auto_pay flag, per-transaction limit, and daily budget.
     *
     * @param numeric-string $amount Amount in atomic units.
     */
    public function canAutoPay(string $amount): bool
    {
        if (! $this->auto_pay_enabled) {
            return false;
        }

        if ($this->per_transaction_limit !== null && bccomp($amount, $this->per_transaction_limit) > 0) {
            return false;
        }

        return $this->canSpend($amount);
    }

    /**
     * Determine whether the daily budget has room for the given amount.
     *
     * @param numeric-string $amount Amount in atomic units.
     */
    public function canSpend(string $amount): bool
    {
        $this->resetIfNeeded();

        $remaining = bcsub($this->daily_limit, $this->spent_today);

        return bccomp($remaining, $amount) >= 0;
    }

    /**
     * Record a spending event against today's budget.
     *
     * @param numeric-string $amount Amount in atomic units.
     */
    public function recordSpending(string $amount): void
    {
        $this->resetIfNeeded();

        $this->spent_today = bcadd($this->spent_today, $amount);
        $this->save();
    }

    /**
     * Reset daily counters if the reset window has passed.
     *
     * Note: Does not persist automatically — caller is responsible for save().
     * This prevents redundant database writes when called multiple times
     * within a single transaction.
     */
    public function resetIfNeeded(): void
    {
        if ($this->limit_resets_at->isPast()) {
            $this->spent_today = '0';
            $this->limit_resets_at = now()->addDay();
        }
    }

    /**
     * Get the remaining daily budget in atomic units.
     */
    public function remainingDailyBudget(): string
    {
        $this->resetIfNeeded();

        $remaining = bcsub($this->daily_limit, $this->spent_today);

        return bccomp($remaining, '0') < 0 ? '0' : $remaining;
    }

    /**
     * Get the percentage of daily budget that has been spent.
     */
    public function spentPercentage(): float
    {
        if (bccomp($this->daily_limit ?? '0', '0') === 0) {
            return 0.0;
        }

        return (float) bcmul(
            bcdiv($this->spent_today, $this->daily_limit, 6),
            '100',
            2,
        );
    }

    // ----------------------------------------------------------------
    // API Serialization
    // ----------------------------------------------------------------

    /**
     * Format for API response.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'                  => $this->id,
            'agentId'             => $this->agent_id,
            'agentType'           => $this->agent_type,
            'dailyLimit'          => $this->daily_limit,
            'spentToday'          => $this->spent_today,
            'perTransactionLimit' => $this->per_transaction_limit,
            'autoPayEnabled'      => $this->auto_pay_enabled,
            'remainingBudget'     => $this->remainingDailyBudget(),
            'spentPercentage'     => $this->spentPercentage(),
            'limitResetsAt'       => $this->limit_resets_at->toIso8601String(),
            'createdAt'           => $this->created_at->toIso8601String(),
            'updatedAt'           => $this->updated_at->toIso8601String(),
        ];
    }
}
