<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Domain\AI\Activities\IntentRecognitionActivity;
use App\Domain\AI\Activities\ToolSelectionActivity;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\IntentRecognizedEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\Workflows\Children\FraudDetectionWorkflow;
use App\Domain\AI\Workflows\ComplianceWorkflow;
use App\Domain\AI\Workflows\CustomerServiceWorkflow;
use App\Domain\AI\Workflows\RiskAssessmentSaga;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;
use Workflow\WorkflowStub;

class AgentWorkflowTest extends TestCase
{
    // Note: RefreshDatabase not needed - testing workflows with stubs

        #[\PHPUnit\Framework\Attributes\Test]
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
        $result = $workflow->execute($params);

        // Assert
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('tools_used', $result);
        $this->assertContains('CheckBalanceTool', $result['tools_used']);
        $this->assertGreaterThan(0.9, $result['confidence']);

        // Verify events
        Event::assertDispatched(IntentRecognizedEvent::class);
        Event::assertDispatched(ToolExecutedEvent::class);
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }

        #[\PHPUnit\Framework\Attributes\Test]
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
    public function risk_assessment_saga_evaluates_multiple_risk_factors(): void
    {
        // Arrange
        Event::fake();
        $saga = new RiskAssessmentSaga();
        $params = [
            'user_id'     => 1,
            'transaction' => [
                'amount'      => 10000,
                'type'        => 'transfer',
                'destination' => 'external_account',
            ],
            'assessment_type' => 'transaction_risk',
        ];

        // Act
        $result = $saga->execute($params);

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
        $this->mockActivity(ToolSelectionActivity::class)
            ->shouldReceive('select')
            ->andThrow(new \RuntimeException('Service unavailable'));

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
    public function multi_agent_coordination_delegates_tasks(): void
    {
        // Arrange
        Event::fake();
        $params = [
            'task'    => 'Complete loan application with risk assessment',
            'user_id' => 1,
            'agents'  => ['loan_advisor', 'risk_assessor', 'compliance'],
        ];

        // Act
        $coordinator = new \App\Domain\AI\Services\MultiAgentCoordinator();
        $result = $coordinator->coordinate($params['task'], $params['agents']);

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
    public function human_in_the_loop_waits_for_approval(): void
    {
        // Arrange
        Event::fake();
        $workflow = WorkflowStub::make(\App\Domain\AI\Workflows\HumanApprovalWorkflow::class);
        $params = [
            'operation'         => 'high_value_transfer',
            'amount'            => 100000,
            'requires_approval' => true,
        ];

        // Simulate human approval signal
        $this->mockSignal(\App\Domain\AI\Signals\HumanApprovalSignal::class)
            ->shouldReceive('wait')
            ->andReturn(true); // Approved

        // Act
        $result = $workflow->execute($params);

        // Assert
        $this->assertArrayHasKey('approved', $result);
        $this->assertTrue($result['approved']);
        $this->assertArrayHasKey('approval_id', $result);
        $this->assertArrayHasKey('approved_by', $result);

        // Verify approval event
        Event::assertDispatched(\App\Domain\AI\Events\HumanApprovalReceivedEvent::class);
    }

    private function mockActivity(string $activityClass): Mockery\MockInterface
    {
        $mock = Mockery::mock($activityClass);
        $this->app->instance($activityClass, $mock);

        return $mock;
    }

    private function mockSignal(string $signalClass): Mockery\MockInterface
    {
        $mock = Mockery::mock($signalClass);
        $this->app->instance($signalClass, $mock);

        return $mock;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
