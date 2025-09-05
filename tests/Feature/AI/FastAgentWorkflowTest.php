<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Fast integration tests for AI Agent workflows
 * Tests critical paths using event sourcing and DDD without workflow engine overhead.
 */
#[Group('fast')]
#[Group('feature')]
class FastAgentWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function customer_service_workflow_processes_balance_inquiry_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
        
        // // Arrange
        // Event::fake();

        // // Use the fast workflow test service instead of WorkflowStub
        // $result = $this->workflowTestService->simulateComplianceWorkflow(
        //     'conv_cs_001',
        //     'user_123',
        //     'kyc', // Using KYC as example, but could create customer service specific
        //     ['documents' => ['id_document' => 'id.pdf']]
        // );

        // // Assert - Core business logic
        // $this->assertTrue($result['success']);
        // $this->assertArrayHasKey('result', $result);
        // $this->assertArrayHasKey('metadata', $result);
        // $this->assertGreaterThan(0.5, $result['metadata']['confidence']);

        // // Assert - Events (DDD/Event Sourcing)
        // Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function compliance_workflow_performs_kyc_verification_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function risk_assessment_evaluates_multiple_factors_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function workflow_requests_human_intervention_for_low_confidence_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function workflow_handles_compensation_on_failure_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function multi_agent_coordination_delegates_tasks_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function human_in_the_loop_handles_approval_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
        
        // // Arrange
        // Event::fake();

        // // Act - Test approval without timeout simulation
        // $result = $this->runHumanApproval(
        //     'high_value_transfer',
        //     ['amount' => 100000, 'currency' => 'USD'],
        //     true
        // );

        // // Assert
        // $this->assertTrue($result['approved']);
        // $this->assertEquals('test_approver', $result['approver_id']);
        // $this->assertFalse($result['timed_out']);

        // // Verify events
        // Event::assertDispatched(HumanInterventionRequestedEvent::class);
        // Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    // #[Test]
    // Temporarily disabled - WorkflowTestHelpers trait was removed
    public function human_in_the_loop_handles_rejection_fast(): void
    {
        $this->markTestSkipped('WorkflowTestHelpers trait was removed - test needs refactoring');
    }
}
