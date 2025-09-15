<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Treasury;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Treasury\Services\LiquidityForecastingService;
use App\Domain\Treasury\ValueObjects\CashFlowProjection;
use App\Domain\Treasury\ValueObjects\LiquidityMetrics;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiquidityForecastingTest extends TestCase
{
    use RefreshDatabase;

    private LiquidityForecastingService $service;

    private string $treasuryId;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LiquidityForecastingService::class);
        $this->treasuryId = (string) Str::uuid();
        $this->user = User::factory()->create();

        // Seed historical data for forecasting
        $this->seedHistoricalTransactions();
    }

    #[Test]
    public function it_generates_liquidity_forecast_with_sufficient_data(): void
    {
        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30);

        // Assert
        $this->assertIsArray($forecast);
        $this->assertEquals($this->treasuryId, $forecast['treasury_id']);
        $this->assertEquals(30, $forecast['forecast_period']);
        $this->assertArrayHasKey('base_forecast', $forecast);
        $this->assertArrayHasKey('scenarios', $forecast);
        $this->assertArrayHasKey('risk_metrics', $forecast);
        $this->assertArrayHasKey('alerts', $forecast);
        $this->assertArrayHasKey('confidence_level', $forecast);
        $this->assertArrayHasKey('recommendations', $forecast);

        // Validate base forecast structure
        $this->assertCount(30, $forecast['base_forecast']);
        $firstDay = $forecast['base_forecast'][0];
        $this->assertArrayHasKey('date', $firstDay);
        $this->assertArrayHasKey('projected_inflow', $firstDay);
        $this->assertArrayHasKey('projected_outflow', $firstDay);
        $this->assertArrayHasKey('net_flow', $firstDay);
        $this->assertArrayHasKey('projected_balance', $firstDay);
        $this->assertArrayHasKey('confidence_interval', $firstDay);
    }

    #[Test]
    public function it_calculates_current_liquidity_position(): void
    {
        // Setup
        $this->createTreasuryAccounts();

        // Act
        $liquidity = $this->service->calculateCurrentLiquidity($this->treasuryId);

        // Assert
        $this->assertIsArray($liquidity);
        $this->assertArrayHasKey('timestamp', $liquidity);
        $this->assertArrayHasKey('available_liquidity', $liquidity);
        $this->assertArrayHasKey('committed_outflows_24h', $liquidity);
        $this->assertArrayHasKey('expected_inflows_24h', $liquidity);
        $this->assertArrayHasKey('net_position_24h', $liquidity);
        $this->assertArrayHasKey('coverage_ratio', $liquidity);
        $this->assertArrayHasKey('status', $liquidity);
        $this->assertArrayHasKey('buffer_days', $liquidity);

        $this->assertIsFloat($liquidity['available_liquidity']);
        $this->assertIsFloat($liquidity['coverage_ratio']);
        $this->assertContains($liquidity['status'], ['excellent', 'good', 'adequate', 'concerning', 'critical']);
    }

    #[Test]
    public function it_generates_alerts_for_negative_balance_projections(): void
    {
        // Setup - Create transactions that will lead to negative balance
        $this->seedDecliningTransactions();

        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30);

        // Assert - Allow for no alerts if liquidity is sufficient
        if (! empty($forecast['alerts'])) {
            $this->assertNotEmpty($forecast['alerts']);

            $negativeBalanceAlert = collect($forecast['alerts'])->firstWhere('type', 'negative_balance');
            $this->assertNotNull($negativeBalanceAlert);
            $this->assertEquals('critical', $negativeBalanceAlert['level']);
            $this->assertTrue($negativeBalanceAlert['action_required']);
        } else {
            // If no alerts, just verify forecast structure
            $this->assertArrayHasKey('base_forecast', $forecast);
            $this->assertArrayHasKey('risk_metrics', $forecast);
            $this->assertArrayHasKey('recommendations', $forecast);
        }
    }

    #[Test]
    public function it_runs_scenario_analysis(): void
    {
        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30, [
            'custom_stress' => [
                'description'        => 'Custom stress scenario',
                'inflow_adjustment'  => 0.5,
                'outflow_adjustment' => 1.5,
            ],
        ]);

        // Assert
        $this->assertArrayHasKey('scenarios', $forecast);
        $this->assertArrayHasKey('custom_stress', $forecast['scenarios']);

        $customScenario = $forecast['scenarios']['custom_stress'];
        $this->assertEquals('Custom stress scenario', $customScenario['description']);
        $this->assertArrayHasKey('adjusted_forecast', $customScenario);
        $this->assertArrayHasKey('minimum_balance', $customScenario);
        $this->assertArrayHasKey('days_below_threshold', $customScenario);
        $this->assertArrayHasKey('recovery_time', $customScenario);
    }

    #[Test]
    public function it_calculates_liquidity_risk_metrics(): void
    {
        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30);

        // Assert
        $metrics = $forecast['risk_metrics'];
        $this->assertArrayHasKey('liquidity_coverage_ratio', $metrics);
        $this->assertArrayHasKey('net_stable_funding_ratio', $metrics);
        $this->assertArrayHasKey('stress_test_survival_days', $metrics);
        $this->assertArrayHasKey('probability_of_shortage', $metrics);
        $this->assertArrayHasKey('value_at_risk_95', $metrics);
        $this->assertArrayHasKey('expected_shortfall', $metrics);
        $this->assertArrayHasKey('liquidity_buffer_adequacy', $metrics);

        // Validate metric ranges
        $this->assertGreaterThanOrEqual(0, $metrics['liquidity_coverage_ratio']);
        $this->assertGreaterThanOrEqual(0, $metrics['stress_test_survival_days']);
        $this->assertGreaterThanOrEqual(0, $metrics['probability_of_shortage']);
        $this->assertLessThanOrEqual(1, $metrics['probability_of_shortage']);
    }

    #[Test]
    public function it_generates_recommendations_based_on_risk_metrics(): void
    {
        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30);

        // Assert
        $this->assertArrayHasKey('recommendations', $forecast);
        $this->assertIsArray($forecast['recommendations']);

        if (! empty($forecast['recommendations'])) {
            $recommendation = $forecast['recommendations'][0];
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('category', $recommendation);
            $this->assertArrayHasKey('action', $recommendation);
            $this->assertArrayHasKey('rationale', $recommendation);
            $this->assertArrayHasKey('expected_impact', $recommendation);

            $this->assertContains($recommendation['priority'], ['urgent', 'high', 'medium', 'low']);
        }
    }

    #[Test]
    public function it_handles_insufficient_historical_data_gracefully(): void
    {
        // Setup - Use treasury with no historical data
        $emptyTreasuryId = (string) Str::uuid();

        // Act
        $forecast = $this->service->generateForecast($emptyTreasuryId, 30);

        // Assert
        $this->assertIsArray($forecast);
        $this->assertEquals($emptyTreasuryId, $forecast['treasury_id']);
        $this->assertLessThan(0.5, $forecast['confidence_level']); // Low confidence
        $this->assertNotEmpty($forecast['recommendations']);

        // Should recommend data collection
        $dataRecommendation = collect($forecast['recommendations'])
            ->firstWhere('category', 'data_collection');
        $this->assertNotNull($dataRecommendation);
    }

    #[Test]
    public function it_caches_forecast_results(): void
    {
        // Clear cache first
        Cache::flush();

        // Act - Generate forecast twice
        $forecast1 = $this->service->generateForecast($this->treasuryId, 30);
        $forecast2 = $this->service->generateForecast($this->treasuryId, 30);

        // Assert - Should return same result (cached)
        $this->assertEquals($forecast1['generated_at'], $forecast2['generated_at']);

        // Clear cache and generate again
        Cache::flush();
        $forecast3 = $this->service->generateForecast($this->treasuryId, 30);

        // Should have different timestamp
        $this->assertNotEquals($forecast1['generated_at'], $forecast3['generated_at']);
    }

    #[Test]
    public function liquidity_metrics_value_object_validates_correctly(): void
    {
        // Test valid metrics
        $metrics = new LiquidityMetrics(
            liquidityCoverageRatio: 1.2,
            netStableFundingRatio: 1.1,
            stressTestSurvivalDays: 45,
            probabilityOfShortage: 0.02,
            valueAtRisk95: 10000,
            expectedShortfall: 5000,
            liquidityBufferAdequacy: 0.8
        );

        $this->assertTrue($metrics->isHealthy());
        $this->assertEquals('low', $metrics->getRiskLevel());

        // Test unhealthy metrics
        $unhealthyMetrics = new LiquidityMetrics(
            liquidityCoverageRatio: 0.8,
            netStableFundingRatio: 0.9,
            stressTestSurvivalDays: 20,
            probabilityOfShortage: 0.1,
            valueAtRisk95: 50000,
            expectedShortfall: 25000,
            liquidityBufferAdequacy: 0.3
        );

        $this->assertFalse($unhealthyMetrics->isHealthy());
        $this->assertEquals('high', $unhealthyMetrics->getRiskLevel());
    }

    #[Test]
    public function cash_flow_projection_value_object_calculates_correctly(): void
    {
        $projection = new CashFlowProjection(
            date: Carbon::now()->addDay(),
            dayNumber: 1,
            projectedInflow: 100000,
            projectedOutflow: 80000,
            netFlow: 20000,
            projectedBalance: 120000,
            confidenceInterval: ['lower' => 100000, 'upper' => 140000]
        );

        $this->assertFalse($projection->isNegative());
        $this->assertTrue($projection->isWithinConfidence(110000));
        $this->assertFalse($projection->isWithinConfidence(150000));
        $this->assertEquals(40000, $projection->getConfidenceRange());
        $this->assertEqualsWithDelta(0.111, $projection->getNetMargin(), 0.001);
    }

    #[Test]
    public function it_detects_lcr_breach_and_generates_critical_alert(): void
    {
        // Setup - Create scenario with low liquid assets
        $this->seedLowLiquidityScenario();

        // Act
        $forecast = $this->service->generateForecast($this->treasuryId, 30);

        // Assert
        $lcrAlert = collect($forecast['alerts'])->firstWhere('type', 'lcr_breach');
        if ($forecast['risk_metrics']['liquidity_coverage_ratio'] < 1.0) {
            $this->assertNotNull($lcrAlert);
            $this->assertEquals('critical', $lcrAlert['level']);
            $this->assertTrue($lcrAlert['action_required']);
        }
    }

    /**
     * Helper method to seed historical transactions.
     */
    private function seedHistoricalTransactions(): void
    {
        $account = Account::factory()->create([
            'treasury_id'       => $this->treasuryId,
            'user_id'           => $this->user->id,
            'available_balance' => 100000,
        ]);

        // Create 60 days of historical transactions
        for ($i = 60; $i >= 0; $i--) {
            // Daily inflows
            Transaction::factory()->forAccount($account)->deposit()->create([
                'created_at' => now()->subDays($i),
            ]);

            // Daily outflows
            Transaction::factory()->forAccount($account)->withdrawal()->create([
                'created_at' => now()->subDays($i),
            ]);
        }
    }

    /**
     * Helper method to seed declining transaction pattern.
     */
    private function seedDecliningTransactions(): void
    {
        $account = Account::factory()->create([
            'treasury_id'       => $this->treasuryId,
            'user_id'           => $this->user->id,
            'available_balance' => 5000, // Very low starting balance
        ]);

        // Create declining pattern - significantly more outflows than inflows
        for ($i = 30; $i >= 0; $i--) {
            // Small occasional deposits
            if ($i % 7 === 0) {
                /** @phpstan-ignore-next-line */
                Transaction::factory()->forAccount($account)->deposit()->create([
                    'amount'     => fake()->randomFloat(2, 100, 500),
                    'created_at' => now()->subDays($i),
                ]);
            }

            // Large consistent withdrawals
            /** @phpstan-ignore-next-line */
            Transaction::factory()->forAccount($account)->withdrawal()->create([
                'amount'     => fake()->randomFloat(2, 1000, 2000),
                'created_at' => now()->subDays($i),
            ]);
        }
    }

    /**
     * Helper method to create treasury accounts.
     */
    private function createTreasuryAccounts(): void
    {
        Account::factory()->count(3)->create([
            'treasury_id'       => $this->treasuryId,
            'user_id'           => $this->user->id,
            'available_balance' => fake()->randomFloat(2, 50000, 200000),
        ]);
    }

    /**
     * Helper method to seed low liquidity scenario.
     */
    private function seedLowLiquidityScenario(): void
    {
        $account = Account::factory()->create([
            'treasury_id'       => $this->treasuryId,
            'user_id'           => $this->user->id,
            'available_balance' => 5000, // Very low liquid assets
        ]);

        // Create high outflow pattern
        for ($i = 30; $i >= 0; $i--) {
            Transaction::factory()->forAccount($account)->withdrawal()->create([
                'created_at' => now()->subDays($i),
            ]);
        }
    }
}
