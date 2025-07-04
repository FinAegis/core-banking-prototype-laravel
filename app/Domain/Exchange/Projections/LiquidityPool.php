<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidityPool extends Model
{
    protected $table = 'liquidity_pools';

    protected $fillable = [
        'pool_id',
        'account_id',
        'base_currency',
        'quote_currency',
        'base_reserve',
        'quote_reserve',
        'total_shares',
        'fee_rate',
        'is_active',
        'volume_24h',
        'fees_collected_24h',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function providers(): HasMany
    {
        return $this->hasMany(LiquidityProvider::class, 'pool_id', 'pool_id');
    }

    public function swaps(): HasMany
    {
        return $this->hasMany(PoolSwap::class, 'pool_id', 'pool_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency);
    }

    public function getSpotPriceAttribute(): string
    {
        if ($this->base_reserve == 0) {
            return '0';
        }
        
        return \Brick\Math\BigDecimal::of($this->quote_reserve)
            ->dividedBy($this->base_reserve, 18)
            ->__toString();
    }

    public function getTotalValueLockedAttribute(): string
    {
        // This would need exchange rates to calculate in a common currency
        return \Brick\Math\BigDecimal::of($this->base_reserve)
            ->plus($this->quote_reserve)
            ->__toString();
    }
}