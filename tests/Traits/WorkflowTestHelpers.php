<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Domain\AI\Services\WorkflowTestService;
use Exception;
use Mockery;
use Workflow\WorkflowStub;

/**
 * Helper trait for testing workflows without execution overhead
 * Maintains DDD patterns while improving test performance.
 */
trait WorkflowTestHelpers
{
    protected WorkflowTestService $workflowTestService;

    /**
     * Setup workflow test service.
     */
    protected function setUpWorkflowTestHelpers(): void
    {
        $this->workflowTestService = new WorkflowTestService();
    }

    /**
     * Mock a workflow execution without running the actual workflow engine
     * This maintains the contract while avoiding overhead.
     */
    protected function mockWorkflowExecution(string $workflowClass, array $expectedResult): object
    {
        $stub = Mockery::mock(WorkflowStub::class);

        $stub->shouldReceive('make')
            ->with($workflowClass)
            ->andReturn($stub);

        $stub->shouldReceive('start')
            ->andReturn($stub);

        $stub->shouldReceive('execute')
            ->andReturn($expectedResult);

        $stub->shouldReceive('output')
            ->andReturn($expectedResult);

        return $stub;
    }

    /**
     * Fast-track compliance workflow testing.
     */
    protected function runComplianceCheck(
        string $type,
        array $parameters,
        ?string $conversationId = null
    ): array {
        $conversationId = $conversationId ?? 'conv_test_' . uniqid();

        return $this->workflowTestService->simulateComplianceWorkflow(
            $conversationId,
            'test_user',
            $type,
            $parameters
        );
    }

    /**
     * Fast-track saga compensation testing.
     */
    protected function runSagaCompensation(array $completedSteps, Exception $failure): bool
    {
        return $this->workflowTestService->simulateSagaCompensation(
            'conv_saga_' . uniqid(),
            $completedSteps,
            $failure
        );
    }

    /**
     * Fast-track human approval testing.
     */
    protected function runHumanApproval(
        string $operation,
        array $data,
        bool $approved = true
    ): array {
        return $this->workflowTestService->simulateHumanApproval(
            'conv_approval_' . uniqid(),
            $operation,
            $data,
            $approved
        );
    }

    /**
     * Fast-track multi-agent coordination testing.
     */
    protected function runMultiAgentCoordination(
        string $taskType,
        array $agents
    ): array {
        return $this->workflowTestService->simulateMultiAgentCoordination(
            'task_' . uniqid(),
            $taskType,
            $agents
        );
    }

    /**
     * Assert workflow event was dispatched with specific criteria.
     */
    protected function assertWorkflowEventDispatched(string $eventClass, array $criteria = []): void
    {
        \Illuminate\Support\Facades\Event::assertDispatched($eventClass, function ($event) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (! property_exists($event, $key) || $event->$key !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Create a mock workflow stub for isolated testing.
     */
    protected function createWorkflowStub(string $workflowClass): object
    {
        return new class ($workflowClass) {
            private array $steps = [];

            private array $result = [];

            public function __construct(private string $workflowClass)
            {
            }

            public function addStep(string $step, $result): self
            {
                $this->steps[] = $step;
                $this->result[$step] = $result;

                return $this;
            }

            public function execute(array $params): array
            {
                // Simulate execution without actual workflow engine
                return [
                    'success'        => true,
                    'workflow'       => $this->workflowClass,
                    'steps_executed' => $this->steps,
                    'results'        => $this->result,
                ];
            }
        };
    }
}
