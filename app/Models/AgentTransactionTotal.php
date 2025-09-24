<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $agent_id
 * @property float $daily_total
 * @property float $weekly_total
 * @property float $monthly_total
 * @property Carbon $last_daily_reset
 * @property Carbon $last_weekly_reset
 * @property Carbon $last_monthly_reset
 * @property Carbon|null $last_transaction_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AgentTransactionTotal extends Model
{
    use HasFactory;

    protected $table = 'agent_transaction_totals';

    protected $fillable = [
        'agent_id',
        'daily_total',
        'weekly_total',
        'monthly_total',
        'last_daily_reset',
        'last_weekly_reset',
        'last_monthly_reset',
        'last_transaction_at',
    ];

    protected $casts = [
        'daily_total'         => 'decimal:2',
        'weekly_total'        => 'decimal:2',
        'monthly_total'       => 'decimal:2',
        'last_daily_reset'    => 'datetime',
        'last_weekly_reset'   => 'datetime',
        'last_monthly_reset'  => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    /**
     * Get the agent that owns the transaction totals.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Check if daily totals need reset.
     */
    public function needsDailyReset(): bool
    {
        return $this->last_daily_reset->lt(now()->startOfDay());
    }

    /**
     * Check if weekly totals need reset.
     */
    public function needsWeeklyReset(): bool
    {
        return $this->last_weekly_reset->lt(now()->startOfWeek());
    }

    /**
     * Check if monthly totals need reset.
     */
    public function needsMonthlyReset(): bool
    {
        return $this->last_monthly_reset->lt(now()->startOfMonth());
    }

    /**
     * Reset daily totals.
     */
    public function resetDaily(): void
    {
        $this->daily_total = 0;
        $this->last_daily_reset = now()->startOfDay();
        $this->save();
    }

    /**
     * Reset weekly totals.
     */
    public function resetWeekly(): void
    {
        $this->weekly_total = 0;
        $this->last_weekly_reset = now()->startOfWeek();
        $this->save();
    }

    /**
     * Reset monthly totals.
     */
    public function resetMonthly(): void
    {
        $this->monthly_total = 0;
        $this->last_monthly_reset = now()->startOfMonth();
        $this->save();
    }

    /**
     * Add transaction amount to all periods.
     */
    public function addTransaction(float $amount): void
    {
        $this->daily_total += $amount;
        $this->weekly_total += $amount;
        $this->monthly_total += $amount;
        $this->last_transaction_at = now();
        $this->save();
    }

    /**
     * Get remaining daily limit.
     */
    public function getRemainingDailyLimit(float $limit): float
    {
        return max(0, $limit - $this->daily_total);
    }

    /**
     * Get remaining weekly limit.
     */
    public function getRemainingWeeklyLimit(float $limit): float
    {
        return max(0, $limit - $this->weekly_total);
    }

    /**
     * Get remaining monthly limit.
     */
    public function getRemainingMonthlyLimit(float $limit): float
    {
        return max(0, $limit - $this->monthly_total);
    }
}
