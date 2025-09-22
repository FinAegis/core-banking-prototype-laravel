<?php

declare(strict_types=1);

namespace App\Domain\Asset\Services;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /**
     * Cache TTL for exchange rates in minutes.
     */
    private const CACHE_TTL = 15;

    /**
     * Maximum age for rates before considering them stale (in minutes).
     */
    private const MAX_RATE_AGE = 60;

    /**
     * Get the current exchange rate between two assets.
     */
    public function getRate(string $fromAsset, string $toAsset): ?ExchangeRate
    {
        // Same asset conversion
        if ($fromAsset === $toAsset) {
            return $this->createIdentityRate($fromAsset, $toAsset);
        }

        $cacheKey = "exchange_rate:{$fromAsset}:{$toAsset}";

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL * 60,
            function () use ($fromAsset, $toAsset): ?ExchangeRate {
                // Try to get the most recent valid rate
                $rate = ExchangeRate::between($fromAsset, $toAsset)
                    ->valid()
                    ->latest()
                    ->first();

                // If no rate found or rate is stale, try to fetch a new one
                if (! $rate || $rate->getAgeInMinutes() > self::MAX_RATE_AGE) {
                    $fetchedRate = $this->fetchAndStoreRate($fromAsset, $toAsset);
                    if ($fetchedRate) {
                        $rate = $fetchedRate;
                    }
                }

                return $rate;
            }
        );
    }

    /**
     * Get the inverse rate (to -> from).
     */
    public function getInverseRate(string $fromAsset, string $toAsset): ?ExchangeRate
    {
        return $this->getRate($toAsset, $fromAsset);
    }

    /**
     * Convert an amount from one asset to another.
     *
     * @param  int  $amount  Amount in smallest unit
     * @return int|null Converted amount in smallest unit, null if no rate available
     */
    public function convert(int $amount, string $fromAsset, string $toAsset): ?int
    {
        $rate = $this->getRate($fromAsset, $toAsset);

        if (! $rate) {
            return null;
        }

        return $rate->convert($amount);
    }

    /**
     * Fetch exchange rate from external API and store it.
     */
    public function fetchAndStoreRate(string $fromAsset, string $toAsset): ?ExchangeRate
    {
        try {
            // First, check if we can use a chain conversion through USD
            if ($fromAsset !== 'USD' && $toAsset !== 'USD') {
                return $this->fetchChainedRate($fromAsset, $toAsset);
            }

            $rateData = $this->fetchRateFromProvider($fromAsset, $toAsset);

            if (! $rateData) {
                Log::warning(
                    'Failed to fetch exchange rate',
                    [
                        'from' => $fromAsset,
                        'to'   => $toAsset,
                    ]
                );

                return null;
            }

            return $this->storeRate(
                $fromAsset,
                $toAsset,
                $rateData['rate'],
                ExchangeRate::SOURCE_API,
                $rateData['metadata'] ?? []
            );
        } catch (Exception $e) {
            Log::error(
                'Error fetching exchange rate',
                [
                    'from'  => $fromAsset,
                    'to'    => $toAsset,
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Fetch a chained rate through USD (e.g., EUR->USD->BTC).
     */
    private function fetchChainedRate(string $fromAsset, string $toAsset): ?ExchangeRate
    {
        // Get both rates to/from USD
        $fromToUsdData = $this->fetchRateFromProvider($fromAsset, 'USD');
        $usdToToData = $this->fetchRateFromProvider('USD', $toAsset);

        if (! $fromToUsdData || ! $usdToToData) {
            return null;
        }

        // Calculate chained rate
        $chainedRate = $fromToUsdData['rate'] * $usdToToData['rate'];

        return $this->storeRate(
            $fromAsset,
            $toAsset,
            $chainedRate,
            ExchangeRate::SOURCE_API,
            [
                'chained'       => true,
                'via'           => 'USD',
                'from_usd_rate' => $fromToUsdData['rate'],
                'usd_to_rate'   => $usdToToData['rate'],
            ]
        );
    }

    /**
     * Fetch rate from external provider.
     */
    private function fetchRateFromProvider(string $fromAsset, string $toAsset): ?array
    {
        // Determine the best provider based on asset types
        /** @var Asset|null $fromAssetModel */
        $fromAssetModel = Asset::where('code', $fromAsset)->first();
        /** @var Asset|null $toAssetModel */
        $toAssetModel = Asset::where('code', $toAsset)->first();

        if (! $fromAssetModel || ! $toAssetModel) {
            return null;
        }

        // Use appropriate provider based on asset types
        if ($fromAssetModel->isCrypto() || $toAssetModel->isCrypto()) {
            return $this->fetchFromCryptoProvider($fromAsset, $toAsset);
        } elseif ($fromAssetModel->isFiat() && $toAssetModel->isFiat()) {
            return $this->fetchFromFiatProvider($fromAsset, $toAsset);
        } elseif ($fromAssetModel->isCommodity() || $toAssetModel->isCommodity()) {
            return $this->fetchFromCommodityProvider($fromAsset, $toAsset);
        }

        return null;
    }

    /**
     * Fetch rate from cryptocurrency API.
     */
    private function fetchFromCryptoProvider(string $fromAsset, string $toAsset): ?array
    {
        // Mock implementation - in production, integrate with CoinGecko, CoinMarketCap, etc.
        $mockRates = [
            'BTC-USD' => 42000.00,
            'USD-BTC' => 0.0000238,
            'ETH-USD' => 2500.00,
            'USD-ETH' => 0.0004,
            'BTC-ETH' => 16.8,
            'ETH-BTC' => 0.0595,
        ];

        $pair = "$fromAsset-$toAsset";
        if (isset($mockRates[$pair])) {
            return [
                'rate'     => $mockRates[$pair],
                'metadata' => [
                    'provider'  => 'mock_crypto_api',
                    'timestamp' => now()->toISOString(),
                ],
            ];
        }

        return null;
    }

    /**
     * Fetch rate from fiat currency API.
     */
    private function fetchFromFiatProvider(string $fromAsset, string $toAsset): ?array
    {
        // Mock implementation - in production, integrate with Fixer.io, ExchangeRate-API, etc.
        $mockRates = [
            'USD-EUR' => 0.85,
            'EUR-USD' => 1.18,
            'USD-GBP' => 0.73,
            'GBP-USD' => 1.37,
            'EUR-GBP' => 0.86,
            'GBP-EUR' => 1.16,
        ];

        $pair = "$fromAsset-$toAsset";
        if (isset($mockRates[$pair])) {
            return [
                'rate'     => $mockRates[$pair],
                'metadata' => [
                    'provider'  => 'mock_fiat_api',
                    'timestamp' => now()->toISOString(),
                ],
            ];
        }

        return null;
    }

    /**
     * Fetch rate from commodity API.
     */
    private function fetchFromCommodityProvider(string $fromAsset, string $toAsset): ?array
    {
        // Mock implementation - in production, integrate with commodity APIs
        $mockRates = [
            'XAU-USD' => 2000.00,
            'USD-XAU' => 0.0005,
        ];

        $pair = "$fromAsset-$toAsset";
        if (isset($mockRates[$pair])) {
            return [
                'rate'     => $mockRates[$pair],
                'metadata' => [
                    'provider'  => 'mock_commodity_api',
                    'timestamp' => now()->toISOString(),
                ],
            ];
        }

        return null;
    }

    /**
     * Store a new exchange rate.
     */
    public function storeRate(
        string $fromAsset,
        string $toAsset,
        float $rate,
        string $source = ExchangeRate::SOURCE_MANUAL,
        array $metadata = []
    ): ExchangeRate {
        $exchangeRate = ExchangeRate::create(
            [
                'from_asset_code' => $fromAsset,
                'to_asset_code'   => $toAsset,
                'rate'            => $rate,
                'source'          => $source,
                'valid_at'        => now(),
                'expires_at'      => now()->addHours(1), // Default 1 hour expiry
                'is_active'       => true,
                'metadata'        => $metadata,
            ]
        );

        // Clear cache
        Cache::forget("exchange_rate:{$fromAsset}:{$toAsset}");

        return $exchangeRate;
    }

    /**
     * Create an identity rate (1:1) for same asset conversions.
     */
    private function createIdentityRate(string $fromAsset, string $toAsset): ExchangeRate
    {
        $rate = new ExchangeRate(
            [
                'from_asset_code' => $fromAsset,
                'to_asset_code'   => $toAsset,
                'rate'            => 1.0,
                'source'          => ExchangeRate::SOURCE_MANUAL,
                'valid_at'        => now(),
                'expires_at'      => null,
                'is_active'       => true,
                'metadata'        => ['identity' => true],
            ]
        );

        // Don't save identity rates to database
        return $rate;
    }

    /**
     * Get all available exchange rates for an asset.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableRatesFor(string $assetCode)
    {
        return ExchangeRate::where(
            function ($query) use ($assetCode) {
                $query->where('from_asset_code', $assetCode)
                    ->orWhere('to_asset_code', $assetCode);
            }
        )
            ->valid()
            ->latest()
            ->get();
    }

    /**
     * Refresh all stale rates.
     *
     * @return int Number of rates refreshed
     */
    public function refreshStaleRates(): int
    {
        $staleRates = ExchangeRate::where('valid_at', '<', now()->subMinutes(self::MAX_RATE_AGE))
            ->active()
            ->get();

        $refreshed = 0;

        foreach ($staleRates as $staleRate) {
            if ($this->fetchAndStoreRate($staleRate->from_asset_code, $staleRate->to_asset_code)) {
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * Get rate history for a specific pair.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRateHistory(string $fromAsset, string $toAsset, int $days = 30)
    {
        return ExchangeRate::between($fromAsset, $toAsset)
            ->where('valid_at', '>=', now()->subDays($days))
            ->orderBy('valid_at', 'desc')
            ->get();
    }
}
