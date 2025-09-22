<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Activities;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Workflows\Activities\ValidatePaymentActivity;
use Tests\TestCase;

class ValidatePaymentActivityTest extends TestCase
{
    private ValidatePaymentActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        /** @phpstan-ignore-next-line */
        $this->activity = new ValidatePaymentActivity();
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

        // Act
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

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

        // Act
        $result = $this->activity->execute($request);

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

        // Act
        $result = $this->activity->execute($request);

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
        $result = $this->activity->execute($request);

        // Assert
        $this->assertFalse($result->isValid);
        $this->assertContains('Split amounts exceed total payment', $result->errors);
    }
}
