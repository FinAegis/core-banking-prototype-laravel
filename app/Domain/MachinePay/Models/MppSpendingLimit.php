<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MPP agent spending limit.
 *
 * Tracks per-agent daily spending and per-transaction limits
 * for AI agents making MPP payments on behalf of users.
 *
 * @property int    $id
 * @property string $agent_id
 * @property int    $daily_limit
 * @property int    $per_tx_limit
 * @property int    $spent_today
 * @property bool   $auto_pay
 * @property string $last_reset
 * @property int|null $team_id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MppSpendingLimit extends Model
{
    protected $table = 'mpp_spending_limits';

    protected $fillable = [
        'agent_id',
        'daily_limit',
        'per_tx_limit',
        'spent_today',
        'auto_pay',
        'last_reset',
        'team_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_limit'  => 'integer',
            'per_tx_limit' => 'integer',
            'spent_today'  => 'integer',
            'auto_pay'     => 'boolean',
            'team_id'      => 'integer',
            'user_id'      => 'integer',
        ];
    }

    /**
     * Remaining daily budget in cents.
     */
    public function remainingDailyBudget(): int
    {
        return max(0, $this->daily_limit - $this->spent_today);
    }
}
