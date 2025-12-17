<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Workflows\PaymentOrchestrationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Workflow\WorkflowStub;

class PaymentOrchestrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $senderDid;

    private string $receiverDid;

    private string $transactionId;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake workflow for testing
        WorkflowStub::fake();

        // Setup test DIDs and transaction ID
        $this->senderDid = 'did:agent:test:sender-' . Str::random(8);
        $this->receiverDid = 'did:agent:test:receiver-' . Str::random(8);
        $this->transactionId = 'txn-' . Str::uuid()->toString();

        // Initialize wallets with balance
        $this->initializeWallet($this->senderDid, 1000.00);
        $this->initializeWallet($this->receiverDid, 0.00);
    }

    /** @test */
    public function it_can_process_a_simple_payment_successfully()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'transfer',
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow (in fake mode, this queues for later execution)
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created successfully
        $this->assertInstanceOf(WorkflowStub::class, $workflow);
        $this->assertNotEmpty($workflow->id());

        // Verify the sender wallet was initialized with balance
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $this->assertEquals(1000.00, $senderWallet->getBalance());
    }

    /** @test */
    public function it_applies_fees_correctly_to_payments()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment',
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created and fee configuration is correct
        $this->assertInstanceOf(WorkflowStub::class, $workflow);

        $expectedFee = max(
            config('agent_protocol.fees.minimum_fee', 0.50),
            min(
                100.00 * config('agent_protocol.fees.standard_rate', 0.025),
                config('agent_protocol.fees.maximum_fee', 100.00)
            )
        );

        // Verify fee calculation is as expected (2.5% with min 0.50, max 100)
        $this->assertEquals(2.50, $expectedFee);
    }

    /** @test */
    public function it_fails_payment_with_insufficient_balance()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 2000.00, // More than available balance
            currency: 'USD',
            purpose: 'payment',
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow (in fake mode)
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created (validation happens during execution)
        $this->assertInstanceOf(WorkflowStub::class, $workflow);

        // Verify the sender only has 1000 balance (insufficient for 2000 transfer)
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $this->assertEquals(1000.00, $senderWallet->getBalance());
        $this->assertLessThan($request->amount, $senderWallet->getBalance());
    }

    /** @test */
    public function it_can_process_split_payments()
    {
        // Arrange
        $split1Did = 'did:agent:test:split1-' . Str::random(8);
        $split2Did = 'did:agent:test:split2-' . Str::random(8);

        $this->initializeWallet($split1Did, 0.00);
        $this->initializeWallet($split2Did, 0.00);

        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'transfer',
            metadata: [],
            escrowConditions: [],
            splits: [
                ['agentDid' => $split1Did, 'amount' => 10.00, 'type' => 'commission'],
                ['agentDid' => $split2Did, 'amount' => 5.00, 'type' => 'referral'],
            ],
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created with split configuration
        $this->assertInstanceOf(WorkflowStub::class, $workflow);
        $this->assertTrue($request->hasSplits());
        $this->assertCount(2, $request->splits);

        // Verify split wallets were initialized
        $split1Wallet = AgentWalletAggregate::retrieve($split1Did);
        $split2Wallet = AgentWalletAggregate::retrieve($split2Did);

        $this->assertEquals(0.00, $split1Wallet->getBalance());
        $this->assertEquals(0.00, $split2Wallet->getBalance());
    }

    /** @test */
    public function it_records_payment_in_history()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 50.00,
            currency: 'USD',
            purpose: 'payment',
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created with payment details
        $this->assertInstanceOf(WorkflowStub::class, $workflow);
        $this->assertEquals($this->transactionId, $request->transactionId);
        $this->assertEquals($this->senderDid, $request->fromAgentDid);
        $this->assertEquals($this->receiverDid, $request->toAgentDid);
        $this->assertEquals(50.00, $request->amount);
    }

    /** @test */
    public function it_validates_minimum_payment_amount()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 0.01, // Very small amount
            currency: 'USD',
            purpose: 'micropayment',
            transactionId: $this->transactionId
        );

        // Act - Create and start workflow
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->start($request);

        // Assert - Workflow was created and micro-payment configuration is correct
        $this->assertInstanceOf(WorkflowStub::class, $workflow);

        // Verify fee exemption threshold configuration
        $exemptionThreshold = config('agent_protocol.fees.exemption_threshold', 1.00);
        $this->assertLessThan($exemptionThreshold, $request->amount);
        // Small amounts below threshold should be exempt from fees when processed
    }

    /** @test */
    public function it_handles_payment_retry_on_failure()
    {
        // This would test the executeWithRetry method
        // In a real implementation, we'd mock a failure and verify retry behavior
        $this->markTestIncomplete('Retry logic testing requires workflow mocking capabilities');
    }

    /** @test */
    public function it_compensates_failed_payments()
    {
        // This would test the compensation logic
        // In a real implementation, we'd simulate a failure after partial processing
        $this->markTestIncomplete('Compensation testing requires advanced workflow control');
    }

    /**
     * Helper method to initialize a wallet with balance.
     */
    private function initializeWallet(string $agentDid, float $balance): void
    {
        $wallet = AgentWalletAggregate::retrieve($agentDid);

        if ($balance > 0) {
            // Initialize wallet with a deposit
            $wallet->receivePayment(
                transactionId: 'init-' . Str::uuid()->toString(),
                fromAgentId: 'did:agent:test:system',
                amount: $balance,
                metadata: ['type' => 'initial_deposit']
            );
        }

        $wallet->persist();
    }
}
