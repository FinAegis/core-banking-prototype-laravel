<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'trade_id',
        'buy_order_id',
        'sell_order_id',
        'buyer_account_id',
        'seller_account_id',
        'base_currency',
        'quote_currency',
        'price',
        'amount',
        'value',
        'maker_fee',
        'taker_fee',
        'maker_side',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buy_order_id', 'order_id');
    }

    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sell_order_id', 'order_id');
    }

    public function buyerAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'buyer_account_id', 'id');
    }

    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'seller_account_id', 'id');
    }

    public function getPairAttribute(): string
    {
        return "{$this->base_currency}/{$this->quote_currency}";
    }

    public function getBuyerFeeAttribute(): string
    {
        return $this->maker_side === 'buy' ? $this->maker_fee : $this->taker_fee;
    }

    public function getSellerFeeAttribute(): string
    {
        return $this->maker_side === 'sell' ? $this->maker_fee : $this->taker_fee;
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency);
    }

    public function scopeForAccount($query, string $accountId)
    {
        return $query->where(function ($q) use ($accountId) {
            $q->where('buyer_account_id', $accountId)
                ->orWhere('seller_account_id', $accountId);
        });
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}