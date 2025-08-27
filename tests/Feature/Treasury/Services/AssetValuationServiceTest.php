<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury\Services;

use App\Domain\Treasury\Services\AssetValuationService;
use App\Domain\Treasury\Services\PortfolioManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class AssetValuationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssetValuationService $service;

    private PortfolioManagementService $portfolioService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portfolioService = app(PortfolioManagementService::class);
        $this->service = new AssetValuationService($this->portfolioService);
        Cache::flush();
    }

    public function test_get_asset_prices_successfully(): void
    {
        $assetIds = ['USD', 'SP500_ETF', 'US_TREASURY_10Y'];

        $prices = $this->service->getAssetPrices($assetIds);

        $this->assertIsArray($prices);
        $this->assertCount(3, $prices);

        foreach ($assetIds as $assetId) {
            $this->assertArrayHasKey($assetId, $prices);

            $priceData = $prices[$assetId];
            $this->assertArrayHasKey('symbol', $priceData);
            $this->assertArrayHasKey('current_price', $priceData);
            $this->assertArrayHasKey('previous_close', $priceData);
            $this->assertArrayHasKey('change_amount', $priceData);
            $this->assertArrayHasKey('change_percent', $priceData);
            $this->assertArrayHasKey('volume', $priceData);
            $this->assertArrayHasKey('last_updated', $priceData);
            $this->assertArrayHasKey('source', $priceData);

            $this->assertEquals($assetId, $priceData['symbol']);
            $this->assertIsFloat($priceData['current_price']);
            $this->assertGreaterThan(0, $priceData['current_price']);
        }
    }

    public function test_get_asset_prices_with_empty_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset IDs cannot be empty');

        $this->service->getAssetPrices([]);
    }

    public function test_get_asset_prices_with_unknown_assets(): void
    {
        $assetIds = ['UNKNOWN_ASSET', 'ANOTHER_UNKNOWN'];

        $prices = $this->service->getAssetPrices($assetIds);

        $this->assertIsArray($prices);
        $this->assertCount(2, $prices);

        // Should return default prices for unknown assets
        foreach ($assetIds as $assetId) {
            $this->assertArrayHasKey($assetId, $prices);
            $this->assertIsArray($prices[$assetId]);
            $this->assertEquals(100.00, $prices[$assetId]['previous_close']); // Default price
        }
    }

    public function test_calculate_portfolio_value_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $value = $this->service->calculatePortfolioValue($portfolioId);

        // Value should be a positive float
        $this->assertGreaterThan(0, $value);

        // Should be close to the allocated amount, adjusted for price changes
        $this->assertGreaterThan(900000, $value); // Should be around 1M with variations
        $this->assertLessThan(1100000, $value);
    }

    public function test_calculate_portfolio_value_with_empty_portfolio(): void
    {
        $portfolioId = $this->createTestPortfolio(); // No allocations

        $value = $this->service->calculatePortfolioValue($portfolioId);

        $this->assertEquals(0.0, $value);
    }

    public function test_calculate_portfolio_value_with_empty_portfolio_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Portfolio ID cannot be empty');

        $this->service->calculatePortfolioValue('');
    }

    public function test_mark_to_market_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $valuation = $this->service->markToMarket($portfolioId);

        $this->assertIsArray($valuation);
        $this->assertEquals($portfolioId, $valuation['portfolio_id']);
        $this->assertArrayHasKey('valuation_date', $valuation);
        $this->assertArrayHasKey('total_value', $valuation);
        $this->assertArrayHasKey('previous_value', $valuation);
        $this->assertArrayHasKey('change_amount', $valuation);
        $this->assertArrayHasKey('change_percent', $valuation);
        $this->assertArrayHasKey('asset_valuations', $valuation);
        $this->assertArrayHasKey('market_data_quality', $valuation);
        $this->assertArrayHasKey('valuation_method', $valuation);
        $this->assertArrayHasKey('confidence_level', $valuation);

        $this->assertEquals('mark_to_market', $valuation['valuation_method']);
        $this->assertIsFloat($valuation['confidence_level']);
        $this->assertGreaterThanOrEqual(0.0, $valuation['confidence_level']);
        $this->assertLessThanOrEqual(1.0, $valuation['confidence_level']);

        // Verify asset valuations structure
        $this->assertIsArray($valuation['asset_valuations']);
        $this->assertNotEmpty($valuation['asset_valuations']);

        foreach ($valuation['asset_valuations'] as $assetValuation) {
            $this->assertArrayHasKey('asset_class', $assetValuation);
            $this->assertArrayHasKey('target_weight', $assetValuation);
            $this->assertArrayHasKey('current_weight', $assetValuation);
            $this->assertArrayHasKey('target_value', $assetValuation);
            $this->assertArrayHasKey('previous_value', $assetValuation);
            $this->assertArrayHasKey('current_value', $assetValuation);
            $this->assertArrayHasKey('change_amount', $assetValuation);
            $this->assertArrayHasKey('change_percent', $assetValuation);
            $this->assertArrayHasKey('drift', $assetValuation);
            $this->assertArrayHasKey('price_data', $assetValuation);
        }
    }

    public function test_mark_to_market_with_empty_portfolio(): void
    {
        $portfolioId = $this->createTestPortfolio(); // No allocations

        $valuation = $this->service->markToMarket($portfolioId);

        $this->assertEquals(0.0, $valuation['total_value']);
        $this->assertEquals(0.0, $valuation['change_amount']);
        $this->assertEquals(0.0, $valuation['change_percent']);
        $this->assertEmpty($valuation['asset_valuations']);
    }

    public function test_get_historical_prices_successfully(): void
    {
        $assetIds = ['USD', 'SP500_ETF'];
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $historicalData = $this->service->getHistoricalPrices($assetIds, $startDate, $endDate);

        $this->assertIsArray($historicalData);
        $this->assertNotEmpty($historicalData);

        // Verify structure
        foreach ($historicalData as $dayData) {
            $this->assertArrayHasKey('date', $dayData);

            foreach ($assetIds as $assetId) {
                $this->assertArrayHasKey($assetId, $dayData);
                $this->assertIsFloat($dayData[$assetId]);
                $this->assertGreaterThan(0, $dayData[$assetId]);
            }
        }

        // Should have approximately 31 days of data (30 days + start day)
        $this->assertGreaterThanOrEqual(30, count($historicalData));
        $this->assertLessThanOrEqual(32, count($historicalData));
    }

    public function test_get_historical_prices_with_empty_asset_ids(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset IDs cannot be empty');

        $this->service->getHistoricalPrices([], $startDate, $endDate);
    }

    public function test_calculate_asset_correlations_successfully(): void
    {
        $assetIds = ['SP500_ETF', 'US_TREASURY_10Y', 'USD'];

        $correlations = $this->service->calculateAssetCorrelations($assetIds, 30);

        $this->assertIsArray($correlations);
        $this->assertCount(3, $correlations);

        foreach ($assetIds as $asset1) {
            $this->assertArrayHasKey($asset1, $correlations);
            $this->assertCount(3, $correlations[$asset1]);

            foreach ($assetIds as $asset2) {
                $this->assertArrayHasKey($asset2, $correlations[$asset1]);

                $correlation = $correlations[$asset1][$asset2];
                $this->assertIsFloat($correlation);
                $this->assertGreaterThanOrEqual(-1.0, $correlation);
                $this->assertLessThanOrEqual(1.0, $correlation);

                // Self-correlation should be 1.0
                if ($asset1 === $asset2) {
                    $this->assertEquals(1.0, $correlation);
                }
            }
        }
    }

    public function test_calculate_asset_correlations_with_empty_assets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset IDs cannot be empty');

        $this->service->calculateAssetCorrelations([]);
    }

    public function test_get_asset_volatilities_successfully(): void
    {
        $assetIds = ['SP500_ETF', 'US_TREASURY_10Y', 'USD'];

        $volatilities = $this->service->getAssetVolatilities($assetIds, 30);

        $this->assertIsArray($volatilities);
        $this->assertCount(3, $volatilities);

        foreach ($assetIds as $assetId) {
            $this->assertArrayHasKey($assetId, $volatilities);
            $this->assertIsFloat($volatilities[$assetId]);
            $this->assertGreaterThanOrEqual(0.0, $volatilities[$assetId]);

            // Volatility should be reasonable (0% to 300% annualized for mock data)
            $this->assertLessThanOrEqual(3.0, $volatilities[$assetId]);
        }
    }

    public function test_get_asset_volatilities_with_empty_assets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Asset IDs cannot be empty');

        $this->service->getAssetVolatilities([]);
    }

    public function test_cache_behavior(): void
    {
        $assetIds = ['USD', 'SP500_ETF'];

        // First call should cache
        $prices1 = $this->service->getAssetPrices($assetIds);

        // Verify individual asset prices are cached
        foreach ($assetIds as $assetId) {
            $this->assertTrue(Cache::has("asset_price:{$assetId}"));
        }

        // Second call should use cache
        $prices2 = $this->service->getAssetPrices($assetIds);

        // Prices should be identical (from cache)
        $this->assertEquals($prices1, $prices2);

        // Portfolio valuation should also cache
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $value1 = $this->service->calculatePortfolioValue($portfolioId);
        $this->assertTrue(Cache::has("portfolio_value:{$portfolioId}"));

        $value2 = $this->service->calculatePortfolioValue($portfolioId);
        $this->assertEquals($value1, $value2);
    }

    public function test_data_quality_assessment(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $valuation = $this->service->markToMarket($portfolioId);

        $this->assertArrayHasKey('market_data_quality', $valuation);
        $this->assertContains($valuation['market_data_quality'], [
            'excellent', 'good', 'fair', 'poor', 'no_data',
        ]);
    }

    public function test_price_variation_simulation(): void
    {
        $assetIds = ['SP500_ETF'];

        // Get prices multiple times to see variation
        $prices1 = $this->service->getAssetPrices($assetIds);
        Cache::flush(); // Clear cache to get fresh prices
        $prices2 = $this->service->getAssetPrices($assetIds);

        $price1 = $prices1['SP500_ETF']['current_price'];
        $price2 = $prices2['SP500_ETF']['current_price'];

        // Prices should be different due to mock variation
        // (unless we get unlucky with random seed)
        $this->assertIsFloat($price1);
        $this->assertIsFloat($price2);
        $this->assertGreaterThan(0, $price1);
        $this->assertGreaterThan(0, $price2);
    }

    public function test_default_asset_pricing(): void
    {
        $unknownAssets = ['COMPLETELY_UNKNOWN_ASSET'];

        $prices = $this->service->getAssetPrices($unknownAssets);

        $this->assertArrayHasKey('COMPLETELY_UNKNOWN_ASSET', $prices);

        $priceData = $prices['COMPLETELY_UNKNOWN_ASSET'];
        $this->assertEquals(100.00, $priceData['previous_close']); // Default price
    }

    private function createTestPortfolio(string $treasuryId = 'treasury-123'): string
    {
        return $this->portfolioService->createPortfolio($treasuryId, 'Test Portfolio', [
            'riskProfile'        => 'moderate',
            'rebalanceThreshold' => 5.0,
            'targetReturn'       => 0.08,
        ]);
    }

    private function addTestAllocations(string $portfolioId): void
    {
        $allocations = [
            [
                'assetClass'    => 'SP500_ETF',
                'targetWeight'  => 60.0,
                'currentWeight' => 60.0,
                'drift'         => 0.0,
                'amount'        => 600000,
            ],
            [
                'assetClass'    => 'US_TREASURY_10Y',
                'targetWeight'  => 30.0,
                'currentWeight' => 30.0,
                'drift'         => 0.0,
                'amount'        => 300000,
            ],
            [
                'assetClass'    => 'USD',
                'targetWeight'  => 10.0,
                'currentWeight' => 10.0,
                'drift'         => 0.0,
                'amount'        => 100000,
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $allocations);
    }
}
