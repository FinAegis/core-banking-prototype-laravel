<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Activities;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Workflows\Activities\ValidatePaymentActivity;
use App\Domain\AgentProtocol\Workflows\PaymentOrchestrationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

class ValidatePaymentActivityTest extends TestCase
{
    use RefreshDatabase;

    private ?ValidatePaymentActivity $activity = null;

    private function createActivity(): ValidatePaymentActivity
    {
        if ($this->activity !== null) {
            return $this->activity;
        }

        // Create a workflow stub to get proper StoredWorkflow context
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        /** @var StoredWorkflow $storedWorkflow */
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        $this->activity = new ValidatePaymentActivity(
            0,
            now()->toDateTimeString(),
            $storedWorkflow,
            $request
        );

        return $this->activity;
    }

    /** @test */
    public function it_validates_valid_payment_request()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act - use basicValidationOnly to skip aggregate checks in unit tests
        $result = $this->createActivity()->execute($request, ['basicValidationOnly' => true]);

        // Assert
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertNotNull($result->validatedAt);
    }

    /** @test */
    public function it_rejects_negative_amount()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: -10.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Amount must be positive', $result->errors);
    }

    /** @test */
    public function it_rejects_zero_amount()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 0.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Amount must be positive', $result->errors);
    }

    /** @test */
    public function it_rejects_invalid_did_format()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'invalid-did',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Invalid sender DID format', $result->errors);
    }

    /** @test */
    public function it_rejects_same_sender_and_receiver()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:same',
            toAgentDid: 'did:agent:test:same',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Sender and receiver cannot be the same', $result->errors);
    }

    /** @test */
    public function it_validates_supported_currencies()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'XYZ', // Unsupported currency
            purpose: 'payment'
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Unsupported currency: XYZ', $result->errors);
    }

    /** @test */
    public function it_validates_escrow_conditions()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'escrow',
            escrowConditions: [
                'condition1' => false,
                'condition2' => false,
            ]
        );

        // Act - use basicValidationOnly to skip aggregate checks in unit tests
        $result = $this->createActivity()->execute($request, ['basicValidationOnly' => true]);

        // Assert
        $this->assertTrue($result->isValid);
        $this->assertEquals(['condition1', 'condition2'], $result->escrowRequirements);
    }

    /** @test */
    public function it_validates_split_payments()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment',
            splits: [
                ['agentDid' => 'did:agent:test:split1', 'amount' => 10.00],
                ['agentDid' => 'did:agent:test:split2', 'amount' => 5.00],
            ]
        );

        // Act - use basicValidationOnly to skip aggregate checks in unit tests
        $result = $this->createActivity()->execute($request, ['basicValidationOnly' => true]);

        // Assert
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    /** @test */
    public function it_rejects_splits_exceeding_total()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: 'did:agent:test:sender',
            toAgentDid: 'did:agent:test:receiver',
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment',
            splits: [
                ['agentDid' => 'did:agent:test:split1', 'amount' => 60.00],
                ['agentDid' => 'did:agent:test:split2', 'amount' => 50.00], // Total 110 > 100
            ]
        );

        // Act
        $result = $this->createActivity()->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Split amounts exceed total payment', $result->errors);
    }
}
