<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PriceOracleService
{
    private const CACHE_TTL = 60; // 1 minute cache for prices

    private const PRICE_SOURCES = [
        'internal'  => 0.4,  // 40% weight
        'chainlink' => 0.3, // 30% weight
        'binance'   => 0.2,   // 20% weight
        'coinbase'  => 0.1,  // 10% weight
    ];

    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {
    }

    /**
     * Get current price for an asset.
     */
    public function getPrice(string $assetCode, string $quoteCurrency = 'USD'): BigDecimal
    {
        $cacheKey = "oracle:price:{$assetCode}:{$quoteCurrency}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($assetCode, $quoteCurrency) {
            return $this->fetchAggregatedPrice($assetCode, $quoteCurrency);
        });
    }

    /**
     * Get price with confidence score.
     */
    public function getPriceWithConfidence(string $assetCode, string $quoteCurrency = 'USD'): array
    {
        $prices = $this->fetchPricesFromAllSources($assetCode, $quoteCurrency);
        $aggregatedPrice = $this->calculateWeightedAverage($prices);
        $confidence = $this->calculateConfidenceScore($prices);

        return [
            'price'      => $aggregatedPrice,
            'confidence' => $confidence,
            'sources'    => count($prices),
            'timestamp'  => now(),
        ];
    }

    /**
     * Check for price deviation.
     */
    public function checkPriceDeviation(string $assetCode, BigDecimal $expectedPrice): bool
    {
        $currentPrice = $this->getPrice($assetCode);
        $deviation = $currentPrice->minus($expectedPrice)
            ->abs()
            ->dividedBy($expectedPrice, 4);

        $maxDeviation = BigDecimal::of('0.05'); // 5% max deviation

        if ($deviation->isGreaterThan($maxDeviation)) {
            Log::warning('Price deviation detected', [
                'asset'     => $assetCode,
                'expected'  => $expectedPrice->toFloat(),
                'current'   => $currentPrice->toFloat(),
                'deviation' => $deviation->multipliedBy(100)->toFloat() . '%',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get current prices for common assets.
     */
    public function getCurrentPrices(): array
    {
        $assets = ['ETH', 'BTC', 'USDC', 'USDT'];
        $prices = [];

        foreach ($assets as $asset) {
            try {
                $prices[$asset] = $this->getPrice($asset)->toFloat();
            } catch (\Exception $e) {
                Log::warning("Failed to get price for {$asset}", ['error' => $e->getMessage()]);
                $prices[$asset] = 1.0;
            }
        }

        return $prices;
    }

    /**
     * Get historical price.
     */
    public function getHistoricalPrice(
        string $assetCode,
        \DateTimeInterface $timestamp,
        string $quoteCurrency = 'USD'
    ): ?BigDecimal {
        // In production, this would query historical price data
        // For now, return current price with small random variation
        $currentPrice = $this->getPrice($assetCode, $quoteCurrency);
        $variation = BigDecimal::of(rand(-5, 5))->dividedBy(100);

        return $currentPrice->multipliedBy(BigDecimal::one()->plus($variation));
    }

    /**
     * Fetch aggregated price from multiple sources.
     */
    private function fetchAggregatedPrice(string $assetCode, string $quoteCurrency): BigDecimal
    {
        $prices = $this->fetchPricesFromAllSources($assetCode, $quoteCurrency);

        if (empty($prices)) {
            throw new \RuntimeException("No price sources available for {$assetCode}");
        }

        return $this->calculateWeightedAverage($prices);
    }

    /**
     * Fetch prices from all configured sources.
     */
    private function fetchPricesFromAllSources(string $assetCode, string $quoteCurrency): array
    {
        $prices = [];

        // Internal exchange rate service
        try {
            $rate = $this->exchangeRateService->getRate($assetCode, $quoteCurrency);
            if ($rate) {
                $prices['internal'] = BigDecimal::of($rate->rate);
            }
        } catch (\Exception $e) {
            Log::debug('Internal price source unavailable', ['asset' => $assetCode]);
        }

        // Chainlink oracle (simulated)
        try {
            $prices['chainlink'] = $this->fetchChainlinkPrice($assetCode, $quoteCurrency);
        } catch (\Exception $e) {
            Log::debug('Chainlink price source unavailable', ['asset' => $assetCode]);
        }

        // Binance API (simulated)
        try {
            $prices['binance'] = $this->fetchBinancePrice($assetCode, $quoteCurrency);
        } catch (\Exception $e) {
            Log::debug('Binance price source unavailable', ['asset' => $assetCode]);
        }

        // Coinbase API (simulated)
        try {
            $prices['coinbase'] = $this->fetchCoinbasePrice($assetCode, $quoteCurrency);
        } catch (\Exception $e) {
            Log::debug('Coinbase price source unavailable', ['asset' => $assetCode]);
        }

        return $prices;
    }

    /**
     * Calculate weighted average price.
     */
    private function calculateWeightedAverage(array $prices): BigDecimal
    {
        $weightedSum = BigDecimal::zero();
        $totalWeight = BigDecimal::zero();

        foreach ($prices as $source => $price) {
            $weight = BigDecimal::of(self::PRICE_SOURCES[$source] ?? 0.1);
            $weightedSum = $weightedSum->plus($price->multipliedBy($weight));
            $totalWeight = $totalWeight->plus($weight);
        }

        if ($totalWeight->isZero()) {
            throw new \RuntimeException('No valid price sources');
        }

        return $weightedSum->dividedBy($totalWeight, 8);
    }

    /**
     * Calculate confidence score based on price variance.
     */
    private function calculateConfidenceScore(array $prices): float
    {
        if (count($prices) < 2) {
            return 0.5; // Low confidence with single source
        }

        $values = array_map(fn ($p) => $p->toFloat(), $prices);
        $mean = array_sum($values) / count($values);

        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance = $variance / count($values);
        $stdDev = sqrt($variance);

        // Convert standard deviation to confidence (lower deviation = higher confidence)
        $coefficientOfVariation = $mean > 0 ? $stdDev / $mean : 1;
        $confidence = max(0, min(1, 1 - $coefficientOfVariation));

        return round($confidence, 4);
    }

    /**
     * Simulated Chainlink price fetch.
     */
    private function fetchChainlinkPrice(string $assetCode, string $quoteCurrency): BigDecimal
    {
        // In production, this would call actual Chainlink oracle
        return match ($assetCode) {
            'ETH'   => BigDecimal::of('2000'),
            'BTC'   => BigDecimal::of('40000'),
            'USDC'  => BigDecimal::of('1'),
            default => BigDecimal::of('1'),
        };
    }

    /**
     * Simulated Binance price fetch.
     */
    private function fetchBinancePrice(string $assetCode, string $quoteCurrency): BigDecimal
    {
        // In production, this would call Binance API
        return match ($assetCode) {
            'ETH'   => BigDecimal::of('1995'),
            'BTC'   => BigDecimal::of('39900'),
            'USDC'  => BigDecimal::of('0.9999'),
            default => BigDecimal::of('1'),
        };
    }

    /**
     * Simulated Coinbase price fetch.
     */
    private function fetchCoinbasePrice(string $assetCode, string $quoteCurrency): BigDecimal
    {
        // In production, this would call Coinbase API
        return match ($assetCode) {
            'ETH'   => BigDecimal::of('2005'),
            'BTC'   => BigDecimal::of('40100'),
            'USDC'  => BigDecimal::of('1.0001'),
            default => BigDecimal::of('1'),
        };
    }
}
