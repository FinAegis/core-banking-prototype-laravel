<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTransaction extends Model
{
    protected $table = 'agent_transactions';

    protected $fillable = [
        'transaction_id',
        'from_agent_id',
        'to_agent_id',
        'amount',
        'currency',
        'fee_amount',
        'fee_type',
        'status',
        'type',
        'escrow_id',
        'metadata',
    ];

    protected $casts = [
        'amount'     => 'float',
        'fee_amount' => 'float',
        'metadata'   => 'array',
    ];

    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'from_agent_id', 'agent_id');
    }

    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'to_agent_id', 'agent_id');
    }

    public function escrow(): BelongsTo
    {
        return $this->belongsTo(Escrow::class, 'escrow_id', 'escrow_id');
    }

    public function getAmountAttribute($value): float
    {
        return (float) $value;
    }

    public function getFeeAmountAttribute($value): float
    {
        return (float) $value;
    }

    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->fee_amount;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['initiated', 'validated', 'processing'], true);
    }

    public function isEscrowTransaction(): bool
    {
        return $this->type === 'escrow' && ! empty($this->escrow_id);
    }
}
