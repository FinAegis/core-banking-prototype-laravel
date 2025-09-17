<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Escrow extends Model
{
    protected $table = 'escrows';

    protected $fillable = [
        'escrow_id',
        'transaction_id',
        'sender_agent_id',
        'receiver_agent_id',
        'amount',
        'currency',
        'funded_amount',
        'conditions',
        'expires_at',
        'status',
        'is_disputed',
        'released_at',
        'released_by',
        'metadata',
    ];

    protected $casts = [
        'amount'        => 'float',
        'funded_amount' => 'float',
        'conditions'    => 'array',
        'is_disputed'   => 'boolean',
        'metadata'      => 'array',
        'expires_at'    => 'datetime',
        'released_at'   => 'datetime',
    ];

    public function senderAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'sender_agent_id', 'agent_id');
    }

    public function receiverAgent(): BelongsTo
    {
        return $this->belongsTo(AgentIdentity::class, 'receiver_agent_id', 'agent_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AgentTransaction::class, 'transaction_id', 'transaction_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(EscrowDispute::class, 'escrow_id', 'escrow_id');
    }

    public function getAmountAttribute($value): float
    {
        return (float) $value;
    }

    public function getFundedAmountAttribute($value): float
    {
        return (float) $value;
    }

    public function isFullyFunded(): bool
    {
        return $this->funded_amount >= $this->amount;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canBeReleased(): bool
    {
        return in_array($this->status, ['funded', 'resolved'], true) && $this->isFullyFunded();
    }

    public function canBeDisputed(): bool
    {
        return $this->status === 'funded' && ! $this->is_disputed;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['released', 'cancelled'], true);
    }

    public function getConditionsSummary(): array
    {
        if (empty($this->conditions)) {
            return [];
        }

        return array_map(function ($condition) {
            return [
                'type'        => $condition['type'] ?? 'custom',
                'description' => $condition['description'] ?? '',
                'met'         => $condition['met'] ?? false,
            ];
        }, $this->conditions);
    }
}
