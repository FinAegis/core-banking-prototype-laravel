<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury\Workflows;

use App\Domain\Treasury\Activities\Portfolio\ApproveRebalancingActivity;
use App\Domain\Treasury\Events\Portfolio\RebalancingApprovalReceived;
use App\Domain\Treasury\Events\Portfolio\RebalancingApprovalRequested;
use App\Domain\Treasury\Events\Portfolio\RebalancingCompleted;
use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use App\Domain\Treasury\Workflows\PortfolioRebalancingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use Workflow\WorkflowStub;

class PortfolioRebalancingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $portfolioId;

    private PortfolioManagementService $portfolioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->portfolioId = Str::uuid()->toString();
        $this->portfolioService = app(PortfolioManagementService::class);

        // Create a test portfolio
        $this->createTestPortfolio();

        Event::fake();
    }

    /** @test */
    public function testCompletesRebalancingWorkflowSuccessfullyWithoutApproval()
    {
        // Arrange - Create a portfolio that needs minor rebalancing (auto-approved)
        $this->mockMinorRebalancingScenario();

        // Act
        $result = WorkflowStub::make(PortfolioRebalancingWorkflow::class)
            ->execute($this->portfolioId, 'scheduled_rebalancing');

        // Assert
        expect($result['success'])->toBeTrue();
        expect($result['action_taken'])->toBe('completed');
        expect($result['portfolio_id'])->toBe($this->portfolioId);
        expect($result['approval_required'])->toBeFalse();

        // Verify events were dispatched
        Event::assertDispatched(RebalancingCompleted::class);
        Event::assertNotDispatched(RebalancingApprovalRequested::class);
    }

    /** @test */
    public function testRequiresApprovalForHighValueRebalancing()
    {
        // Arrange - Create a portfolio that needs major rebalancing requiring approval
        $this->mockMajorRebalancingScenario();

        // Mock approval timeout (rejection)
        $this->mockApprovalTimeout();

        // Act
        $result = WorkflowStub::make(PortfolioRebalancingWorkflow::class)
            ->execute($this->portfolioId, 'risk_review');

        // Assert
        expect($result['success'])->toBeFalse();
        expect($result['action_taken'])->toBe('rejected');
        expect($result['reason'])->toContain('timeout');

        // Verify approval was requested
        Event::assertDispatched(RebalancingApprovalRequested::class);
        Event::assertNotDispatched(RebalancingCompleted::class);
    }

    /** @test */
    public function testHandlesManualApprovalSuccessfully()
    {
        // Arrange
        $this->mockMajorRebalancingScenario();

        // Create workflow with manual approval
        $workflow = WorkflowStub::make(PortfolioRebalancingWorkflow::class);

        // Approval will be simulated via event dispatching below

        // Act
        $result = $workflow->execute($this->portfolioId, 'quarterly_review');

        // Simulate the approval being received
        Event::dispatch(new RebalancingApprovalReceived(
            $this->portfolioId,
            'test-rebalance-id',
            'test-approval-id',
            true,
            'portfolio_manager_001',
            'Approved for quarterly rebalancing',
            ['approved_at' => now()->toISOString()]
        ));

        // Assert
        expect($result['success'])->toBeTrue();
        expect($result['approved_by'])->toBe('manual');

        Event::assertDispatched(RebalancingApprovalRequested::class);
        Event::assertDispatched(RebalancingCompleted::class);
    }

    /** @test */
    public function testSkipsRebalancingWhenNotNeeded()
    {
        // Arrange - Portfolio is already balanced
        $this->mockBalancedPortfolioScenario();

        // Act
        $result = WorkflowStub::make(PortfolioRebalancingWorkflow::class)
            ->execute($this->portfolioId, 'scheduled_check');

        // Assert
        expect($result['success'])->toBeTrue();
        expect($result['action_taken'])->toBe('none');
        expect($result['needs_attention'])->toBeFalse();
        expect($result['reason'])->toContain('not needed');
    }

    /** @test */
    public function testHandlesExecutionFailuresWithCompensation()
    {
        // Arrange - Set up scenario that will fail during execution
        $this->mockRebalancingExecutionFailure();

        // Act & Assert - Expect exception to be thrown
        expect(function () {
            WorkflowStub::make(PortfolioRebalancingWorkflow::class)
                ->execute($this->portfolioId, 'test_failure');
        })->toThrow(RuntimeException::class);

        // Verify compensation was triggered (failure notification sent)
        Event::assertDispatched(RebalancingApprovalRequested::class, function ($event) {
            return $event->portfolioId === $this->portfolioId;
        });
    }

    /** @test */
    public function testHandlesPortfolioAlreadyRebalancingScenario()
    {
        // Arrange - Mark portfolio as currently rebalancing
        $this->mockPortfolioCurrentlyRebalancing();

        // Act
        $result = WorkflowStub::make(PortfolioRebalancingWorkflow::class)
            ->execute($this->portfolioId, 'duplicate_request');

        // Assert
        expect($result['success'])->toBeTrue();
        expect($result['action_taken'])->toBe('none');
        expect($result['reason'])->toContain('already being rebalanced');
    }

    /** @test */
    public function testProcessesForceRebalancingOverride()
    {
        // Arrange - Balanced portfolio but force rebalancing
        $this->mockBalancedPortfolioScenario();

        $overrides = [
            'force_rebalancing'    => true,
            'max_transaction_cost' => 50000,
        ];

        // Act
        $result = WorkflowStub::make(PortfolioRebalancingWorkflow::class)
            ->execute($this->portfolioId, 'manual_override', $overrides);

        // Assert
        expect($result['success'])->toBeTrue();
        expect($result['action_taken'])->toBe('completed');
        expect($result['approval_required'])->toBeFalse(); // Should be auto-approved even if forced
    }

    /** @test */
    public function testCalculatesApprovalRequirementsCorrectly()
    {
        $workflow = WorkflowStub::make(PortfolioRebalancingWorkflow::class);

        // Test cases for approval requirements
        $testCases = [
            // Small rebalancing - no approval needed
            [
                'plan' => [
                    'total_transaction_cost' => 5000,
                    'risk_impact'            => 'low_risk_reduction',
                    'actions'                => [
                        ['asset_class' => 'bonds', 'amount' => 2500],
                        ['asset_class' => 'stocks', 'amount' => 2500],
                    ],
                    'total_portfolio_value' => 100000,
                ],
                'expected' => false,
            ],
            // High cost - requires approval
            [
                'plan' => [
                    'total_transaction_cost' => 15000,
                    'risk_impact'            => 'moderate_risk_reduction',
                    'actions'                => [
                        ['asset_class' => 'bonds', 'amount' => 15000],
                    ],
                    'total_portfolio_value' => 100000,
                ],
                'expected' => true,
            ],
            // High risk impact - requires approval
            [
                'plan' => [
                    'total_transaction_cost' => 5000,
                    'risk_impact'            => 'high_risk_reduction',
                    'actions'                => [
                        ['asset_class' => 'alternatives', 'amount' => 5000],
                    ],
                    'total_portfolio_value' => 100000,
                ],
                'expected' => true,
            ],
            // Many actions - requires approval
            [
                'plan' => [
                    'total_transaction_cost' => 8000,
                    'risk_impact'            => 'low_risk_reduction',
                    'actions'                => array_fill(0, 6, ['asset_class' => 'mixed', 'amount' => 1333]),
                    'total_portfolio_value'  => 100000,
                ],
                'expected' => true,
            ],
            // Large percentage of portfolio - requires approval
            [
                'plan' => [
                    'total_transaction_cost' => 5000,
                    'risk_impact'            => 'low_risk_reduction',
                    'actions'                => [
                        ['asset_class' => 'stocks', 'amount' => 25000], // 25% of portfolio
                    ],
                    'total_portfolio_value' => 100000,
                ],
                'expected' => true,
            ],
        ];

        foreach ($testCases as $index => $testCase) {
            $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$testCase['plan']]);
            expect($requiresApproval)
                ->toBe($testCase['expected'])
                ->and("Test case {$index} failed");
        }
    }

    // Helper methods for mocking scenarios

    private function createTestPortfolio(): void
    {
        $strategy = [
            'riskProfile'        => 'moderate',
            'rebalanceThreshold' => 5.0,
            'targetReturn'       => 8.0,
        ];

        // Create portfolio through the service
        $this->portfolioService->createPortfolio(
            'treasury-001',
            'Test Portfolio',
            $strategy
        );

        // Mock asset allocations
        $allocations = [
            [
                'assetClass'    => 'stocks',
                'targetWeight'  => 60.0,
                'currentWeight' => 58.0,
                'drift'         => 2.0,
                'amount'        => 58000,
            ],
            [
                'assetClass'    => 'bonds',
                'targetWeight'  => 30.0,
                'currentWeight' => 32.0,
                'drift'         => 2.0,
                'amount'        => 32000,
            ],
            [
                'assetClass'    => 'cash',
                'targetWeight'  => 10.0,
                'currentWeight' => 10.0,
                'drift'         => 0.0,
                'amount'        => 10000,
            ],
        ];

        $this->portfolioService->allocateAssets($this->portfolioId, $allocations);
    }

    private function mockMinorRebalancingScenario(): void
    {
        $this->mock(RebalancingService::class, function ($mock) {
            $mock->shouldReceive('checkRebalancingNeeded')
                ->with($this->portfolioId)
                ->andReturn(true);

            $mock->shouldReceive('calculateRebalancingPlan')
                ->with($this->portfolioId)
                ->andReturn([
                    'portfolio_id'           => $this->portfolioId,
                    'total_transaction_cost' => 2000, // Low cost
                    'actions'                => [
                        [
                            'asset_class'   => 'stocks',
                            'action_type'   => 'buy',
                            'amount'        => 2000,
                            'target_weight' => 60,
                        ],
                    ],
                    'risk_impact'           => 'low_risk_reduction',
                    'recommended'           => true,
                    'net_benefit'           => 5000,
                    'total_portfolio_value' => 100000,
                ]);

            $mock->shouldReceive('executeRebalancing')
                ->with($this->portfolioId, Mockery::any())
                ->andReturn(null);
        });
    }

    private function mockMajorRebalancingScenario(): void
    {
        $this->mock(RebalancingService::class, function ($mock) {
            $mock->shouldReceive('checkRebalancingNeeded')
                ->with($this->portfolioId)
                ->andReturn(true);

            $mock->shouldReceive('calculateRebalancingPlan')
                ->with($this->portfolioId)
                ->andReturn([
                    'portfolio_id'           => $this->portfolioId,
                    'total_transaction_cost' => 25000, // High cost requiring approval
                    'actions'                => [
                        [
                            'asset_class'   => 'stocks',
                            'action_type'   => 'sell',
                            'amount'        => 15000,
                            'target_weight' => 45,
                        ],
                        [
                            'asset_class'   => 'alternatives',
                            'action_type'   => 'buy',
                            'amount'        => 15000,
                            'target_weight' => 15,
                        ],
                    ],
                    'risk_impact'           => 'high_risk_reduction',
                    'recommended'           => true,
                    'net_benefit'           => 50000,
                    'total_portfolio_value' => 500000,
                ]);
        });
    }

    private function mockBalancedPortfolioScenario(): void
    {
        $this->mock(RebalancingService::class, function ($mock) {
            $mock->shouldReceive('checkRebalancingNeeded')
                ->with($this->portfolioId)
                ->andReturn(false);
        });
    }

    private function mockPortfolioCurrentlyRebalancing(): void
    {
        $this->mock(PortfolioManagementService::class, function ($mock) {
            $mock->shouldReceive('getPortfolio')
                ->with($this->portfolioId)
                ->andReturn([
                    'portfolio_id'   => $this->portfolioId,
                    'name'           => 'Test Portfolio',
                    'is_rebalancing' => true,
                    'status'         => 'rebalancing',
                ]);
        });
    }

    private function mockApprovalTimeout(): void
    {
        // Mock the approval activity to timeout
        $this->mock(ApproveRebalancingActivity::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->andReturn([
                    'approved'         => false,
                    'approval_id'      => 'timeout-approval',
                    'approver_id'      => 'timeout',
                    'comments'         => 'Approval request timed out',
                    'timed_out'        => true,
                    'rejection_reason' => 'Approval request timed out after 60 minutes',
                ]);
        });
    }

    private function mockRebalancingExecutionFailure(): void
    {
        $this->mock(RebalancingService::class, function ($mock) {
            $mock->shouldReceive('checkRebalancingNeeded')->andReturn(true);
            $mock->shouldReceive('calculateRebalancingPlan')->andReturn([
                'portfolio_id'           => $this->portfolioId,
                'total_transaction_cost' => 5000,
                'actions'                => [['asset_class' => 'stocks', 'amount' => 5000]],
                'risk_impact'            => 'low_risk_reduction',
                'total_portfolio_value'  => 100000,
            ]);
            $mock->shouldReceive('executeRebalancing')
                ->andThrow(new RuntimeException('Market connectivity failure'));
        });
    }

    private function callPrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
