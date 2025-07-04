<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidityProvider extends Model
{
    protected $table = 'liquidity_providers';

    protected $fillable = [
        'pool_id',
        'provider_id',
        'shares',
        'initial_base_amount',
        'initial_quote_amount',
        'pending_rewards',
        'total_rewards_claimed',
        'metadata',
    ];

    protected $casts = [
        'pending_rewards' => 'array',
        'metadata' => 'array',
    ];

    public function pool(): BelongsTo
    {
        return $this->belongsTo(LiquidityPool::class, 'pool_id', 'pool_id');
    }

    public function getSharePercentageAttribute(): string
    {
        $pool = $this->pool;
        if (!$pool || $pool->total_shares == 0) {
            return '0';
        }

        return \Brick\Math\BigDecimal::of($this->shares)
            ->dividedBy($pool->total_shares, 18, \Brick\Math\RoundingMode::DOWN)
            ->multipliedBy(100)
            ->toScale(6, \Brick\Math\RoundingMode::DOWN)
            ->__toString();
    }

    public function getCurrentValueAttribute(): array
    {
        $pool = $this->pool;
        if (!$pool || $pool->total_shares == 0) {
            return [
                'base_amount' => '0',
                'quote_amount' => '0',
            ];
        }

        $shareRatio = \Brick\Math\BigDecimal::of($this->shares)
            ->dividedBy($pool->total_shares, 18);

        return [
            'base_amount' => \Brick\Math\BigDecimal::of($pool->base_reserve)
                ->multipliedBy($shareRatio)
                ->__toString(),
            'quote_amount' => \Brick\Math\BigDecimal::of($pool->quote_reserve)
                ->multipliedBy($shareRatio)
                ->__toString(),
        ];
    }
}