<?php

namespace App\Domain\Exchange\Projections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrderBook extends Model
{
    protected $fillable = [
        'order_book_id',
        'base_currency',
        'quote_currency',
        'buy_orders',
        'sell_orders',
        'best_bid',
        'best_ask',
        'last_price',
        'volume_24h',
        'high_24h',
        'low_24h',
        'metadata',
    ];

    protected $casts = [
        'buy_orders' => 'array',
        'sell_orders' => 'array',
        'metadata' => 'array',
    ];

    public function getPairAttribute(): string
    {
        return "{$this->base_currency}/{$this->quote_currency}";
    }

    public function getSpreadAttribute(): ?string
    {
        if ($this->best_bid === null || $this->best_ask === null) {
            return null;
        }

        return bcsub($this->best_ask, $this->best_bid, 18);
    }

    public function getSpreadPercentageAttribute(): ?float
    {
        if ($this->best_bid === null || $this->best_ask === null || bccomp($this->best_bid, '0', 18) === 0) {
            return null;
        }

        $spread = $this->spread;
        return (float) bcmul(bcdiv($spread, $this->best_bid, 18), '100', 4);
    }

    public function getMidPriceAttribute(): ?string
    {
        if ($this->best_bid === null || $this->best_ask === null) {
            return null;
        }

        return bcdiv(bcadd($this->best_bid, $this->best_ask, 18), '2', 18);
    }

    public function getChange24hAttribute(): ?string
    {
        $openPrice = $this->metadata['open_24h'] ?? null;
        
        if ($openPrice === null || $this->last_price === null) {
            return null;
        }

        return bcsub($this->last_price, $openPrice, 18);
    }

    public function getChange24hPercentageAttribute(): ?float
    {
        $openPrice = $this->metadata['open_24h'] ?? null;
        
        if ($openPrice === null || $this->last_price === null || bccomp($openPrice, '0', 18) === 0) {
            return null;
        }

        $change = $this->change_24h;
        return (float) bcmul(bcdiv($change, $openPrice, 18), '100', 2);
    }

    public function getBuyOrdersCollectionAttribute(): Collection
    {
        return collect($this->buy_orders);
    }

    public function getSellOrdersCollectionAttribute(): Collection
    {
        return collect($this->sell_orders);
    }

    public function getDepth(int $levels = 10): array
    {
        return [
            'bids' => $this->buy_orders_collection->take($levels)->values()->toArray(),
            'asks' => $this->sell_orders_collection->take($levels)->values()->toArray(),
        ];
    }

    public function getTotalBidVolume(int $levels = null): string
    {
        $orders = $levels ? $this->buy_orders_collection->take($levels) : $this->buy_orders_collection;
        
        return $orders->reduce(function ($carry, $order) {
            return bcadd($carry, $order['amount'], 18);
        }, '0');
    }

    public function getTotalAskVolume(int $levels = null): string
    {
        $orders = $levels ? $this->sell_orders_collection->take($levels) : $this->sell_orders_collection;
        
        return $orders->reduce(function ($carry, $order) {
            return bcadd($carry, $order['amount'], 18);
        }, '0');
    }

    public function scopeForPair($query, string $baseCurrency, string $quoteCurrency)
    {
        return $query->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency);
    }

    public static function findOrCreateForPair(string $baseCurrency, string $quoteCurrency): self
    {
        return static::firstOrCreate(
            [
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
            ],
            [
                'order_book_id' => \Illuminate\Support\Str::uuid()->toString(),
                'buy_orders' => [],
                'sell_orders' => [],
                'metadata' => [],
            ]
        );
    }
}