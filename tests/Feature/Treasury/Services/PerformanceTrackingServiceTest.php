<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury\Services;

use App\Domain\Treasury\Services\AssetValuationService;
use App\Domain\Treasury\Services\PerformanceTrackingService;
use App\Domain\Treasury\Services\PortfolioManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class PerformanceTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceTrackingService $service;

    private PortfolioManagementService $portfolioService;

    private AssetValuationService $valuationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portfolioService = app(PortfolioManagementService::class);
        $this->valuationService = app(AssetValuationService::class);
        $this->service = new PerformanceTrackingService(
            $this->portfolioService,
            $this->valuationService
        );
        Cache::flush();
    }

    public function test_calculate_returns_with_valid_period(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $returns = $this->service->calculateReturns($portfolioId, '30d');

        $this->assertIsArray($returns);
        $this->assertEquals('30d', $returns['period']);
        $this->assertArrayHasKey('start_date', $returns);
        $this->assertArrayHasKey('end_date', $returns);
        $this->assertArrayHasKey('total_return', $returns);
        $this->assertArrayHasKey('annualized_return', $returns);
        $this->assertArrayHasKey('volatility', $returns);
        $this->assertArrayHasKey('sharpe_ratio', $returns);
        $this->assertArrayHasKey('max_drawdown', $returns);
        $this->assertArrayHasKey('return_statistics', $returns);

        // Verify return statistics structure
        $stats = $returns['return_statistics'];
        $this->assertArrayHasKey('mean_return', $stats);
        $this->assertArrayHasKey('median_return', $stats);
        $this->assertArrayHasKey('positive_days', $stats);
        $this->assertArrayHasKey('negative_days', $stats);
        $this->assertArrayHasKey('total_days', $stats);
    }

    public function test_calculate_returns_with_invalid_period(): void
    {
        $portfolioId = $this->createTestPortfolio();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid period');

        $this->service->calculateReturns($portfolioId, 'invalid_period');
    }

    public function test_calculate_returns_with_empty_portfolio_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Portfolio ID cannot be empty');

        $this->service->calculateReturns('', '30d');
    }

    public function test_calculate_returns_for_all_periods(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $periods = ['1d', '7d', '30d', '90d', '1y', 'ytd', 'inception'];

        foreach ($periods as $period) {
            $returns = $this->service->calculateReturns($portfolioId, $period);

            $this->assertEquals($period, $returns['period']);
            $this->assertIsFloat($returns['total_return']);
            $this->assertIsFloat($returns['annualized_return']);
            $this->assertIsFloat($returns['volatility']);
            $this->assertIsFloat($returns['sharpe_ratio']);
            $this->assertIsFloat($returns['max_drawdown']);
        }
    }

    public function test_track_performance_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        // This should not throw an exception
        $this->service->trackPerformance($portfolioId);

        // Verify cache was cleared
        $this->assertFalse(Cache::has("portfolio_returns:{$portfolioId}:1y"));
    }

    public function test_track_performance_with_empty_portfolio_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Portfolio ID cannot be empty');

        $this->service->trackPerformance('');
    }

    public function test_get_performance_metrics_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $metrics = $this->service->getPerformanceMetrics($portfolioId);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('core_metrics', $metrics);
        $this->assertArrayHasKey('risk_adjusted_metrics', $metrics);
        $this->assertArrayHasKey('risk_metrics', $metrics);
        $this->assertArrayHasKey('return_metrics', $metrics);
        $this->assertArrayHasKey('benchmark_comparison', $metrics);

        // Verify risk-adjusted metrics structure
        $riskAdjusted = $metrics['risk_adjusted_metrics'];
        $this->assertArrayHasKey('sharpe_ratio', $riskAdjusted);
        $this->assertArrayHasKey('information_ratio', $riskAdjusted);
        $this->assertArrayHasKey('treynor_ratio', $riskAdjusted);
        $this->assertArrayHasKey('calmar_ratio', $riskAdjusted);
        $this->assertArrayHasKey('sortino_ratio', $riskAdjusted);

        // Verify risk metrics structure
        $riskMetrics = $metrics['risk_metrics'];
        $this->assertArrayHasKey('volatility', $riskMetrics);
        $this->assertArrayHasKey('max_drawdown', $riskMetrics);
        $this->assertArrayHasKey('beta', $riskMetrics);
        $this->assertArrayHasKey('var_95', $riskMetrics);
        $this->assertArrayHasKey('cvar_95', $riskMetrics);
    }

    public function test_get_performance_metrics_with_empty_portfolio_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Portfolio ID cannot be empty');

        $this->service->getPerformanceMetrics('');
    }

    public function test_compare_to_benchmarks_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        $benchmarks = ['sp500', 'balanced'];
        $comparison = $this->service->compareToBusinessary($portfolioId, $benchmarks);

        $this->assertIsArray($comparison);
        $this->assertEquals($portfolioId, $comparison['portfolio_id']);
        $this->assertEquals('1y', $comparison['comparison_period']);
        $this->assertArrayHasKey('comparison_date', $comparison);
        $this->assertArrayHasKey('benchmarks', $comparison);
        $this->assertArrayHasKey('summary', $comparison);

        // Verify benchmark comparisons
        $this->assertArrayHasKey('sp500', $comparison['benchmarks']);
        $this->assertArrayHasKey('balanced', $comparison['benchmarks']);

        foreach ($comparison['benchmarks'] as $benchmark => $data) {
            $this->assertArrayHasKey('name', $data);
            $this->assertArrayHasKey('benchmark_return', $data);
            $this->assertArrayHasKey('portfolio_return', $data);
            $this->assertArrayHasKey('excess_return', $data);
            $this->assertArrayHasKey('relative_performance', $data);
            $this->assertArrayHasKey('risk_adjusted_performance', $data);
        }

        // Verify summary structure
        $summary = $comparison['summary'];
        $this->assertArrayHasKey('total_benchmarks', $summary);
        $this->assertArrayHasKey('outperformed_count', $summary);
        $this->assertArrayHasKey('outperformed_percentage', $summary);
        $this->assertArrayHasKey('overall_assessment', $summary);
    }

    public function test_compare_to_benchmarks_with_empty_benchmarks(): void
    {
        $portfolioId = $this->createTestPortfolio();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Benchmarks cannot be empty');

        $this->service->compareToBusinessary($portfolioId, []);
    }

    public function test_compare_to_benchmarks_with_invalid_benchmarks(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        // Should handle invalid benchmarks gracefully
        $comparison = $this->service->compareToBusinessary($portfolioId, ['invalid_benchmark']);

        $this->assertIsArray($comparison);
        $this->assertEmpty($comparison['benchmarks']); // Invalid benchmarks filtered out
    }

    public function test_performance_metrics_for_empty_portfolio(): void
    {
        $portfolioId = $this->createTestPortfolio(); // No allocations

        $metrics = $this->service->getPerformanceMetrics($portfolioId);

        // Should return default metrics structure
        $this->assertIsArray($metrics);
        $this->assertEquals(0.0, $metrics['core_metrics']['totalValue']);
        $this->assertEquals(0.0, $metrics['core_metrics']['returns']);
        $this->assertEquals(1.0, $metrics['core_metrics']['beta']); // Default beta
    }

    public function test_cache_behavior(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addTestAllocations($portfolioId);

        // First call should cache
        $returns1 = $this->service->calculateReturns($portfolioId, '30d');
        $cacheKey = "portfolio_returns:{$portfolioId}:30d";
        $this->assertTrue(Cache::has($cacheKey));

        // Second call should use cache
        $returns2 = $this->service->calculateReturns($portfolioId, '30d');
        $this->assertEquals($returns1, $returns2);

        // Performance metrics should also cache
        $metrics1 = $this->service->getPerformanceMetrics($portfolioId);
        $metricsKey = "performance_metrics:{$portfolioId}";
        $this->assertTrue(Cache::has($metricsKey));

        $metrics2 = $this->service->getPerformanceMetrics($portfolioId);
        $this->assertEquals($metrics1, $metrics2);
    }

    public function test_risk_profile_based_benchmark_selection(): void
    {
        // Test conservative portfolio
        $conservativeId = $this->portfolioService->createPortfolio('treasury-123', 'Conservative Portfolio', [
            'riskProfile'        => 'conservative',
            'rebalanceThreshold' => 3.0,
            'targetReturn'       => 0.05,
        ]);

        // Test aggressive portfolio
        $aggressiveId = $this->portfolioService->createPortfolio('treasury-456', 'Aggressive Portfolio', [
            'riskProfile'        => 'aggressive',
            'rebalanceThreshold' => 7.0,
            'targetReturn'       => 0.12,
        ]);

        $this->addTestAllocations($conservativeId);
        $this->addTestAllocations($aggressiveId);

        // Both should work without throwing exceptions
        $conservativeComparison = $this->service->compareToBusinessary($conservativeId, ['sp500']);
        $aggressiveComparison = $this->service->compareToBusinessary($aggressiveId, ['sp500']);

        $this->assertIsArray($conservativeComparison);
        $this->assertIsArray($aggressiveComparison);
    }

    public function test_return_calculation_with_different_portfolio_values(): void
    {
        $portfolioId = $this->createTestPortfolio();

        // Add allocations with different total values
        $smallAllocations = [
            [
                'assetClass'    => 'cash',
                'targetWeight'  => 100.0,
                'currentWeight' => 100.0,
                'drift'         => 0.0,
                'amount'        => 10000, // Small portfolio
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $smallAllocations);

        $returns = $this->service->calculateReturns($portfolioId, '30d');

        $this->assertEquals(10000.0, $returns['start_value']);
        $this->assertEquals(10000.0, $returns['end_value']);
        $this->assertEquals(0.0, $returns['total_return']); // No performance history yet
    }

    private function createTestPortfolio(string $treasuryId = 'treasury-123', string $name = 'Test Portfolio'): string
    {
        return $this->portfolioService->createPortfolio($treasuryId, $name, [
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
