<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentWallet extends Model
{
    protected $table = 'agent_wallets';

    protected $fillable = [
        'wallet_id',
        'agent_id',
        'currency',
        'available_balance',
        'held_balance',
        'total_balance',
        'daily_limit',
        'transaction_limit',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'available_balance' => 'float',
        'held_balance'      => 'float',
        'total_balance'     => 'float',
        'daily_limit'       => 'float',
        'transaction_limit' => 'float',
        'is_active'         => 'boolean',
        'metadata'          => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'agent_id', 'agent_id');
    }

    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'from_agent_id', 'agent_id');
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'to_agent_id', 'agent_id');
    }

    public function getAvailableBalanceAttribute($value): float
    {
        return (float) $value;
    }

    public function getHeldBalanceAttribute($value): float
    {
        return (float) $value;
    }

    public function getTotalBalanceAttribute($value): float
    {
        return (float) $value;
    }

    public function hasAvailableBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    public function isWithinDailyLimit(float $amount): bool
    {
        if ($this->daily_limit === null || $this->daily_limit === 0.0) {
            return true;
        }

        $todaySpent = $this->outgoingTransactions()
            ->whereDate('created_at', today())
            ->sum('amount');

        return ($todaySpent + $amount) <= $this->daily_limit;
    }

    public function isWithinTransactionLimit(float $amount): bool
    {
        if ($this->transaction_limit === null || $this->transaction_limit === 0.0) {
            return true;
        }

        return $amount <= $this->transaction_limit;
    }
}
