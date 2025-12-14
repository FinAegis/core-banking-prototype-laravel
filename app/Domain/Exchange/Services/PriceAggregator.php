<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\PriceAggregatorInterface;
use Illuminate\Support\Facades\Cache;

class PriceAggregator implements PriceAggregatorInterface
{
    /**
     * Supported exchanges for price aggregation.
     */
    private const EXCHANGES = ['binance', 'kraken', 'coinbase', 'internal'];

    /**
     * Base prices for common trading pairs.
     */
    private const BASE_PRICES = [
        'BTC/EUR' => 45000.0,
        'BTC/USD' => 48000.0,
        'ETH/EUR' => 2500.0,
        'ETH/USD' => 2650.0,
        'ETH/BTC' => 0.055,
        'XRP/EUR' => 0.52,
        'XRP/USD' => 0.55,
        'SOL/EUR' => 95.0,
        'SOL/USD' => 100.0,
    ];

    public function getAggregatedPrice(string $symbol): array
    {
        $cacheKey = "price:aggregated:{$symbol}";

        return Cache::remember($cacheKey, 15, function () use ($symbol) {
            $prices = $this->getPricesAcrossExchanges($symbol);

            if (empty($prices)) {
                return [
                    'symbol'    => $symbol,
                    'average'   => 0,
                    'min'       => 0,
                    'max'       => 0,
                    'exchanges' => [],
                ];
            }

            $priceValues = array_column($prices, 'price');

            return [
                'symbol'     => $symbol,
                'average'    => round(array_sum($priceValues) / count($priceValues), 8),
                'min'        => round(min($priceValues), 8),
                'max'        => round(max($priceValues), 8),
                'spread'     => round(max($priceValues) - min($priceValues), 8),
                'exchanges'  => $prices,
                'updated_at' => now()->toIso8601String(),
            ];
        });
    }

    public function getBestBid(string $symbol): array
    {
        $prices = $this->getPricesAcrossExchanges($symbol);

        if (empty($prices)) {
            return [
                'exchange' => null,
                'price'    => 0,
                'amount'   => 0,
            ];
        }

        // Find the highest bid (best for sellers)
        $bestBid = null;
        foreach ($prices as $exchangePrice) {
            if ($bestBid === null || $exchangePrice['bid'] > $bestBid['price']) {
                $bestBid = [
                    'exchange' => $exchangePrice['exchange'],
                    'price'    => $exchangePrice['bid'],
                    'amount'   => $exchangePrice['volume'] ?? 0,
                ];
            }
        }

        return $bestBid;
    }

    public function getBestAsk(string $symbol): array
    {
        $prices = $this->getPricesAcrossExchanges($symbol);

        if (empty($prices)) {
            return [
                'exchange' => null,
                'price'    => 0,
                'amount'   => 0,
            ];
        }

        // Find the lowest ask (best for buyers)
        $bestAsk = null;
        foreach ($prices as $exchangePrice) {
            if ($bestAsk === null || $exchangePrice['ask'] < $bestAsk['price']) {
                $bestAsk = [
                    'exchange' => $exchangePrice['exchange'],
                    'price'    => $exchangePrice['ask'],
                    'amount'   => $exchangePrice['volume'] ?? 0,
                ];
            }
        }

        return $bestAsk;
    }

    /**
     * Get prices across all exchanges.
     */
    public function getPricesAcrossExchanges(string $pair): array
    {
        $cacheKey = "price:all_exchanges:{$pair}";

        return Cache::remember($cacheKey, 10, function () use ($pair) {
            $basePrice = self::BASE_PRICES[$pair] ?? 100.0;
            $prices = [];

            foreach (self::EXCHANGES as $exchange) {
                // Add slight variation per exchange (±0.3% typical)
                $variation = (mt_rand(-30, 30) / 10000);
                $exchangePrice = $basePrice * (1 + $variation);

                // Bid/ask spread (0.05-0.1% typical)
                $spreadPercent = mt_rand(5, 10) / 10000;
                $spread = $exchangePrice * $spreadPercent;

                $prices[] = [
                    'exchange'   => $exchange,
                    'pair'       => $pair,
                    'price'      => round($exchangePrice, 8),
                    'bid'        => round($exchangePrice - $spread, 8),
                    'ask'        => round($exchangePrice + $spread, 8),
                    'volume'     => round(mt_rand(10, 1000) / 10, 4),
                    'updated_at' => now()->toIso8601String(),
                ];
            }

            return $prices;
        });
    }
}
