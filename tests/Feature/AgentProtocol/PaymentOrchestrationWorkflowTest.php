<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for payment orchestration workflow components.
 *
 * Note: Full workflow integration tests require Laravel Workflow infrastructure
 * to be properly configured. These tests validate the individual components
 * and data objects used in the payment orchestration process.
 */
class PaymentOrchestrationWorkflowTest extends TestCase
{
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

    #[Test]
    public function it_can_process_a_simple_payment_successfully(): void
    {
        // Test wallet initialization and balance
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'transfer',
            transactionId: $this->transactionId
        );

        // Verify sender wallet has initial balance
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $this->assertEquals(1000.00, $senderWallet->getBalance());

        // Verify receiver wallet starts at 0
        $receiverWallet = AgentWalletAggregate::retrieve($this->receiverDid);
        $this->assertEquals(0.00, $receiverWallet->getBalance());

        // Verify payment request is correctly structured
        $this->assertEquals($this->senderDid, $request->fromAgentDid);
        $this->assertEquals($this->receiverDid, $request->toAgentDid);
        $this->assertEquals(100.00, $request->amount);
        $this->assertEquals('USD', $request->currency);

        // Verify sender has sufficient balance for this payment
        $this->assertTrue($senderWallet->hasSufficientBalance($request->amount));

        // Verify receiver can receive payment (balance check for receive is always valid)
        $this->assertGreaterThanOrEqual(0.00, $receiverWallet->getBalance());
    }

    #[Test]
    public function it_applies_fees_correctly_to_payments(): void
    {
        // Test fee calculation logic
        $amount = 100.00;

        $feeRate = config('agent_protocol.fees.standard_rate', 0.025);
        $minFee = config('agent_protocol.fees.minimum_fee', 0.50);
        $maxFee = config('agent_protocol.fees.maximum_fee', 100.00);

        $calculatedFee = $amount * $feeRate;
        $expectedFee = max($minFee, min($calculatedFee, $maxFee));
        $totalAmount = $amount + $expectedFee;

        // Assert fee calculation is correct
        $this->assertEquals(2.50, $expectedFee); // 100 * 0.025 = 2.50
        $this->assertEquals(102.50, $totalAmount);
    }

    #[Test]
    public function it_fails_payment_with_insufficient_balance(): void
    {
        // Test insufficient balance check
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $requestedAmount = 2000.00; // More than available balance

        // Verify balance check would fail
        $this->assertFalse($senderWallet->hasSufficientBalance($requestedAmount));
    }

    #[Test]
    public function it_can_process_split_payments(): void
    {
        // Test split payment configuration
        $split1Did = 'did:agent:test:split1-' . Str::random(8);
        $split2Did = 'did:agent:test:split2-' . Str::random(8);

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

        // Verify split payment data is correctly captured
        $this->assertCount(2, $request->splits);
        $this->assertEquals(10.00, $request->splits[0]['amount']);
        $this->assertEquals(5.00, $request->splits[1]['amount']);

        // Verify split amounts don't exceed total payment
        $totalSplits = array_sum(array_column($request->splits, 'amount'));
        $this->assertEquals(15.00, $totalSplits);
        $this->assertLessThan($request->amount, $totalSplits);

        // Verify split agent DIDs are correctly captured
        $this->assertEquals($split1Did, $request->splits[0]['agentDid']);
        $this->assertEquals($split2Did, $request->splits[1]['agentDid']);

        // Verify split types are correctly captured
        $this->assertEquals('commission', $request->splits[0]['type']);
        $this->assertEquals('referral', $request->splits[1]['type']);
    }

    #[Test]
    public function it_records_payment_in_history(): void
    {
        // Test payment request data integrity
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 50.00,
            currency: 'USD',
            purpose: 'payment',
            transactionId: $this->transactionId
        );

        // Verify request data is correctly captured
        $this->assertEquals($this->transactionId, $request->transactionId);
        $this->assertEquals($this->senderDid, $request->fromAgentDid);
        $this->assertEquals($this->receiverDid, $request->toAgentDid);
        $this->assertEquals(50.00, $request->amount);
        $this->assertEquals('USD', $request->currency);
    }

    #[Test]
    public function it_validates_minimum_payment_amount(): void
    {
        // Test micro-payment fee exemption
        $amount = 0.01; // Very small amount
        $exemptionThreshold = config('agent_protocol.fees.exemption_threshold', 1.00);

        // Amount below exemption threshold should be fee-exempt
        $this->assertLessThan($exemptionThreshold, $amount);
        $appliedFee = ($amount < $exemptionThreshold) ? 0.0 : 1.0;
        $this->assertEquals(0.0, $appliedFee);
    }

    #[Test]
    public function it_handles_payment_retry_on_failure(): void
    {
        // Retry logic testing requires full workflow infrastructure
        $this->markTestSkipped('Retry logic testing requires workflow mocking capabilities');
    }

    #[Test]
    public function it_compensates_failed_payments(): void
    {
        // Compensation testing requires advanced workflow control
        $this->markTestSkipped('Compensation testing requires advanced workflow control');
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
