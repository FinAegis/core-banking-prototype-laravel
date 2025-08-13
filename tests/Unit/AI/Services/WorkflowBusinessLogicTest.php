<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Services;

use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\CompensationExecutedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use App\Domain\AI\Services\WorkflowTestService;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fast unit tests for workflow business logic
 * Tests DDD, Event Sourcing, and Saga patterns without workflow engine overhead.
 */
#[Group('fast')]
#[Group('unit')]
class WorkflowBusinessLogicTest extends TestCase
{
    private WorkflowTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkflowTestService();
    }

    #[Test]
    public function it_performs_kyc_verification_with_event_sourcing(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_kyc_001';
        $userId = 'user_123';
        $parameters = [
            'documents' => [
                'id_document'      => 'passport.pdf',
                'proof_of_address' => 'utility.pdf',
            ],
            'level' => 'standard',
        ];

        // Act
        $result = $this->service->simulateComplianceWorkflow(
            $conversationId,
            $userId,
            'kyc',
            $parameters
        );

        // Assert - Business logic
        $this->assertTrue($result['success']);
        $this->assertEquals('kyc', $result['compliance_type']);
        $this->assertTrue($result['result']['verified']);
        $this->assertEquals(100, $result['result']['score']);
        $this->assertGreaterThan(0.7, $result['metadata']['confidence']);

        // Assert - Event sourcing
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'Compliance check completed');
        });
    }

    #[Test]
    public function it_performs_aml_screening_with_risk_assessment(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_aml_001';
        $userId = 'user_456';
        $parameters = [
            'amount'         => 5000,
            'transaction_id' => 'txn_123',
            'counterparty'   => 'verified_party',
        ];

        // Act
        $result = $this->service->simulateComplianceWorkflow(
            $conversationId,
            $userId,
            'aml',
            $parameters
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']['cleared']);
        $this->assertLessThan(30, $result['result']['risk_score']);
        $this->assertFalse($result['result']['sanctions_match']);

        // Event sourcing verification
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    #[Test]
    public function it_triggers_human_intervention_for_low_confidence(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_kyc_002';
        $userId = 'user_789';
        $parameters = [
            'documents' => [], // No documents = low score = low confidence
            'level'     => 'enhanced',
        ];

        // Act
        $result = $this->service->simulateComplianceWorkflow(
            $conversationId,
            $userId,
            'kyc',
            $parameters
        );

        // Assert
        $this->assertFalse($result['result']['verified']);
        $this->assertEquals(50, $result['result']['score']);
        $this->assertLessThan(0.7, $result['metadata']['confidence']);

        // Should trigger human intervention due to low confidence
        Event::assertDispatched(HumanInterventionRequestedEvent::class);
    }

    #[Test]
    public function it_executes_saga_compensation_on_failure(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_saga_001';
        $completedSteps = [
            ['type' => 'kyc_verification', 'data' => ['user' => '123']],
            ['type' => 'aml_screening', 'data' => ['transaction' => 'txn_456']],
            ['type' => 'risk_assessment', 'data' => ['score' => 85]],
        ];
        $failure = new \RuntimeException('External service unavailable');

        // Act
        $compensated = $this->service->simulateSagaCompensation(
            $conversationId,
            $completedSteps,
            $failure
        );

        // Assert
        $this->assertTrue($compensated);

        // Verify compensation events in reverse order
        Event::assertDispatched(CompensationExecutedEvent::class, function ($event) {
            return $event->workflowId === 'risk_assessment';
        });
        Event::assertDispatched(CompensationExecutedEvent::class, function ($event) {
            return $event->workflowId === 'aml_screening';
        });
        Event::assertDispatched(CompensationExecutedEvent::class, function ($event) {
            return $event->workflowId === 'kyc_verification';
        });
    }

    #[Test]
    public function it_handles_human_approval_without_timeout_simulation(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_approval_001';
        $operation = 'high_value_transfer';
        $data = ['amount' => 100000, 'currency' => 'USD'];

        // Act - Test approval
        $result = $this->service->simulateHumanApproval(
            $conversationId,
            $operation,
            $data,
            true
        );

        // Assert
        $this->assertTrue($result['approved']);
        $this->assertEquals('test_approver', $result['approver_id']);
        $this->assertFalse($result['timed_out']);

        // Verify events
        Event::assertDispatched(HumanInterventionRequestedEvent::class);
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return $event->decision === 'Approved';
        });
    }

    #[Test]
    public function it_handles_human_rejection_without_timeout_simulation(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_approval_002';
        $operation = 'suspicious_transaction';
        $data = ['amount' => 50000, 'risk_score' => 0.85];

        // Act - Test rejection
        $result = $this->service->simulateHumanApproval(
            $conversationId,
            $operation,
            $data,
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

    #[Test]
    public function it_coordinates_multi_agent_tasks_using_bounded_contexts(): void
    {
        // Arrange
        Event::fake();
        $taskId = 'task_loan_001';
        $taskType = 'loan_application';
        $agents = ['loan_advisor', 'risk_assessor', 'compliance'];

        // Act
        $result = $this->service->simulateMultiAgentCoordination(
            $taskId,
            $taskType,
            $agents
        );

        // Assert
        $this->assertEquals('loan_advisor', $result['lead_agent']);
        $this->assertCount(3, $result['subtasks']);
        $this->assertCount(3, $result['results']);

        // Each agent should have completed their task
        foreach ($agents as $agent) {
            $this->assertEquals('completed', $result['results'][$agent]['status']);
            $this->assertEquals(0.85, $result['results'][$agent]['confidence']);
        }

        // Verify coordination event
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'Multi-agent coordination completed');
        });
    }

    #[Test]
    public function it_monitors_transactions_with_event_sourcing(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_monitor_001';
        $userId = 'user_monitor';
        $parameters = [
            'period'    => 'last_7_days',
            'threshold' => 5000,
        ];

        // Act
        $result = $this->service->simulateComplianceWorkflow(
            $conversationId,
            $userId,
            'transaction_monitoring',
            $parameters
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']['monitored']);
        $this->assertEquals('last_7_days', $result['result']['period']);
        $this->assertFalse($result['result']['unusual_activity']['detected']);
        $this->assertGreaterThan(0.8, $result['metadata']['confidence']);

        Event::assertDispatched(AIDecisionMadeEvent::class);
    }
}
