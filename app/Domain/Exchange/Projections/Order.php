<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'account_id',
        'type',
        'order_type',
        'base_currency',
        'quote_currency',
        'amount',
        'filled_amount',
        'price',
        'stop_price',
        'average_price',
        'status',
        'trades',
        'metadata',
        'cancelled_at',
        'filled_at',
    ];

    protected $casts = [
        'trades' => 'array',
        'metadata' => 'array',
        'cancelled_at' => 'datetime',
        'filled_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'account_id', 'id');
    }

    public function relatedTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buy_order_id', 'order_id')
            ->orWhere('sell_order_id', $this->order_id);
    }

    public function getPairAttribute(): string
    {
        return "{$this->base_currency}/{$this->quote_currency}";
    }

    public function getRemainingAmountAttribute(): string
    {
        return bcsub($this->amount, $this->filled_amount, 18);
    }

    public function getFilledPercentageAttribute(): float
    {
        if (bccomp($this->amount, '0', 18) === 0) {
            return 0;
        }
        
        return (float) bcmul(bcdiv($this->filled_amount, $this->amount, 18), '100', 2);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'open', 'partially_filled']);
    }

    public function isFilled(): bool
    {
        return $this->status === 'filled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeCancelled(): bool
    {
        return $this->isOpen() && bccomp($this->remaining_amount, '0', 18) > 0;
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'open', 'partially_filled']);
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency);
    }

    public function scopeForAccount($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeBuyOrders($query)
    {
        return $query->where('type', 'buy');
    }

    public function scopeSellOrders($query)
    {
        return $query->where('type', 'sell');
    }
}