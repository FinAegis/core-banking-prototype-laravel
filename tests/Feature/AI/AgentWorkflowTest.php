<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Activities\IntentRecognitionActivity;
use App\Domain\AI\Activities\ToolSelectionActivity;
use App\Domain\AI\ChildWorkflows\Children\FraudDetectionWorkflow;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\IntentRecognizedEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\Workflows\ComplianceWorkflow;
use App\Domain\AI\Workflows\CustomerServiceWorkflow;
use App\Domain\AI\Workflows\RiskAssessmentSaga;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Workflow\WorkflowStub;

#[Group('slow')]
#[Group('workflows')]
class AgentWorkflowTest extends TestCase
{
    // Note: RefreshDatabase not needed - testing workflows with stubs

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function customer_service_workflow_processes_balance_inquiry(): void
    {
        // Arrange
        Event::fake();
        $workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
        $params = [
            'conversation_id' => 'conv_test_001',
            'user_id'         => 1,
            'message'         => 'What is my account balance?',
            'context'         => ['account_type' => 'checking'],
        ];

        // Mock activities
        $this->mockActivity(IntentRecognitionActivity::class)
            ->shouldReceive('recognize')
            ->andReturn([
                'type'       => 'balance_inquiry',
                'confidence' => 0.95,
                'entities'   => ['account_type' => 'checking'],
            ]);

        $this->mockActivity(ToolSelectionActivity::class)
            ->shouldReceive('select')
            ->andReturn(['CheckBalanceTool']);

        // Act
        $workflow->start(
            $params['conversation_id'],
            $params['message'],
            (string) $params['user_id'],
            $params['context']
        );

        // Get the workflow result
        $result = $workflow->output();

        // Assert
        $this->assertNotNull($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('confidence', $result['metadata']);
        $this->assertArrayHasKey('tools_used', $result['metadata']);
        $this->assertGreaterThan(0.9, $result['metadata']['confidence']);

        // Verify events
        Event::assertDispatched(IntentRecognizedEvent::class);
        Event::assertDispatched(ToolExecutedEvent::class);
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function compliance_workflow_performs_kyc_verification(): void
    {
        // Arrange
        Event::fake();
        $workflow = WorkflowStub::make(ComplianceWorkflow::class);
        $params = [
            'user_id'   => 1,
            'documents' => [
                'id_document'      => 'passport_123.pdf',
                'proof_of_address' => 'utility_bill.pdf',
            ],
            'request_type' => 'account_opening',
        ];

        // Act
        $result = $workflow->execute($params);

        // Assert
        $this->assertArrayHasKey('kyc_status', $result);
        $this->assertArrayHasKey('aml_status', $result);
        $this->assertArrayHasKey('risk_rating', $result);
        $this->assertArrayHasKey('compliance_decision', $result);
        $this->assertContains($result['compliance_decision'], ['approved', 'manual_review', 'rejected']);

        // Verify compliance events
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'compliance');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function risk_assessment_saga_evaluates_multiple_risk_factors(): void
    {
        // Arrange
        Event::fake();
        $saga = new RiskAssessmentSaga();
        $params = [
            'conversation_id' => 'conv_risk_001',
            'user_id'         => '1',
            'transaction'     => [
                'amount'      => 10000,
                'type'        => 'transfer',
                'destination' => 'external_account',
            ],
            'assessment_type' => 'transaction_risk',
        ];

        // Act
        $generator = $saga->execute(
            $params['conversation_id'],
            $params['user_id'],
            $params['assessment_type'],
            $params
        );

        // Convert generator to array by iterating through it
        $result = iterator_to_array($generator, false);
        // Get the final return value (last yielded value)
        $result = end($result) ?: [];

        // Assert
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('risk_factors', $result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertIsFloat($result['risk_score']);
        $this->assertGreaterThanOrEqual(0, $result['risk_score']);
        $this->assertLessThanOrEqual(1, $result['risk_score']);

        // Verify saga steps executed
        $this->assertArrayHasKey('credit_risk', $result['risk_factors']);
        $this->assertArrayHasKey('behavioral_risk', $result['risk_factors']);
        $this->assertArrayHasKey('transaction_risk', $result['risk_factors']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function fraud_detection_workflow_identifies_suspicious_activity(): void
    {
        // Arrange
        Event::fake();
        $workflow = WorkflowStub::make(FraudDetectionWorkflow::class);
        $params = [
            'user_id'     => 1,
            'transaction' => [
                'amount'   => 50000,
                'merchant' => ['category' => 'gambling', 'country' => 'high_risk'],
                'time'     => '03:00:00',
                'location' => 'unusual',
            ],
        ];

        // Act
        $result = $workflow->execute($params);

        // Assert
        $this->assertArrayHasKey('fraud_score', $result);
        $this->assertArrayHasKey('is_fraudulent', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('risk_indicators', $result);

        if ($result['fraud_score'] > 0.7) {
            $this->assertTrue($result['requires_review']);
        }

        // Verify fraud detection events
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'fraud');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function workflow_requests_human_intervention_for_low_confidence(): void
    {
        // Arrange
        Event::fake();
        config(['ai.confidence_threshold' => 0.8]);

        $workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
        $params = [
            'conversation_id' => 'conv_test_002',
            'user_id'         => 1,
            'message'         => 'Can I get a loan for buying cryptocurrency?', // Ambiguous request
        ];

        // Mock low confidence intent recognition
        $this->mockActivity(IntentRecognitionActivity::class)
            ->shouldReceive('recognize')
            ->andReturn([
                'type'       => 'unclear',
                'confidence' => 0.45, // Below threshold
                'entities'   => [],
            ]);

        // Act
        $result = $workflow->execute($params);

        // Assert
        $this->assertArrayHasKey('requires_human', $result);
        $this->assertTrue($result['requires_human']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('confidence', strtolower($result['reason']));

        // Verify human intervention event
        Event::assertDispatched(\App\Domain\AI\Events\HumanInterventionRequestedEvent::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function workflow_handles_compensation_on_failure(): void
    {
        // Arrange
        Event::fake();
        $workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
        $params = [
            'conversation_id' => 'conv_test_003',
            'user_id'         => 1,
            'message'         => 'Transfer $1000 to account XYZ',
        ];

        // Mock activity to throw exception
        /** @var Mockery\MockInterface $mock */
        $mock = $this->mockActivity(ToolSelectionActivity::class);
        /** @var Mockery\ExpectationInterface $expectation */
        $expectation = $mock->shouldReceive('select');
        $expectation->andThrow(new \RuntimeException('Service unavailable'));

        // Act & Assert
        try {
            $workflow->execute($params);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Service unavailable', $e->getMessage());
        }

        // Verify compensation was executed
        Event::assertDispatched(\App\Domain\AI\Events\CompensationExecutedEvent::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function multi_agent_coordination_delegates_tasks(): void
    {
        // Arrange
        Event::fake();
        $params = [
            'task'    => 'Complete loan application with risk assessment',
            'user_id' => 1,
            'agents'  => ['loan_advisor', 'risk_assessor', 'compliance'],
        ];

        // Create a mock user
        $user = new \App\Models\User();
        $user->id = $params['user_id'];

        // Act
        $coordinator = new \App\Domain\AI\Services\MultiAgentCoordinationService();
        $result = $coordinator->coordinateTask(
            'task_' . uniqid(), // taskId
            'loan_application', // taskType
            ['task' => $params['task'], 'agents' => $params['agents']], // parameters
            $user // user
        );

        // Assert
        $this->assertArrayHasKey('lead_agent', $result);
        $this->assertArrayHasKey('subtasks', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(3, $result['subtasks']); // One for each agent

        // Verify coordination events
        Event::assertDispatched(AIDecisionMadeEvent::class, function ($event) {
            return str_contains($event->decision, 'coordination');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function human_in_the_loop_waits_for_approval(): void
    {
        // Arrange
        Event::fake();

        // Create workflow stub
        $workflow = WorkflowStub::make(\App\Domain\AI\Workflows\HumanApprovalWorkflow::class);

        // Start the workflow execution (non-blocking)
        $workflow->start(
            'conversation_123',
            'high_value_transfer',
            [
                'amount'   => 100000,
                'currency' => 'USD',
            ],
            10 // 10 second timeout for test
        );

        // Simulate human approval by calling the signal method
        $workflow->approve('test_approver', 'Approved for testing');

        // Wait for workflow to complete
        $result = $workflow->output();

        // Assert
        $this->assertArrayHasKey('approved', $result);
        $this->assertTrue($result['approved']);
        $this->assertArrayHasKey('approval_id', $result);
        $this->assertEquals('test_approver', $result['approver_id']);
        $this->assertEquals('Approved for testing', $result['comments']);
        $this->assertFalse($result['timed_out']);

        // Verify events
        Event::assertDispatched(\App\Domain\AI\Events\HumanInterventionRequestedEvent::class);
        Event::assertDispatched(\App\Domain\AI\Events\HumanApprovalReceivedEvent::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function human_in_the_loop_handles_rejection(): void
    {
        // Arrange
        Event::fake();

        // Create workflow stub
        $workflow = WorkflowStub::make(\App\Domain\AI\Workflows\HumanApprovalWorkflow::class);

        // Start the workflow execution
        $workflow->start(
            'conversation_456',
            'suspicious_transaction',
            [
                'amount'     => 50000,
                'risk_score' => 0.85,
            ],
            10 // 10 second timeout for test
        );

        // Simulate human rejection by calling the reject signal method
        $workflow->reject('compliance_officer', 'Transaction flagged as suspicious');

        // Wait for workflow to complete
        $result = $workflow->output();

        // Assert
        $this->assertArrayHasKey('approved', $result);
        $this->assertFalse($result['approved']);
        $this->assertEquals('compliance_officer', $result['approver_id']);
        $this->assertEquals('Transaction flagged as suspicious', $result['comments']);
        $this->assertFalse($result['timed_out']);

        // Verify events
        Event::assertDispatched(\App\Domain\AI\Events\HumanInterventionRequestedEvent::class);
        Event::assertDispatched(\App\Domain\AI\Events\HumanApprovalReceivedEvent::class, function ($event) {
            return $event->approved === false;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[Group('slow')]
    public function human_in_the_loop_handles_timeout(): void
    {
        // Arrange
        Event::fake();

        // Create workflow stub
        $workflow = WorkflowStub::make(\App\Domain\AI\Workflows\HumanApprovalWorkflow::class);

        // Start the workflow execution with very short timeout
        $workflow->start(
            'conversation_789',
            'pending_operation',
            [
                'amount' => 25000,
            ],
            0.1 // 0.1 second timeout to ensure it times out
        );

        // Don't send any signal, let it timeout

        // Wait for workflow to complete
        $result = $workflow->output();

        // Assert
        $this->assertArrayHasKey('approved', $result);
        $this->assertFalse($result['approved']);
        $this->assertEquals('timeout', $result['approver_id']);
        $this->assertEquals('Approval request timed out', $result['comments']);
        $this->assertTrue($result['timed_out']);

        // Verify events
        Event::assertDispatched(\App\Domain\AI\Events\HumanInterventionRequestedEvent::class);
        Event::assertDispatched(\App\Domain\AI\Events\HumanApprovalReceivedEvent::class, function ($event) {
            return $event->approved === false && $event->approverId === 'timeout';
        });
    }

    /**
     * @param class-string $activityClass
     * @return Mockery\MockInterface&Mockery\LegacyMockInterface
     */
    private function mockActivity(string $activityClass): Mockery\MockInterface
    {
        $mock = Mockery::mock($activityClass);
        $this->app->instance($activityClass, $mock);

        return $mock;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
