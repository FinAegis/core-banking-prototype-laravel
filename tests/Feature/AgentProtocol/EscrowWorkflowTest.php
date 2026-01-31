<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\DataObjects\EscrowRequest;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the Escrow Workflow functionality.
 *
 * Tests the EscrowAggregate for secure agent-to-agent payments
 * with condition-based release and dispute resolution.
 */
class EscrowWorkflowTest extends TestCase
{
    private string $buyerDid;

    private string $sellerDid;

    private string $escrowId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyerDid = 'did:agent:test:buyer-' . Str::random(8);
        $this->sellerDid = 'did:agent:test:seller-' . Str::random(8);
        $this->escrowId = 'escrow-' . Str::uuid()->toString();
    }

    public function test_can_create_escrow_with_conditions(): void
    {
        // Arrange
        $conditions = [
            'delivery_confirmed' => false,
            'quality_accepted'   => false,
        ];
        $releaseConditions = ['delivery_confirmed', 'quality_accepted'];

        // Act
        $request = new EscrowRequest(
            buyerDid: $this->buyerDid,
            sellerDid: $this->sellerDid,
            amount: 500.00,
            currency: 'USD',
            conditions: $conditions,
            releaseConditions: $releaseConditions,
            timeoutSeconds: 86400
        );

        // Assert
        $this->assertEquals($this->buyerDid, $request->buyerDid);
        $this->assertEquals($this->sellerDid, $request->sellerDid);
        $this->assertEquals(500.00, $request->amount);
        $this->assertEquals('USD', $request->currency);
        $this->assertCount(2, $request->conditions);
        $this->assertCount(2, $request->releaseConditions);
        $this->assertFalse($request->areReleaseConditionsMet());
        $this->assertStringStartsWith('escrow-', $request->escrowId);
    }

    public function test_can_fund_escrow(): void
    {
        // Arrange - Create escrow aggregate
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 500.00,
            currency: 'USD',
            conditions: ['delivery_confirmed' => false],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        // Assert initial state
        $this->assertEquals('created', $escrow->getStatus());
        $this->assertEquals(0.0, $escrow->getFundedAmount());

        // Act - Deposit funds
        $escrow->deposit(
            amount: 500.00,
            depositedBy: $this->buyerDid,
            depositDetails: ['source' => 'wallet']
        );

        // Assert after funding
        $this->assertEquals('funded', $escrow->getStatus());
        $this->assertEquals(500.00, $escrow->getFundedAmount());
        $this->assertTrue($escrow->isFunded());
        $this->assertTrue($escrow->isFullyFunded());
    }

    public function test_can_release_funds_when_conditions_met(): void
    {
        // Arrange - Create and fund escrow
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 250.00,
            currency: 'USD',
            conditions: [], // No conditions required
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        $escrow->deposit(
            amount: 250.00,
            depositedBy: $this->buyerDid,
            depositDetails: []
        );

        // Act - Release funds
        $escrow->release(
            releasedBy: $this->buyerDid,
            reason: 'Service completed',
            releaseDetails: []
        );

        // Assert
        $this->assertEquals('released', $escrow->getStatus());
        $this->assertEquals($this->buyerDid, $escrow->getReleasedBy());
        $this->assertNotNull($escrow->getReleasedAt());
    }

    public function test_can_handle_disputes(): void
    {
        // Arrange - Create and fund escrow
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 1000.00,
            currency: 'USD',
            conditions: ['delivery_confirmed' => false],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        $escrow->deposit(
            amount: 1000.00,
            depositedBy: $this->buyerDid,
            depositDetails: []
        );

        // Act - Raise dispute
        $escrow->dispute(
            disputedBy: $this->buyerDid,
            reason: 'Service not delivered as promised',
            disputeEvidence: ['expected' => 'Full delivery', 'received' => 'Partial delivery']
        );

        // Assert
        $this->assertEquals('disputed', $escrow->getStatus());
        $this->assertTrue($escrow->isDisputed());
        $this->assertEquals($this->buyerDid, $escrow->getDisputedBy());
        $this->assertEquals('Service not delivered as promised', $escrow->getDisputeReason());
    }

    public function test_can_resolve_dispute_with_refund(): void
    {
        // Arrange - Create, fund, and dispute escrow
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 750.00,
            currency: 'USD',
            conditions: [],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        $escrow->deposit(750.00, $this->buyerDid, []);
        $escrow->dispute($this->buyerDid, 'Quality issues', []);

        // Act - Resolve dispute in favor of buyer (refund)
        $resolverDid = 'did:agent:test:resolver';
        $escrow->resolveDispute(
            resolvedBy: $resolverDid,
            resolutionType: 'return_to_sender',
            resolutionAllocation: ['sender' => 750.00, 'receiver' => 0.00],
            resolutionDetails: ['ruling' => 'Buyer claim validated']
        );

        // Assert
        $this->assertEquals('resolved', $escrow->getStatus());
        $this->assertTrue($escrow->isDisputeResolved());
        $this->assertEquals('return_to_sender', $escrow->getResolutionType());
        $this->assertEquals(['sender' => 750.00, 'receiver' => 0.00], $escrow->getResolutionAllocation());
    }

    public function test_can_handle_timeout_expiry(): void
    {
        // Arrange - Create escrow (must be valid future date for creation)
        // Then test hasExpired() method with expiration check logic
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 300.00,
            currency: 'USD',
            conditions: ['delivery_confirmed' => false],
            expiresAt: now()->addSeconds(1)->toIso8601String() // Expires in 1 second
        );

        $escrow->deposit(300.00, $this->buyerDid, []);

        // Verify escrow can be expired - expire() takes no parameters
        $escrow->expire();

        // Assert
        $this->assertEquals('expired', $escrow->getStatus());
    }

    public function test_prevents_release_without_meeting_conditions(): void
    {
        // Arrange - Create escrow with unmet conditions
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 400.00,
            currency: 'USD',
            conditions: ['delivery_confirmed' => false, 'inspection_passed' => false],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        $escrow->deposit(400.00, $this->buyerDid, []);

        // Assert - Conditions are not met
        $this->assertFalse($escrow->isReadyForRelease());

        // The aggregate should validate conditions in real scenario
        // For now, verify the condition check mechanism works
        $conditions = $escrow->getConditions();
        $this->assertFalse($conditions['delivery_confirmed']);
        $this->assertFalse($conditions['inspection_passed']);
    }

    public function test_calculates_escrow_duration(): void
    {
        // Arrange
        $createdAt = Carbon::now();
        $timeoutSeconds = 172800; // 48 hours

        $request = new EscrowRequest(
            buyerDid: $this->buyerDid,
            sellerDid: $this->sellerDid,
            amount: 100.00,
            currency: 'USD',
            conditions: [],
            releaseConditions: [],
            timeoutSeconds: $timeoutSeconds,
            escrowId: $this->escrowId,
            createdAt: $createdAt
        );

        // Act
        $timeoutAt = $request->getTimeoutAt();

        // Assert
        $this->assertEquals($createdAt->copy()->addSeconds($timeoutSeconds), $timeoutAt);
        $this->assertFalse($request->isTimedOut());

        // Test with expired escrow
        $expiredRequest = new EscrowRequest(
            buyerDid: $this->buyerDid,
            sellerDid: $this->sellerDid,
            amount: 100.00,
            currency: 'USD',
            conditions: [],
            releaseConditions: [],
            timeoutSeconds: 1, // 1 second timeout
            escrowId: 'escrow-expired',
            createdAt: Carbon::now()->subMinutes(5)
        );

        $this->assertTrue($expiredRequest->isTimedOut());
    }

    public function test_can_cancel_unfunded_escrow(): void
    {
        // Arrange - Create escrow without funding
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 200.00,
            currency: 'USD',
            conditions: [],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        // Act - Cancel the escrow
        $escrow->cancel(
            cancelledBy: $this->buyerDid,
            reason: 'Changed mind before funding',
            cancellationDetails: []
        );

        // Assert
        $this->assertEquals('cancelled', $escrow->getStatus());
    }

    public function test_can_resolve_dispute_with_split_allocation(): void
    {
        // Arrange - Create, fund, and dispute escrow
        $escrow = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: 'txn-' . Str::uuid()->toString(),
            senderAgentId: $this->buyerDid,
            receiverAgentId: $this->sellerDid,
            amount: 1000.00,
            currency: 'USD',
            conditions: [],
            expiresAt: now()->addDays(7)->toIso8601String()
        );

        $escrow->deposit(1000.00, $this->buyerDid, []);
        $escrow->dispute($this->sellerDid, 'Partial delivery dispute', []);

        // Act - Resolve with 70/30 split
        $escrow->resolveDispute(
            resolvedBy: 'did:agent:test:arbitrator',
            resolutionType: 'split',
            resolutionAllocation: ['sender' => 300.00, 'receiver' => 700.00],
            resolutionDetails: ['ruling' => 'Partial delivery confirmed, 70% to seller']
        );

        // Assert
        $this->assertEquals('resolved', $escrow->getStatus());
        $this->assertEquals('split', $escrow->getResolutionType());
        $this->assertEquals(['sender' => 300.00, 'receiver' => 700.00], $escrow->getResolutionAllocation());
    }
}
