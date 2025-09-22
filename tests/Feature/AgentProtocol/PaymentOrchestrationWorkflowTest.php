<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Aggregates\PaymentHistoryAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use App\Domain\AgentProtocol\Workflows\PaymentOrchestrationWorkflow;
use DomainException;
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

        // Act
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $result = $workflow->execute($request);

        // Assert
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertEquals('completed', $result->status);
        $this->assertEquals($this->transactionId, $result->transactionId);
        $this->assertEquals(100.00, $result->amount);
        $this->assertGreaterThan(0, $result->fees); // Should have fees applied

        // Verify wallet balances were updated
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $receiverWallet = AgentWalletAggregate::retrieve($this->receiverDid);

        // Sender should have less than 900 due to amount + fees
        $this->assertLessThan(900.00, $senderWallet->getBalance());
        // Receiver should have exactly 100
        $this->assertEquals(100.00, $receiverWallet->getBalance());
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

        // Act
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $result = $workflow->execute($request);

        // Assert
        $expectedFee = max(
            config('agent_protocol.fees.minimum_fee', 0.50),
            min(
                100.00 * config('agent_protocol.fees.standard_rate', 0.025),
                config('agent_protocol.fees.maximum_fee', 100.00)
            )
        );

        $this->assertEquals($expectedFee, $result->fees);
        $this->assertEquals(100.00 + $expectedFee, $result->totalAmount);
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

        // Act & Assert
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $workflow->execute($request);
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

        // Act
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $result = $workflow->execute($request);

        // Assert
        $this->assertEquals('completed', $result->status);

        // Verify split recipients received their amounts
        $split1Wallet = AgentWalletAggregate::retrieve($split1Did);
        $split2Wallet = AgentWalletAggregate::retrieve($split2Did);

        $this->assertEquals(10.00, $split1Wallet->getBalance());
        $this->assertEquals(5.00, $split2Wallet->getBalance());
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

        // Act
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $result = $workflow->execute($request);

        // Assert
        $history = PaymentHistoryAggregate::retrieve($this->transactionId);
        $this->assertEquals($this->transactionId, $history->getTransactionId());
        $this->assertEquals($this->senderDid, $history->getFromAgent());
        $this->assertEquals($this->receiverDid, $history->getToAgent());
        $this->assertEquals(50.00, $history->getAmount());
        $this->assertEquals('completed', $history->getStatus());
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

        // Act
        $workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
        $result = $workflow->execute($request);

        // Assert - Should be fee-exempt for micro-transactions
        $exemptionThreshold = config('agent_protocol.fees.exemption_threshold', 1.00);
        if ($request->amount < $exemptionThreshold) {
            $this->assertEquals(0, $result->fees);
        }
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
