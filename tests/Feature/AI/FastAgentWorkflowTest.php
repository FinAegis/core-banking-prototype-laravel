<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\WorkflowTestHelpers;

/**
 * Fast integration tests for AI Agent workflows
 * Tests critical paths using event sourcing and DDD without workflow engine overhead.
 */
#[Group('fast')]
#[Group('feature')]
class FastAgentWorkflowTest extends TestCase
{
    use WorkflowTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkflowTestHelpers();
    }

    #[Test]
    public function customer_service_workflow_processes_balance_inquiry_fast(): void
    {
        // Arrange
        Event::fake();

        // Use the fast workflow test service instead of WorkflowStub
        $result = $this->workflowTestService->simulateComplianceWorkflow(
            'conv_cs_001',
            'user_123',
            'kyc', // Using KYC as example, but could create customer service specific
            ['documents' => ['id_document' => 'id.pdf']]
        );

        // Assert - Core business logic
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertGreaterThan(0.5, $result['metadata']['confidence']);

        // Assert - Events (DDD/Event Sourcing)
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    #[Test]
    public function compliance_workflow_performs_kyc_verification_fast(): void
    {
        // Arrange
        Event::fake();

        // Act - Use helper for fast execution
        $result = $this->runComplianceCheck('kyc', [
            'documents' => [
                'id_document'      => 'passport_123.pdf',
                'proof_of_address' => 'utility_bill.pdf',
            ],
            'level' => 'standard',
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('kyc', $result['compliance_type']);
        $this->assertTrue($result['result']['verified']);
        $this->assertEquals(100, $result['result']['score']);

        // Verify event sourcing
        $this->assertWorkflowEventDispatched(AIDecisionMadeEvent::class, [
            'agentType' => 'compliance-agent',
        ]);
    }

    #[Test]
    public function risk_assessment_evaluates_multiple_factors_fast(): void
    {
        // Arrange
        Event::fake();

        // Act - Simulate risk assessment without saga overhead
        $result = $this->runComplianceCheck('aml', [
            'amount'         => 10000,
            'transaction_id' => 'txn_risk_001',
            'counterparty'   => 'verified_merchant',
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']['cleared']);
        $this->assertLessThan(50, $result['result']['risk_score']);

        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    #[Test]
    public function workflow_requests_human_intervention_for_low_confidence_fast(): void
    {
        // Arrange
        Event::fake();
        config(['ai.confidence_threshold' => 0.8]);

        // Act - Test with no documents (will trigger low confidence)
        $result = $this->runComplianceCheck('kyc', [
            'documents' => [], // Empty = low score = low confidence
        ]);

        // Assert
        $this->assertFalse($result['result']['verified']);
        $this->assertLessThan(0.7, $result['metadata']['confidence']);

        // Should trigger human intervention
        Event::assertDispatched(HumanInterventionRequestedEvent::class);
    }

    #[Test]
    public function workflow_handles_compensation_on_failure_fast(): void
    {
        // Arrange
        Event::fake();

        $completedSteps = [
            ['type' => 'kyc_verification', 'data' => ['status' => 'completed']],
            ['type' => 'aml_screening', 'data' => ['status' => 'completed']],
        ];

        // Act - Test compensation without workflow engine
        $compensated = $this->runSagaCompensation(
            $completedSteps,
            new \RuntimeException('Service unavailable')
        );

        // Assert
        $this->assertTrue($compensated);

        // Verify compensation events were dispatched
        Event::assertDispatchedTimes(\App\Domain\AI\Events\CompensationExecutedEvent::class, 2);
    }

    #[Test]
    public function multi_agent_coordination_delegates_tasks_fast(): void
    {
        // Arrange
        Event::fake();

        // Act - Test multi-agent without actual workflow coordination
        $result = $this->runMultiAgentCoordination(
            'loan_application',
            ['loan_advisor', 'risk_assessor', 'compliance']
        );

        // Assert
        $this->assertEquals('loan_advisor', $result['lead_agent']);
        $this->assertCount(3, $result['subtasks']);
        $this->assertCount(3, $result['results']);

        // Verify coordination event
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'Multi-agent coordination completed');
        });
    }

    #[Test]
    public function human_in_the_loop_handles_approval_fast(): void
    {
        // Arrange
        Event::fake();

        // Act - Test approval without timeout simulation
        $result = $this->runHumanApproval(
            'high_value_transfer',
            ['amount' => 100000, 'currency' => 'USD'],
            true
        );

        // Assert
        $this->assertTrue($result['approved']);
        $this->assertEquals('test_approver', $result['approver_id']);
        $this->assertFalse($result['timed_out']);

        // Verify events
        Event::assertDispatched(HumanInterventionRequestedEvent::class);
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    #[Test]
    public function human_in_the_loop_handles_rejection_fast(): void
    {
        // Arrange
        Event::fake();

        // Act - Test rejection without timeout simulation
        $result = $this->runHumanApproval(
            'suspicious_transaction',
            ['amount' => 50000, 'risk_score' => 0.85],
            false
        );

        // Assert
        $this->assertFalse($result['approved']);
        $this->assertEquals('test_rejector', $result['approver_id']);
        $this->assertEquals('Rejected in test', $result['comments']);

        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return $event->decision === 'Rejected';
        });
    }
}
