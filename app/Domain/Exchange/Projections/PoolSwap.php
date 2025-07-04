<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolSwap extends Model
{
    protected $table = 'pool_swaps';

    protected $fillable = [
        'swap_id',
        'pool_id',
        'account_id',
        'input_currency',
        'input_amount',
        'output_currency',
        'output_amount',
        'fee_amount',
        'price_impact',
        'execution_price',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(LiquidityPool::class, 'pool_id', 'pool_id');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where(function ($q) use ($baseCurrency, $quoteCurrency) {
            $q->where('input_currency', $baseCurrency)
                ->where('output_currency', $quoteCurrency)
                ->orWhere(function ($q2) use ($baseCurrency, $quoteCurrency) {
                    $q2->where('input_currency', $quoteCurrency)
                        ->where('output_currency', $baseCurrency);
                });
        });
    }
}