<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent spending limit — controls automated Visa CLI payment budgets.
 *
 * @property string $id
 * @property string $agent_id
 * @property string $agent_type
 * @property int $daily_limit
 * @property int $spent_today
 * @property int|null $per_transaction_limit
 * @property bool $auto_pay_enabled
 * @property \Carbon\Carbon $limit_resets_at
 * @property int|null $team_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class VisaCliSpendingLimit extends Model
{
    use HasUuids;

    protected $table = 'visa_cli_spending_limits';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'spent_today'      => 0,
        'auto_pay_enabled' => false,
    ];

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
        'daily_limit'           => 'integer',
        'spent_today'           => 'integer',
        'per_transaction_limit' => 'integer',
        'auto_pay_enabled'      => 'boolean',
        'limit_resets_at'       => 'datetime',
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
     */
    public function canAutoPay(int $amount): bool
    {
        if (! $this->auto_pay_enabled) {
            return false;
        }

        if ($this->per_transaction_limit !== null && $amount > $this->per_transaction_limit) {
            return false;
        }

        return $this->canSpend($amount);
    }

    /**
     * Determine whether the daily budget has room for the given amount.
     */
    public function canSpend(int $amount): bool
    {
        $this->resetIfNeeded();

        return ($this->daily_limit - $this->spent_today) >= $amount;
    }

    /**
     * Record a spending event against today's budget.
     */
    public function recordSpending(int $amount): void
    {
        $this->resetIfNeeded();

        $this->spent_today += $amount;
        $this->save();
    }

    /**
     * Reset daily counters if the reset window has passed.
     */
    public function resetIfNeeded(): void
    {
        if ($this->limit_resets_at !== null && $this->limit_resets_at->isPast()) {
            $this->spent_today = 0;
            $this->limit_resets_at = now()->addDay();
        }
    }

    /**
     * Get the remaining daily budget in cents.
     */
    public function remainingDailyBudget(): int
    {
        $this->resetIfNeeded();

        $remaining = $this->daily_limit - $this->spent_today;

        return max(0, $remaining);
    }

    /**
     * Get the percentage of daily budget that has been spent.
     */
    public function spentPercentage(): float
    {
        if ($this->daily_limit === 0) {
            return 0.0;
        }

        return round(($this->spent_today / $this->daily_limit) * 100, 2);
    }

    // ----------------------------------------------------------------
    // API Serialization
    // ----------------------------------------------------------------

    /**
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
