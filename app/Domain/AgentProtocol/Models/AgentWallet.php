<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentWallet extends Model
{
    use HasFactory;

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
        'balance',
        'blockchain_address',
        'linked_account_uuid',
        'linked_at',
        'link_metadata',
    ];

    protected $casts = [
        'available_balance' => 'float',
        'held_balance'      => 'float',
        'total_balance'     => 'float',
        'balance'           => 'float',
        'daily_limit'       => 'float',
        'transaction_limit' => 'float',
        'is_active'         => 'boolean',
        'metadata'          => 'array',
        'link_metadata'     => 'array',
        'linked_at'         => 'datetime',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AgentWalletFactory::new();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'from_agent_id', 'agent_id');
    }

    public function incomingTransactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'to_agent_id', 'agent_id');
    }
}
