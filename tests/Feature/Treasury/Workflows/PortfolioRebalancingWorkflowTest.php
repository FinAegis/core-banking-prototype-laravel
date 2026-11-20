<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury\Workflows;

use App\Domain\Treasury\Activities\Portfolio\ApproveRebalancingActivity;
use App\Domain\Treasury\Activities\Portfolio\CalculateRebalancingPlanActivity;
use App\Domain\Treasury\Activities\Portfolio\CheckRebalancingNeedActivity;
use App\Domain\Treasury\Activities\Portfolio\ExecuteRebalancingActivity;
use App\Domain\Treasury\Activities\Portfolio\NotifyRebalancingCompleteActivity;
use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use App\Domain\Treasury\Workflows\PortfolioRebalancingWorkflow;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;

/**
 * Tests for PortfolioRebalancingWorkflow business logic.
 *
 * Note: The workflow's execute() method is a generator that uses `yield Activity::make()`
 * and requires the laravel-workflow runtime to orchestrate. These tests verify the
 * workflow's decision logic (requiresHumanApproval) and structural correctness.
 * The underlying services (RebalancingService, PortfolioManagementService) are tested
 * separately in their own test files.
 */
class PortfolioRebalancingWorkflowTest extends TestCase
{
    private string $portfolioId;

    private PortfolioManagementService $portfolioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->portfolioId = Str::uuid()->toString();
        $this->portfolioService = app(PortfolioManagementService::class);

        // Create a test portfolio
        $this->createTestPortfolio();
    }

    #[Test]
    public function testWorkflowClassHasCorrectStructure(): void
    {
        $reflection = new ReflectionClass(PortfolioRebalancingWorkflow::class);

        // Verify the workflow has the required methods
        $this->assertTrue($reflection->hasMethod('execute'), 'Workflow must have execute method');
        $this->assertTrue($reflection->hasMethod('compensate'), 'Workflow must have compensate method');
        $this->assertTrue($reflection->hasMethod('requiresHumanApproval'), 'Workflow must have requiresHumanApproval method');

        // Verify execute is a generator (it uses yield)
        $executeMethod = $reflection->getMethod('execute');
        $this->assertTrue($executeMethod->isGenerator(), 'execute() must be a generator method');

        // Verify it accepts the expected parameters
        $params = $executeMethod->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('portfolioId', $params[0]->getName());
        $this->assertEquals('reason', $params[1]->getName());
        $this->assertEquals('overrides', $params[2]->getName());
    }

    #[Test]
    public function testWorkflowReferencesRequiredActivities(): void
    {
        // Verify all required activity classes exist
        $requiredActivities = [
            CheckRebalancingNeedActivity::class,
            CalculateRebalancingPlanActivity::class,
            ApproveRebalancingActivity::class,
            ExecuteRebalancingActivity::class,
            NotifyRebalancingCompleteActivity::class,
        ];

        foreach ($requiredActivities as $activity) {
            $this->assertNotEmpty((new ReflectionClass($activity))->getName(), "Activity class {$activity} must exist");
        }
    }

    #[Test]
    public function testSmallRebalancingDoesNotRequireApproval(): void
    {
        $workflow = $this->createWorkflowInstance();

        $plan = [
            'total_transaction_cost' => 5000,
            'risk_impact'            => 'low_risk_reduction',
            'actions'                => [
                ['asset_class' => 'bonds', 'amount' => 2500],
                ['asset_class' => 'stocks', 'amount' => 2500],
            ],
            'total_portfolio_value' => 100000,
        ];

        $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$plan]);
        expect($requiresApproval)->toBeFalse();
    }

    #[Test]
    public function testHighCostRebalancingRequiresApproval(): void
    {
        $workflow = $this->createWorkflowInstance();

        $plan = [
            'total_transaction_cost' => 15000, // > $10,000 threshold
            'risk_impact'            => 'moderate_risk_reduction',
            'actions'                => [
                ['asset_class' => 'bonds', 'amount' => 15000],
            ],
            'total_portfolio_value' => 100000,
        ];

        $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$plan]);
        expect($requiresApproval)->toBeTrue();
    }

    #[Test]
    public function testHighRiskRebalancingRequiresApproval(): void
    {
        $workflow = $this->createWorkflowInstance();

        $plan = [
            'total_transaction_cost' => 5000,
            'risk_impact'            => 'high_risk_reduction',
            'actions'                => [
                ['asset_class' => 'alternatives', 'amount' => 5000],
            ],
            'total_portfolio_value' => 100000,
        ];

        $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$plan]);
        expect($requiresApproval)->toBeTrue();
    }

    #[Test]
    public function testManyActionsRequireApproval(): void
    {
        $workflow = $this->createWorkflowInstance();

        $plan = [
            'total_transaction_cost' => 8000,
            'risk_impact'            => 'low_risk_reduction',
            'actions'                => array_fill(0, 6, ['asset_class' => 'mixed', 'amount' => 1333]),
            'total_portfolio_value'  => 100000,
        ];

        $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$plan]);
        expect($requiresApproval)->toBeTrue();
    }

    #[Test]
    public function testLargePortfolioPercentageRequiresApproval(): void
    {
        $workflow = $this->createWorkflowInstance();

        $plan = [
            'total_transaction_cost' => 5000,
            'risk_impact'            => 'low_risk_reduction',
            'actions'                => [
                ['asset_class' => 'stocks', 'amount' => 25000], // 25% of portfolio
            ],
            'total_portfolio_value' => 100000,
        ];

        $requiresApproval = $this->callPrivateMethod($workflow, 'requiresHumanApproval', [$plan]);
        expect($requiresApproval)->toBeTrue();
    }

    #[Test]
    public function testUnderlyingServicesAreAvailable(): void
    {
        // Verify the services that the workflow depends on can be resolved
        $rebalancingService = app(RebalancingService::class);
        $portfolioService = app(PortfolioManagementService::class);

        $this->assertInstanceOf(RebalancingService::class, $rebalancingService);
        $this->assertInstanceOf(PortfolioManagementService::class, $portfolioService);

        // Verify the portfolio was properly created
        $portfolio = $portfolioService->getPortfolio($this->portfolioId);
        $this->assertEquals($this->portfolioId, $portfolio['portfolio_id']);
        $this->assertNotEmpty($portfolio['asset_allocations']);
    }

    // Helper methods

    private function createTestPortfolio(): void
    {
        $strategy = [
            'riskProfile'        => 'moderate',
            'rebalanceThreshold' => 5.0,
            'targetReturn'       => 8.0,
        ];

        $this->portfolioId = $this->portfolioService->createPortfolio(
            'treasury-001',
            'Test Portfolio',
            $strategy
        );

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

    private function createWorkflowInstance(): PortfolioRebalancingWorkflow
    {
        $storedWorkflow = StoredWorkflow::create([
            'class' => PortfolioRebalancingWorkflow::class,
        ]);

        return new PortfolioRebalancingWorkflow($storedWorkflow);
    }

    private function callPrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
