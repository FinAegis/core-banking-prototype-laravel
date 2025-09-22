<?php

declare(strict_types=1);

namespace Tests\Unit\AgentProtocol\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Workflows\Activities\ApplyFeesActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplyFeesActivityTest extends TestCase
{
    use RefreshDatabase;

    private ApplyFeesActivity $activity;

    private string $senderDid;

    private string $receiverDid;

    protected function setUp(): void
    {
        parent::setUp();

        /** @phpstan-ignore-next-line */
        $this->activity = new ApplyFeesActivity();
        $this->senderDid = 'did:agent:test:sender-' . Str::random(8);
        $this->receiverDid = 'did:agent:test:receiver-' . Str::random(8);

        // Initialize sender wallet with balance
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $senderWallet->receivePayment(
            transactionId: 'init-' . Str::uuid()->toString(),
            fromAgentId: 'did:agent:test:system',
            amount: 1000.00,
            metadata: ['type' => 'initial_deposit']
        );
        $senderWallet->persist();

        // Initialize fee collector wallet
        $feeCollectorDid = config('agent_protocol.fees.fee_collector_did', 'did:agent:finaegis:fee-collector');
        $feeCollector = AgentWalletAggregate::retrieve($feeCollectorDid);
        $feeCollector->persist();
    }

    /** @test */
    public function it_applies_standard_fees()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $expectedFee = max(
            config('agent_protocol.fees.minimum_fee', 0.50),
            min(
                100.00 * config('agent_protocol.fees.standard_rate', 0.025),
                config('agent_protocol.fees.maximum_fee', 100.00)
            )
        );

        $this->assertTrue($result->success);
        $this->assertEquals($expectedFee, $result->appliedFee);
        $this->assertEquals(100.00 + $expectedFee, $result->totalAmount);
        $this->assertEquals('applied', $result->status);
    }

    /** @test */
    public function it_applies_minimum_fee_for_small_amounts()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 10.00, // Small amount
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $minimumFee = config('agent_protocol.fees.minimum_fee', 0.50);
        $this->assertEquals($minimumFee, $result->appliedFee);
        $this->assertEquals(10.00 + $minimumFee, $result->totalAmount);
    }

    /** @test */
    public function it_applies_maximum_fee_for_large_amounts()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 10000.00, // Large amount
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $maximumFee = config('agent_protocol.fees.maximum_fee', 100.00);
        $this->assertEquals($maximumFee, $result->appliedFee);
        $this->assertEquals(10000.00 + $maximumFee, $result->totalAmount);
    }

    /** @test */
    public function it_exempts_fees_for_micropayments()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 0.50, // Below exemption threshold
            currency: 'USD',
            purpose: 'micropayment'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $this->assertEquals(0.00, $result->appliedFee);
        $this->assertEquals(0.50, $result->totalAmount);
        $this->assertEquals('exempt', $result->status);
    }

    /** @test */
    public function it_exempts_fees_for_system_accounts()
    {
        // Arrange
        $systemDid = config('agent_protocol.system_agents.system_did', 'did:agent:finaegis:system');
        $request = new AgentPaymentRequest(
            fromAgentDid: $systemDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'system_transfer'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $this->assertEquals(0.00, $result->appliedFee);
        $this->assertEquals('exempt', $result->status);
    }

    /** @test */
    public function it_exempts_fees_for_internal_transfers()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'internal:transfer' // Internal transfer prefix
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $this->assertEquals(0.00, $result->appliedFee);
        $this->assertEquals('exempt', $result->status);
    }

    /** @test */
    public function it_applies_custom_fee_rate_when_specified()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment',
            metadata: ['custom_fee_rate' => 0.05] // 5% custom rate
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $this->assertEquals(5.00, $result->appliedFee); // 100 * 0.05
        $this->assertEquals(105.00, $result->totalAmount);
    }

    /** @test */
    public function it_reverses_fees_correctly()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // First apply fee
        $applyResult = $this->activity->execute($request);
        $appliedFee = $applyResult->appliedFee;

        // Act - reverse the fee
        $reverseResult = $this->activity->execute($request, ['reverse' => true]);

        // Assert
        $this->assertTrue($reverseResult->success);
        $this->assertEquals($appliedFee, $reverseResult->appliedFee);
        $this->assertEquals('reversed', $reverseResult->status);
    }

    /** @test */
    public function it_updates_wallet_balances_correctly()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment'
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert
        $senderWallet = AgentWalletAggregate::retrieve($this->senderDid);
        $feeCollectorDid = config('agent_protocol.fees.fee_collector_did');
        $feeCollector = AgentWalletAggregate::retrieve($feeCollectorDid);

        // Sender should have been debited the fee
        $this->assertEquals(1000.00 - $result->appliedFee, $senderWallet->getBalance());

        // Fee collector should have received the fee
        $this->assertEquals($result->appliedFee, $feeCollector->getBalance());
    }

    /** @test */
    public function it_rejects_custom_fee_rate_above_limit()
    {
        // Arrange
        $request = new AgentPaymentRequest(
            fromAgentDid: $this->senderDid,
            toAgentDid: $this->receiverDid,
            amount: 100.00,
            currency: 'USD',
            purpose: 'payment',
            metadata: ['custom_fee_rate' => 0.15] // 15% - above 10% limit
        );

        // Act
        $result = $this->activity->execute($request);

        // Assert - Should use standard rate instead
        $standardFee = max(
            config('agent_protocol.fees.minimum_fee', 0.50),
            min(
                100.00 * config('agent_protocol.fees.standard_rate', 0.025),
                config('agent_protocol.fees.maximum_fee', 100.00)
            )
        );
        $this->assertEquals($standardFee, $result->appliedFee);
    }
}
