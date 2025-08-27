<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class AssetValuationService
{
    private const CACHE_TTL = 300; // 5 minutes cache for asset prices

    private const PRICE_CACHE_TTL = 60; // 1 minute cache for real-time prices

    // Demo/mock asset prices for development
    private const MOCK_ASSET_PRICES = [
        // Cash equivalents
        'USD' => 1.00,
        'EUR' => 1.08,
        'GBP' => 1.27,
        'JPY' => 0.0067,

        // Bonds
        'US_TREASURY_10Y'     => 95.50,
        'US_TREASURY_2Y'      => 98.25,
        'CORPORATE_BONDS_AAA' => 102.75,
        'MUNICIPAL_BONDS'     => 99.80,
        'HIGH_YIELD_BONDS'    => 87.50,

        // Equities
        'SP500_ETF'            => 450.25,
        'NASDAQ_ETF'           => 375.80,
        'DOW_ETF'              => 340.50,
        'FTSE_ETF'             => 280.75,
        'EMERGING_MARKETS_ETF' => 42.30,

        // REITs
        'RESIDENTIAL_REIT' => 85.60,
        'COMMERCIAL_REIT'  => 92.40,
        'REIT_ETF'         => 78.90,

        // Commodities
        'GOLD_ETF'   => 180.45,
        'SILVER_ETF' => 21.30,
        'OIL_ETF'    => 75.20,

        // Alternatives
        'PRIVATE_EQUITY_INDEX' => 125.80,
        'HEDGE_FUND_INDEX'     => 110.50,
        'COMMODITY_INDEX'      => 95.75,
    ];

    public function __construct(
        private readonly PortfolioManagementService $portfolioService
    ) {
    }

    public function getAssetPrices(array $assetIds): array
    {
        if (empty($assetIds)) {
            throw new InvalidArgumentException('Asset IDs cannot be empty');
        }

        $prices = [];
        $cacheMisses = [];

        // Check cache first
        foreach ($assetIds as $assetId) {
            $cacheKey = "asset_price:{$assetId}";
            $cachedPrice = Cache::get($cacheKey);

            if ($cachedPrice !== null) {
                $prices[$assetId] = $cachedPrice;
            } else {
                $cacheMisses[] = $assetId;
            }
        }

        // Fetch missing prices
        if (! empty($cacheMisses)) {
            $freshPrices = $this->fetchAssetPrices($cacheMisses);

            foreach ($freshPrices as $assetId => $priceData) {
                $cacheKey = "asset_price:{$assetId}";
                Cache::put($cacheKey, $priceData, self::PRICE_CACHE_TTL);
                $prices[$assetId] = $priceData;
            }
        }

        return $prices;
    }

    public function calculatePortfolioValue(string $portfolioId): float
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "portfolio_value:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                $portfolio = $this->portfolioService->getPortfolio($portfolioId);

                if (empty($portfolio['asset_allocations'])) {
                    return 0.0;
                }

                $totalValue = 0.0;
                $assetIds = array_column($portfolio['asset_allocations'], 'assetClass');
                if (empty($assetIds)) {
                    return 0.0;
                }
                $assetPrices = $this->getAssetPrices($assetIds);

                foreach ($portfolio['asset_allocations'] as $allocation) {
                    $assetClass = $allocation['assetClass'];
                    $currentWeight = $allocation['currentWeight'] / 100;

                    // Get asset price and calculate position value
                    $assetPrice = $assetPrices[$assetClass] ?? $this->getDefaultAssetPrice($assetClass);
                    $positionValue = $portfolio['total_value'] * $currentWeight;

                    // Apply price changes
                    if (is_array($assetPrice)) {
                        $priceChange = $assetPrice['change_percent'] ?? 0.0;
                        $positionValue *= (1 + $priceChange / 100);
                    }

                    $totalValue += $positionValue;
                }

                return $totalValue;
            } catch (Exception $e) {
                throw new RuntimeException("Failed to calculate portfolio value: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function markToMarket(string $portfolioId): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);

            if (empty($portfolio['asset_allocations'])) {
                return [
                    'portfolio_id'     => $portfolioId,
                    'valuation_date'   => now()->toISOString(),
                    'total_value'      => 0.0,
                    'previous_value'   => $portfolio['total_value'],
                    'change_amount'    => 0.0,
                    'change_percent'   => 0.0,
                    'asset_valuations' => [],
                ];
            }

            $assetIds = array_column($portfolio['asset_allocations'], 'assetClass');
            if (empty($assetIds)) {
                return [
                    'portfolio_id'     => $portfolioId,
                    'valuation_date'   => now()->toISOString(),
                    'total_value'      => 0.0,
                    'previous_value'   => $portfolio['total_value'],
                    'change_amount'    => 0.0,
                    'change_percent'   => 0.0,
                    'asset_valuations' => [],
                ];
            }
            $assetPrices = $this->getAssetPrices($assetIds);

            $assetValuations = [];
            $totalCurrentValue = 0.0;
            $previousValue = $portfolio['total_value'];

            foreach ($portfolio['asset_allocations'] as $allocation) {
                $assetClass = $allocation['assetClass'];
                $targetWeight = $allocation['targetWeight'] / 100;
                $currentWeight = $allocation['currentWeight'] / 100;

                $assetPrice = $assetPrices[$assetClass] ?? $this->getDefaultAssetPrice($assetClass);

                // Calculate current market values
                $previousPositionValue = $previousValue * $currentWeight;
                $currentPositionValue = $previousPositionValue;

                if (is_array($assetPrice)) {
                    $priceChange = $assetPrice['change_percent'] ?? 0.0;
                    $currentPositionValue *= (1 + $priceChange / 100);
                }

                $positionChange = $currentPositionValue - $previousPositionValue;
                $positionChangePercent = $previousPositionValue > 0 ?
                    ($positionChange / $previousPositionValue) * 100 : 0.0;

                $assetValuations[] = [
                    'asset_class'    => $assetClass,
                    'target_weight'  => $allocation['targetWeight'],
                    'current_weight' => $allocation['currentWeight'],
                    'target_value'   => $previousValue * $targetWeight,
                    'previous_value' => $previousPositionValue,
                    'current_value'  => $currentPositionValue,
                    'change_amount'  => $positionChange,
                    'change_percent' => $positionChangePercent,
                    'drift'          => $allocation['drift'],
                    'price_data'     => $assetPrice,
                ];

                $totalCurrentValue += $currentPositionValue;
            }

            $totalChange = $totalCurrentValue - $previousValue;
            $totalChangePercent = $previousValue > 0 ? ($totalChange / $previousValue) * 100 : 0.0;

            return [
                'portfolio_id'        => $portfolioId,
                'valuation_date'      => now()->toISOString(),
                'total_value'         => $totalCurrentValue,
                'previous_value'      => $previousValue,
                'change_amount'       => $totalChange,
                'change_percent'      => $totalChangePercent,
                'asset_valuations'    => $assetValuations,
                'market_data_quality' => $this->assessDataQuality($assetPrices),
                'valuation_method'    => 'mark_to_market',
                'confidence_level'    => $this->calculateConfidenceLevel($assetPrices),
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to mark portfolio to market: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get historical asset prices for performance calculation.
     */
    public function getHistoricalPrices(array $assetIds, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        if (empty($assetIds)) {
            throw new InvalidArgumentException('Asset IDs cannot be empty');
        }

        $cacheKey = 'historical_prices:' . md5(implode(',', $assetIds) . $startDate->toDateString() . $endDate->toDateString());

        return Cache::remember($cacheKey, 3600, function () use ($assetIds, $startDate, $endDate) {
            // In production, this would fetch from a financial data provider
            // For demo, generate mock historical data
            return $this->generateMockHistoricalData($assetIds, $startDate, $endDate);
        });
    }

    /**
     * Calculate asset correlation matrix.
     */
    public function calculateAssetCorrelations(array $assetIds, int $days = 252): array
    {
        if (empty($assetIds)) {
            throw new InvalidArgumentException('Asset IDs cannot be empty');
        }

        $cacheKey = 'asset_correlations:' . md5(implode(',', $assetIds)) . ":{$days}";

        return Cache::remember($cacheKey, 3600, function () use ($assetIds, $days) {
            $startDate = now()->subDays($days);
            $endDate = now();

            $historicalData = $this->getHistoricalPrices($assetIds, $startDate, $endDate);

            return $this->calculateCorrelationMatrix($historicalData, $assetIds);
        });
    }

    /**
     * Get volatility estimates for assets.
     */
    public function getAssetVolatilities(array $assetIds, int $days = 252): array
    {
        if (empty($assetIds)) {
            throw new InvalidArgumentException('Asset IDs cannot be empty');
        }

        $volatilities = [];

        foreach ($assetIds as $assetId) {
            $volatilities[$assetId] = $this->calculateAssetVolatility($assetId, $days);
        }

        return $volatilities;
    }

    private function fetchAssetPrices(array $assetIds): array
    {
        // In production, this would call external financial data APIs
        // For development/demo, we'll use mock data with slight variations

        $prices = [];

        foreach ($assetIds as $assetId) {
            $basePrice = self::MOCK_ASSET_PRICES[$assetId] ?? $this->getDefaultAssetPrice($assetId);

            // $basePrice is already a float from the array or method
            // Add small random variation to simulate market movement
            $variation = (rand(-200, 200) / 10000); // -2% to +2% variation
            $currentPrice = $basePrice * (1 + $variation);

            $prices[$assetId] = [
                'symbol'         => $assetId,
                'current_price'  => round($currentPrice, 2),
                'previous_close' => $basePrice,
                'change_amount'  => round($currentPrice - $basePrice, 2),
                'change_percent' => round($variation * 100, 2),
                'volume'         => rand(100000, 1000000),
                'last_updated'   => now()->toISOString(),
                'source'         => config('app.env') === 'demo' ? 'mock_data' : 'market_data',
            ];
        }

        return $prices;
    }

    private function getDefaultAssetPrice(string $assetId): float
    {
        // Return default price based on asset class if not found in mock data
        return match (true) {
            str_contains(strtolower($assetId), 'cash')        => 1.00,
            str_contains(strtolower($assetId), 'bond')        => 100.00,
            str_contains(strtolower($assetId), 'equity')      => 50.00,
            str_contains(strtolower($assetId), 'reit')        => 75.00,
            str_contains(strtolower($assetId), 'gold')        => 180.00,
            str_contains(strtolower($assetId), 'alternative') => 100.00,
            default                                           => 100.00,
        };
    }

    private function generateMockHistoricalData(array $assetIds, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $historicalData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayData = ['date' => $currentDate->toDateString()];

            foreach ($assetIds as $assetId) {
                $basePrice = self::MOCK_ASSET_PRICES[$assetId] ?? $this->getDefaultAssetPrice($assetId);

                // Generate realistic price movement
                $daysSinceStart = $currentDate->diffInDays($startDate);
                $trend = sin($daysSinceStart / 30) * 0.05; // Monthly trend cycle
                $noise = (rand(-100, 100) / 1000); // Daily noise

                $price = $basePrice * (1 + $trend + $noise);
                $dayData[$assetId] = round($price, 2);
            }

            $historicalData[] = $dayData;
            $currentDate->addDay();
        }

        return $historicalData;
    }

    private function calculateCorrelationMatrix(array $historicalData, array $assetIds): array
    {
        $correlations = [];

        // Extract price series for each asset
        $priceSeries = [];
        foreach ($assetIds as $assetId) {
            $priceSeries[$assetId] = array_column($historicalData, $assetId);
        }

        // Calculate correlations between each pair of assets
        foreach ($assetIds as $asset1) {
            $correlations[$asset1] = [];
            foreach ($assetIds as $asset2) {
                if ($asset1 === $asset2) {
                    $correlations[$asset1][$asset2] = 1.0;
                } else {
                    $correlation = $this->calculateCorrelation(
                        $priceSeries[$asset1],
                        $priceSeries[$asset2]
                    );
                    $correlations[$asset1][$asset2] = round($correlation, 3);
                }
            }
        }

        return $correlations;
    }

    private function calculateCorrelation(array $series1, array $series2): float
    {
        $n = min(count($series1), count($series2));

        if ($n < 2) {
            return 0.0;
        }

        $sum1 = array_sum($series1);
        $sum2 = array_sum($series2);

        $mean1 = $sum1 / $n;
        $mean2 = $sum2 / $n;

        $numerator = 0;
        $sum1sq = 0;
        $sum2sq = 0;

        for ($i = 0; $i < $n; $i++) {
            $diff1 = $series1[$i] - $mean1;
            $diff2 = $series2[$i] - $mean2;

            $numerator += $diff1 * $diff2;
            $sum1sq += $diff1 * $diff1;
            $sum2sq += $diff2 * $diff2;
        }

        $denominator = sqrt($sum1sq * $sum2sq);

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    private function calculateAssetVolatility(string $assetId, int $days): float
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $historicalData = $this->getHistoricalPrices([$assetId], $startDate, $endDate);
        $prices = array_column($historicalData, $assetId);

        if (count($prices) < 2) {
            return 0.0;
        }

        // Calculate daily returns
        $returns = [];
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i - 1] > 0) {
                $returns[] = ($prices[$i] - $prices[$i - 1]) / $prices[$i - 1];
            }
        }

        if (empty($returns)) {
            return 0.0;
        }

        // Calculate standard deviation of returns
        $mean = array_sum($returns) / count($returns);
        $sumSquaredDeviations = array_sum(array_map(fn ($r) => pow($r - $mean, 2), $returns));
        $variance = $sumSquaredDeviations / (count($returns) - 1);

        // Annualize volatility
        return sqrt($variance * 252);
    }

    private function assessDataQuality(array $assetPrices): string
    {
        $freshCount = 0;
        $totalCount = count($assetPrices);

        foreach ($assetPrices as $priceData) {
            if (is_array($priceData) && isset($priceData['last_updated'])) {
                $lastUpdated = now()->parse($priceData['last_updated']);
                if ($lastUpdated->diffInMinutes(now()) <= 5) {
                    $freshCount++;
                }
            }
        }

        if ($totalCount === 0) {
            return 'no_data';
        }

        $freshness = $freshCount / $totalCount;

        return match (true) {
            $freshness >= 0.9 => 'excellent',
            $freshness >= 0.7 => 'good',
            $freshness >= 0.5 => 'fair',
            default           => 'poor',
        };
    }

    private function calculateConfidenceLevel(array $assetPrices): float
    {
        $quality = $this->assessDataQuality($assetPrices);

        return match ($quality) {
            'excellent' => 0.95,
            'good'      => 0.85,
            'fair'      => 0.70,
            'poor'      => 0.50,
            default     => 0.50,
        };
    }
}
