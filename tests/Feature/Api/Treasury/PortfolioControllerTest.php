<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Treasury;

use App\Domain\Treasury\Services\AssetValuationService;
use App\Domain\Treasury\Services\PerformanceTrackingService;
use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use App\Domain\Treasury\ValueObjects\InvestmentStrategy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PortfolioControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $testUser;

    protected string $treasuryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->treasuryId = $this->testUser->uuid;

        Sanctum::actingAs($this->testUser, ['treasury']);

        // Verify the scope is set correctly in tests
        $this->assertTrue($this->testUser->tokenCan('treasury'), 'User should have treasury scope');

        // Clear any existing caches
        Cache::flush();
    }

    #[Test]
    public function it_can_list_treasury_portfolios(): void
    {
        $response = $this->getJson('/api/treasury/portfolios');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'portfolio_id',
                        'treasury_id',
                        'name',
                        'status',
                        'total_value',
                        'asset_count',
                        'is_rebalancing',
                        'last_rebalance_date',
                    ],
                ],
                'meta' => [
                    'total',
                    'count',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function it_can_list_portfolios_with_treasury_id_filter(): void
    {
        $response = $this->getJson("/api/treasury/portfolios?treasury_id={$this->treasuryId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function it_requires_authentication_to_list_portfolios(): void
    {
        Sanctum::actingAs($this->testUser, []);  // No treasury scope

        $response = $this->getJson('/api/treasury/portfolios');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_create_a_treasury_portfolio(): void
    {
        $portfolioData = [
            'treasury_id' => $this->treasuryId,
            'name'        => 'Conservative Growth Portfolio',
            'strategy'    => [
                'riskProfile'        => InvestmentStrategy::RISK_CONSERVATIVE,
                'rebalanceThreshold' => 5.0,
                'targetReturn'       => 0.08,
                'constraints'        => [
                    'maxEquityAllocation' => 30.0,
                    'minCashAllocation'   => 20.0,
                ],
                'metadata' => [
                    'created_by'         => 'portfolio_manager',
                    'investment_horizon' => '3-5 years',
                ],
            ],
        ];

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioId = Str::uuid()->toString();

        $portfolioService->shouldReceive('createPortfolio')
            ->once()
            ->with($this->treasuryId, 'Conservative Growth Portfolio', $portfolioData['strategy'])
            ->andReturn($portfolioId);

        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'portfolio_id'        => $portfolioId,
                'treasury_id'         => $this->treasuryId,
                'name'                => 'Conservative Growth Portfolio',
                'strategy'            => $portfolioData['strategy'],
                'asset_allocations'   => [],
                'latest_metrics'      => [],
                'total_value'         => 0.0,
                'status'              => 'active',
                'is_rebalancing'      => false,
                'last_rebalance_date' => null,
            ]);

        $response = $this->postJson('/api/treasury/portfolios', $portfolioData);

        if ($response->status() !== 201) {
            dump('Response status: ' . $response->status());
            dump('Response body: ' . $response->content());
        }

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio created successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portfolio_id',
                    'treasury_id',
                    'name',
                    'strategy',
                    'asset_allocations',
                    'total_value',
                    'status',
                    'is_rebalancing',
                ],
                'message',
            ]);
    }

    #[Test]
    public function it_validates_portfolio_creation_data(): void
    {
        $invalidData = [
            'treasury_id' => 'not-a-uuid',
            'name'        => 'A', // Too short
            'strategy'    => [
                'riskProfile'        => 'invalid_risk',
                'rebalanceThreshold' => -5.0, // Invalid range
                'targetReturn'       => -0.1, // Negative return
            ],
        ];

        $response = $this->postJson('/api/treasury/portfolios', $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'treasury_id',
                'name',
                'strategy.riskProfile',
                'strategy.rebalanceThreshold',
                'strategy.targetReturn',
            ]);
    }

    #[Test]
    public function it_can_show_a_specific_portfolio(): void
    {
        $portfolioId = Str::uuid()->toString();

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $rebalancingService = $this->mock(RebalancingService::class);

        $portfolioData = [
            'portfolio_id' => $portfolioId,
            'treasury_id'  => $this->treasuryId,
            'name'         => 'Test Portfolio',
            'strategy'     => [
                'riskProfile'        => InvestmentStrategy::RISK_MODERATE,
                'rebalanceThreshold' => 5.0,
                'targetReturn'       => 0.10,
            ],
            'asset_allocations' => [
                [
                    'assetClass'    => 'equities',
                    'targetWeight'  => 60.0,
                    'currentWeight' => 58.0,
                    'drift'         => 2.0,
                ],
                [
                    'assetClass'    => 'bonds',
                    'targetWeight'  => 40.0,
                    'currentWeight' => 42.0,
                    'drift'         => 2.0,
                ],
            ],
            'latest_metrics'      => null,
            'total_value'         => 100000.0,
            'status'              => 'active',
            'is_rebalancing'      => false,
            'last_rebalance_date' => null,
        ];

        $summaryData = [
            'total_allocations' => 2,
            'average_drift'     => 2.0,
            'maximum_drift'     => 2.0,
            'needs_rebalancing' => false,
        ];

        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn($portfolioData);

        $portfolioService->shouldReceive('getPortfolioSummary')
            ->once()
            ->with($portfolioId)
            ->andReturn($summaryData);

        $rebalancingService->shouldReceive('checkRebalancingNeeded')
            ->once()
            ->with($portfolioId)
            ->andReturn(false);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}");

        if ($response->status() === 500) {
            dump('500 Error Response: ' . $response->content());
        }

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'portfolio_id'      => $portfolioId,
                    'summary'           => $summaryData,
                    'needs_rebalancing' => false,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portfolio_id',
                    'treasury_id',
                    'name',
                    'strategy',
                    'asset_allocations',
                    'summary',
                    'needs_rebalancing',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_portfolio(): void
    {
        $portfolioId = Str::uuid()->toString();

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andThrow(new RuntimeException('Portfolio not found'));

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Portfolio not found',
            ]);
    }

    #[Test]
    public function it_can_update_portfolio_strategy(): void
    {
        $portfolioId = Str::uuid()->toString();
        $newStrategy = [
            'riskProfile'        => InvestmentStrategy::RISK_AGGRESSIVE,
            'rebalanceThreshold' => 7.5,
            'targetReturn'       => 0.15,
            'constraints'        => [
                'maxEquityAllocation' => 80.0,
            ],
        ];

        $portfolioService = $this->mock(PortfolioManagementService::class);

        $portfolioService->shouldReceive('updateStrategy')
            ->once()
            ->with($portfolioId, $newStrategy);

        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'portfolio_id'        => $portfolioId,
                'treasury_id'         => $this->treasuryId,
                'name'                => 'Updated Portfolio',
                'strategy'            => $newStrategy,
                'asset_allocations'   => [],
                'latest_metrics'      => [],
                'total_value'         => 0.0,
                'status'              => 'active',
                'is_rebalancing'      => false,
                'last_rebalance_date' => null,
            ]);

        $response = $this->putJson("/api/treasury/portfolios/{$portfolioId}", [
            'strategy' => $newStrategy,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio strategy updated successfully',
                'data'    => [
                    'strategy' => $newStrategy,
                ],
            ]);
    }

    #[Test]
    public function it_can_allocate_assets_to_portfolio(): void
    {
        $portfolioId = Str::uuid()->toString();
        $allocations = [
            [
                'assetClass'   => 'equities',
                'targetWeight' => 60.0,
                'amount'       => 60000.0,
            ],
            [
                'assetClass'   => 'bonds',
                'targetWeight' => 30.0,
                'amount'       => 30000.0,
            ],
            [
                'assetClass'   => 'cash',
                'targetWeight' => 10.0,
                'amount'       => 10000.0,
            ],
        ];

        $portfolioService = $this->mock(PortfolioManagementService::class);

        $portfolioService->shouldReceive('allocateAssets')
            ->once()
            ->withAnyArgs()
            ->andReturn(null);

        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'portfolio_id'        => $portfolioId,
                'treasury_id'         => $this->treasuryId,
                'name'                => 'Test Portfolio',
                'strategy'            => [],
                'asset_allocations'   => $allocations,
                'latest_metrics'      => [],
                'total_value'         => 100000.0,
                'status'              => 'active',
                'is_rebalancing'      => false,
                'last_rebalance_date' => null,
            ]);

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/allocate", [
            'allocations' => $allocations,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Assets allocated successfully',
                'data'    => [
                    'asset_allocations' => $allocations,
                    'total_value'       => 100000.0,
                ],
            ]);
    }

    #[Test]
    public function it_validates_asset_allocation_data(): void
    {
        $portfolioId = Str::uuid()->toString();

        $invalidAllocations = [
            [
                'assetClass'   => '', // Empty asset class
                'targetWeight' => 150.0, // Over 100%
            ],
            [
                'assetClass'   => 'bonds',
                'targetWeight' => -10.0, // Negative weight
            ],
        ];

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/allocate", [
            'allocations' => $invalidAllocations,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'allocations',
                'allocations.0.assetClass',
                'allocations.0.targetWeight',
                'allocations.1.targetWeight',
            ]);
    }

    #[Test]
    public function it_can_get_current_allocations(): void
    {
        $portfolioId = Str::uuid()->toString();
        $allocations = [
            [
                'assetClass'    => 'equities',
                'targetWeight'  => 60.0,
                'currentWeight' => 58.0,
                'drift'         => 2.0,
            ],
        ];

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'asset_allocations' => $allocations,
                'total_value'       => 100000.0,
            ]);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}/allocations");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'portfolio_id' => $portfolioId,
                    'allocations'  => $allocations,
                    'total_value'  => 100000.0,
                ],
            ]);
    }

    #[Test]
    public function it_can_trigger_portfolio_rebalancing(): void
    {
        $portfolioId = Str::uuid()->toString();

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/rebalance", [
            'reason' => 'manual_trigger',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Rebalancing workflow started successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'workflow_id',
                    'portfolio_id',
                    'status',
                ],
                'message',
            ]);
    }

    #[Test]
    public function it_can_get_rebalancing_plan(): void
    {
        $portfolioId = Str::uuid()->toString();
        $rebalancingPlan = [
            'portfolio_id'          => $portfolioId,
            'total_portfolio_value' => 100000.0,
            'rebalance_threshold'   => 5.0,
            'actions'               => [
                [
                    'asset_class'   => 'equities',
                    'action_type'   => 'buy',
                    'amount'        => 2000.0,
                    'target_weight' => 60.0,
                ],
            ],
            'total_transaction_cost' => 20.0,
            'recommended'            => true,
        ];

        $rebalancingService = $this->mock(RebalancingService::class);
        $rebalancingService->shouldReceive('calculateRebalancingPlan')
            ->once()
            ->with($portfolioId)
            ->andReturn($rebalancingPlan);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}/rebalancing-plan");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => $rebalancingPlan,
            ]);
    }

    #[Test]
    public function it_can_approve_rebalancing(): void
    {
        $portfolioId = Str::uuid()->toString();
        $rebalancingPlan = [
            'portfolio_id' => $portfolioId,
            'actions'      => [
                [
                    'asset_class'   => 'equities',
                    'action_type'   => 'buy',
                    'amount'        => 2000.0,
                    'target_weight' => 60.0,
                ],
                [
                    'asset_class'   => 'bonds',
                    'action_type'   => 'sell',
                    'amount'        => 2000.0,
                    'target_weight' => 40.0,
                ],
            ],
            'total_transaction_cost' => 40.0,
        ];

        $rebalancingService = $this->mock(RebalancingService::class);
        $rebalancingService->shouldReceive('executeRebalancing')
            ->once()
            ->with($portfolioId, $rebalancingPlan);

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/approve-rebalancing", [
            'plan'                => $rebalancingPlan,
            'risk_acknowledgment' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Rebalancing executed successfully',
            ]);
    }

    #[Test]
    public function it_validates_rebalancing_approval_requires_risk_acknowledgment(): void
    {
        $portfolioId = Str::uuid()->toString();

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/approve-rebalancing", [
            'plan' => [
                'portfolio_id' => $portfolioId,
                'actions'      => [],
            ],
            'risk_acknowledgment' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['risk_acknowledgment']);
    }

    #[Test]
    public function it_can_get_portfolio_performance(): void
    {
        $portfolioId = Str::uuid()->toString();
        $performanceData = [
            'returns' => [
                '1d'  => 0.012,
                '7d'  => 0.045,
                '30d' => 0.078,
            ],
            'volatility'   => 0.15,
            'sharpe_ratio' => 1.25,
        ];

        $rebalancingMetrics = [
            'total_rebalances' => 3,
            'last_rebalance'   => '2024-01-15T10:30:00Z',
            'success_rate'     => 100,
        ];

        $performanceService = $this->mock(PerformanceTrackingService::class);
        $rebalancingService = $this->mock(RebalancingService::class);

        $performanceService->shouldReceive('getPortfolioPerformance')
            ->once()
            ->with($portfolioId, '30d')
            ->andReturn($performanceData);

        $rebalancingService->shouldReceive('getRebalancingMetrics')
            ->once()
            ->with($portfolioId)
            ->andReturn($rebalancingMetrics);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}/performance?period=30d");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'portfolio_id'        => $portfolioId,
                    'period'              => '30d',
                    'performance'         => $performanceData,
                    'rebalancing_metrics' => $rebalancingMetrics,
                ],
            ]);
    }

    #[Test]
    public function it_can_get_portfolio_valuation(): void
    {
        $portfolioId = Str::uuid()->toString();
        $valuationAmount = 105000.0;

        $valuationService = $this->mock(AssetValuationService::class);
        $valuationService->shouldReceive('calculatePortfolioValue')
            ->once()
            ->with($portfolioId)
            ->andReturn($valuationAmount);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}/valuation");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => [
                    'portfolio_id' => $portfolioId,
                    'valuation'    => $valuationAmount,
                ],
            ]);
    }

    #[Test]
    public function it_can_generate_portfolio_report(): void
    {
        $portfolioId = Str::uuid()->toString();

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/reports", [
            'type'   => 'performance',
            'period' => '90d',
            'format' => 'pdf',
        ]);

        $response->assertAccepted()
            ->assertJson([
                'success' => true,
                'message' => 'Report generation started successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'workflow_id',
                    'portfolio_id',
                    'status',
                ],
                'message',
            ]);
    }

    #[Test]
    public function it_validates_report_generation_parameters(): void
    {
        $portfolioId = Str::uuid()->toString();

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/reports", [
            'type'   => 'invalid_type',
            'period' => 'invalid_period',
            'format' => 'invalid_format',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'period',
                'format',
            ]);
    }

    #[Test]
    public function it_can_list_portfolio_reports(): void
    {
        $portfolioId = Str::uuid()->toString();
        $reports = [
            [
                'report_id'    => Str::uuid()->toString(),
                'type'         => 'performance',
                'period'       => '90d',
                'status'       => 'completed',
                'generated_at' => '2024-01-20T10:00:00Z',
            ],
        ];

        $performanceService = $this->mock(PerformanceTrackingService::class);
        $performanceService->shouldReceive('getPortfolioReports')
            ->once()
            ->with($portfolioId)
            ->andReturn($reports);

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}/reports");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data'    => $reports,
            ]);
    }

    #[Test]
    public function it_can_delete_portfolio(): void
    {
        $portfolioId = Str::uuid()->toString();

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'is_rebalancing' => false,
                'status'         => 'active',
            ]);

        $response = $this->deleteJson("/api/treasury/portfolios/{$portfolioId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Portfolio deletion scheduled successfully',
            ]);
    }

    #[Test]
    public function it_prevents_deletion_of_rebalancing_portfolio(): void
    {
        $portfolioId = Str::uuid()->toString();

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andReturn([
                'is_rebalancing' => true,
                'status'         => 'active',
            ]);

        $response = $this->deleteJson("/api/treasury/portfolios/{$portfolioId}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete portfolio while rebalancing is in progress',
            ]);
    }

    #[Test]
    public function it_handles_service_exceptions_gracefully(): void
    {
        $portfolioId = Str::uuid()->toString();

        $portfolioService = $this->mock(PortfolioManagementService::class);
        $portfolioService->shouldReceive('getPortfolio')
            ->once()
            ->with($portfolioId)
            ->andThrow(new RuntimeException('Database connection failed'));

        $response = $this->getJson("/api/treasury/portfolios/{$portfolioId}");

        $response->assertInternalServerError()
            ->assertJson([
                'success' => false,
                'message' => 'Failed to retrieve portfolio',
            ]);
    }

    #[Test]
    public function it_requires_proper_permissions_for_sensitive_operations(): void
    {
        // Test without proper treasury permissions
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read']); // Only read scope

        $portfolioId = Str::uuid()->toString();

        $response = $this->postJson("/api/treasury/portfolios/{$portfolioId}/approve-rebalancing", [
            'plan' => [
                'portfolio_id' => $portfolioId,
                'actions'      => [],
            ],
            'risk_acknowledgment' => true,
        ]);

        $response->assertStatus(403);
    }
}
