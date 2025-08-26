<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury\Services;

use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RebalancingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RebalancingService $service;

    private PortfolioManagementService $portfolioService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->portfolioService = app(PortfolioManagementService::class);
        $this->service = new RebalancingService($this->portfolioService);
        Cache::flush();
    }

    public function test_check_rebalancing_needed_returns_true_for_drifted_portfolio(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addDriftedAllocations($portfolioId);

        $needsRebalancing = $this->service->checkRebalancingNeeded($portfolioId);

        $this->assertTrue($needsRebalancing);
    }

    public function test_check_rebalancing_needed_returns_false_for_balanced_portfolio(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addBalancedAllocations($portfolioId);

        $needsRebalancing = $this->service->checkRebalancingNeeded($portfolioId);

        $this->assertFalse($needsRebalancing);
    }

    public function test_check_rebalancing_needed_with_empty_portfolio_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Portfolio ID cannot be empty');

        $this->service->checkRebalancingNeeded('');
    }

    public function test_calculate_rebalancing_plan_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addDriftedAllocations($portfolioId);

        $plan = $this->service->calculateRebalancingPlan($portfolioId);

        $this->assertIsArray($plan);
        $this->assertEquals($portfolioId, $plan['portfolio_id']);
        $this->assertArrayHasKey('total_portfolio_value', $plan);
        $this->assertArrayHasKey('actions', $plan);
        $this->assertArrayHasKey('total_transaction_cost', $plan);
        $this->assertArrayHasKey('recommended', $plan);

        // Should have rebalancing actions due to drift
        $this->assertNotEmpty($plan['actions']);

        foreach ($plan['actions'] as $action) {
            $this->assertArrayHasKey('asset_class', $action);
            $this->assertArrayHasKey('action_type', $action);
            $this->assertArrayHasKey('amount', $action);
            $this->assertArrayHasKey('priority', $action);
            $this->assertContains($action['action_type'], ['buy', 'sell']);
            $this->assertGreaterThan(0, $action['amount']);
        }
    }

    public function test_calculate_rebalancing_plan_with_no_drift(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addBalancedAllocations($portfolioId);

        $plan = $this->service->calculateRebalancingPlan($portfolioId);

        $this->assertIsArray($plan);
        $this->assertEquals($portfolioId, $plan['portfolio_id']);

        // Should have no actions for balanced portfolio
        $this->assertEmpty($plan['actions']);
        $this->assertFalse($plan['recommended']);
    }

    public function test_execute_rebalancing_successfully(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addDriftedAllocations($portfolioId);

        $plan = $this->service->calculateRebalancingPlan($portfolioId);

        // Execute rebalancing
        $this->service->executeRebalancing($portfolioId, $plan);

        // Verify portfolio is marked as rebalancing
        $portfolio = $this->portfolioService->getPortfolio($portfolioId);
        $this->assertFalse($portfolio['is_rebalancing']); // Should be false after completion

        // Verify cache was cleared
        $this->assertFalse(Cache::has("rebalancing_needed:{$portfolioId}"));
    }

    public function test_execute_rebalancing_with_empty_plan(): void
    {
        $portfolioId = $this->createTestPortfolio();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rebalancing plan cannot be empty');

        $this->service->executeRebalancing($portfolioId, []);
    }

    public function test_execute_rebalancing_with_invalid_plan(): void
    {
        $portfolioId = $this->createTestPortfolio();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required plan field: portfolio_id');

        $invalidPlan = [
            'actions' => [
                [
                    'asset_class'   => 'equities',
                    'target_weight' => 60.0,
                    'action_type'   => 'buy',
                    'amount'        => 10000,
                ],
            ],
        ];

        $this->service->executeRebalancing($portfolioId, $invalidPlan);
    }

    public function test_get_rebalancing_history(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addDriftedAllocations($portfolioId);

        // Execute a rebalancing to create history
        $plan = $this->service->calculateRebalancingPlan($portfolioId);
        $this->service->executeRebalancing($portfolioId, $plan);

        $history = $this->service->getRebalancingHistory($portfolioId);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $history);

        // In a real scenario with actual events, we would have history
        // For this test, we just verify the method doesn't throw exceptions
    }

    public function test_get_rebalancing_metrics(): void
    {
        $portfolioId = $this->createTestPortfolio();

        $metrics = $this->service->getRebalancingMetrics($portfolioId);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_rebalances', $metrics);
        $this->assertArrayHasKey('last_rebalance', $metrics);
        $this->assertArrayHasKey('average_frequency_days', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);

        // For new portfolio with no history
        $this->assertEquals(0, $metrics['total_rebalances']);
        $this->assertNull($metrics['last_rebalance']);
        $this->assertNull($metrics['average_frequency_days']);
        $this->assertEquals(0, $metrics['success_rate']);
    }

    public function test_rebalancing_plan_calculation_details(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addDriftedAllocations($portfolioId);

        $plan = $this->service->calculateRebalancingPlan($portfolioId);

        // Verify plan structure and calculations
        $this->assertGreaterThan(0, $plan['total_portfolio_value']);
        $this->assertEquals(5.0, $plan['rebalance_threshold']);
        $this->assertGreaterThan(0, $plan['total_transaction_cost']);

        // Verify actions are prioritized correctly
        if (count($plan['actions']) > 1) {
            $priorities = array_column($plan['actions'], 'priority');
            $sortedPriorities = $priorities;
            rsort($sortedPriorities);
            $this->assertEquals($sortedPriorities, $priorities, 'Actions should be sorted by priority');
        }

        // Verify transaction costs are calculated
        foreach ($plan['actions'] as $action) {
            $this->assertArrayHasKey('transaction_cost', $action);
            $expectedCost = $action['amount'] * 0.001; // 0.1% transaction cost
            $this->assertEquals($expectedCost, $action['transaction_cost']);
        }
    }

    public function test_cache_behavior(): void
    {
        $portfolioId = $this->createTestPortfolio();
        $this->addBalancedAllocations($portfolioId);

        // First call should cache the result
        $needed1 = $this->service->checkRebalancingNeeded($portfolioId);
        $this->assertTrue(Cache::has("rebalancing_needed:{$portfolioId}"));

        // Second call should use cache
        $needed2 = $this->service->checkRebalancingNeeded($portfolioId);

        $this->assertEquals($needed1, $needed2);
    }

    public function test_rebalancing_recommendation_logic(): void
    {
        $portfolioId = $this->createTestPortfolio();

        // Test with high drift (should recommend)
        $this->addHighDriftAllocations($portfolioId);
        $plan = $this->service->calculateRebalancingPlan($portfolioId);
        $this->assertTrue($plan['recommended']);

        // Test with low drift (should not recommend)
        $portfolioId2 = $this->createTestPortfolio('treasury-456');
        $this->addLowDriftAllocations($portfolioId2);
        $plan2 = $this->service->calculateRebalancingPlan($portfolioId2);
        $this->assertFalse($plan2['recommended']);
    }

    private function createTestPortfolio(string $treasuryId = 'treasury-123'): string
    {
        return $this->portfolioService->createPortfolio($treasuryId, 'Test Portfolio', [
            'riskProfile'        => 'moderate',
            'rebalanceThreshold' => 5.0,
            'targetReturn'       => 0.07,
        ]);
    }

    private function addDriftedAllocations(string $portfolioId): void
    {
        $allocations = [
            [
                'assetClass'    => 'equities',
                'targetWeight'  => 60.0,
                'currentWeight' => 67.0, // 7% drift > 5% threshold
                'drift'         => 7.0,
                'amount'        => 670000,
            ],
            [
                'assetClass'    => 'bonds',
                'targetWeight'  => 40.0,
                'currentWeight' => 33.0, // 7% drift > 5% threshold
                'drift'         => 7.0,
                'amount'        => 330000,
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $allocations);
    }

    private function addBalancedAllocations(string $portfolioId): void
    {
        $allocations = [
            [
                'assetClass'    => 'equities',
                'targetWeight'  => 60.0,
                'currentWeight' => 61.0, // 1% drift < 5% threshold
                'drift'         => 1.0,
                'amount'        => 610000,
            ],
            [
                'assetClass'    => 'bonds',
                'targetWeight'  => 40.0,
                'currentWeight' => 39.0, // 1% drift < 5% threshold
                'drift'         => 1.0,
                'amount'        => 390000,
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $allocations);
    }

    private function addHighDriftAllocations(string $portfolioId): void
    {
        $allocations = [
            [
                'assetClass'    => 'equities',
                'targetWeight'  => 60.0,
                'currentWeight' => 70.0, // 10% drift - high
                'drift'         => 10.0,
                'amount'        => 700000,
            ],
            [
                'assetClass'    => 'bonds',
                'targetWeight'  => 40.0,
                'currentWeight' => 30.0, // 10% drift - high
                'drift'         => 10.0,
                'amount'        => 300000,
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $allocations);
    }

    private function addLowDriftAllocations(string $portfolioId): void
    {
        $allocations = [
            [
                'assetClass'    => 'equities',
                'targetWeight'  => 60.0,
                'currentWeight' => 62.0, // 2% drift - low
                'drift'         => 2.0,
                'amount'        => 620000,
            ],
            [
                'assetClass'    => 'bonds',
                'targetWeight'  => 40.0,
                'currentWeight' => 38.0, // 2% drift - low
                'drift'         => 2.0,
                'amount'        => 380000,
            ],
        ];

        $this->portfolioService->allocateAssets($portfolioId, $allocations);
    }
}
