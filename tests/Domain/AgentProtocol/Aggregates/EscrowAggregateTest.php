<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\Events\EscrowCreated;
use App\Domain\AgentProtocol\Events\EscrowDisputed;
use App\Domain\AgentProtocol\Events\EscrowDisputeResolved;
use App\Domain\AgentProtocol\Events\EscrowFundsDeposited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EscrowAggregateTest extends TestCase
{
    use RefreshDatabase;

    private string $escrowId;

    private string $transactionId;

    private string $senderAgentId;

    private string $receiverAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->escrowId = 'escrow_' . uniqid();
        $this->transactionId = 'txn_' . uniqid();
        $this->senderAgentId = 'agent_sender_' . uniqid();
        $this->receiverAgentId = 'agent_receiver_' . uniqid();
    }

    #[Test]
    public function it_can_create_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->persist();

        $this->assertInstanceOf(EscrowAggregate::class, $aggregate);
        $this->assertEquals($this->escrowId, $aggregate->getEscrowId());
        $this->assertEquals($this->transactionId, $aggregate->getTransactionId());
        $this->assertEquals($this->senderAgentId, $aggregate->getSenderAgentId());
        $this->assertEquals($this->receiverAgentId, $aggregate->getReceiverAgentId());
        $this->assertEquals(5000.00, $aggregate->getAmount());
        $this->assertEquals('USD', $aggregate->getCurrency());
        $this->assertEquals('created', $aggregate->getStatus());
        $this->assertFalse($aggregate->isFullyFunded());
    }

    #[Test]
    public function it_can_create_escrow_with_conditions()
    {
        $conditions = [
            ['type' => 'delivery', 'description' => 'Product delivered', 'met' => false],
            ['type' => 'approval', 'description' => 'Buyer approval', 'met' => false],
        ];

        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 10000.00,
            currency: 'EUR',
            conditions: $conditions
        );

        $aggregate->persist();

        $this->assertEquals($conditions, $aggregate->getConditions());
    }

    #[Test]
    public function it_can_create_escrow_with_expiration()
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD',
            expiresAt: $expiresAt
        );

        $aggregate->persist();

        $this->assertEquals($expiresAt, $aggregate->getExpiresAt());
        $this->assertFalse($aggregate->hasExpired());
    }

    #[Test]
    public function it_throws_exception_for_zero_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Escrow amount must be greater than zero');

        EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 0,
            currency: 'USD'
        );
    }

    #[Test]
    public function it_throws_exception_for_past_expiration_date()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expiration date must be in the future');

        EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD',
            expiresAt: date('Y-m-d H:i:s', strtotime('-1 day'))
        );
    }

    #[Test]
    public function it_can_deposit_funds()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(2000.00, $this->senderAgentId, ['payment_method' => 'bank_transfer']);
        $aggregate->persist();

        $this->assertEquals(2000.00, $aggregate->getFundedAmount());
        $this->assertFalse($aggregate->isFullyFunded());
        $this->assertEquals('partially_funded', $aggregate->getStatus());
    }

    #[Test]
    public function it_can_deposit_full_amount()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->persist();

        $this->assertEquals(5000.00, $aggregate->getFundedAmount());
        $this->assertTrue($aggregate->isFullyFunded());
        $this->assertEquals('funded', $aggregate->getStatus());
    }

    #[Test]
    public function it_can_deposit_in_multiple_transactions()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(2000.00, $this->senderAgentId);
        $aggregate->deposit(1500.00, $this->senderAgentId);
        $aggregate->deposit(1500.00, $this->senderAgentId);
        $aggregate->persist();

        $this->assertEquals(5000.00, $aggregate->getFundedAmount());
        $this->assertTrue($aggregate->isFullyFunded());
    }

    #[Test]
    public function it_throws_exception_for_negative_deposit()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Deposit amount must be greater than zero');

        $aggregate->deposit(-100.00, $this->senderAgentId);
    }

    #[Test]
    public function it_can_release_funds()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->release($this->senderAgentId, 'Conditions met');
        $aggregate->persist();

        $this->assertEquals('released', $aggregate->getStatus());
        $this->assertEquals($this->senderAgentId, $aggregate->getReleasedBy());
        $this->assertNotNull($aggregate->getReleasedAt());
    }

    #[Test]
    public function it_throws_exception_when_releasing_unfunded_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot release escrow in status: created');

        $aggregate->release($this->senderAgentId, 'Test');
    }

    #[Test]
    public function it_throws_exception_when_releasing_already_released_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->release($this->senderAgentId, 'First release');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot release escrow in status: released');

        $aggregate->release($this->senderAgentId, 'Second release');
    }

    #[Test]
    public function it_can_raise_dispute()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute(
            $this->receiverAgentId,
            'Product not as described',
            ['evidence' => 'photo_url', 'timestamp' => time()]
        );
        $aggregate->persist();

        $this->assertEquals('disputed', $aggregate->getStatus());
        $this->assertTrue($aggregate->isDisputed());
        $this->assertEquals($this->receiverAgentId, $aggregate->getDisputedBy());
        $this->assertEquals('Product not as described', $aggregate->getDisputeReason());
    }

    #[Test]
    public function it_throws_exception_when_disputing_unfunded_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot dispute escrow in status: created');

        $aggregate->dispute($this->receiverAgentId, 'Test');
    }

    #[Test]
    public function it_can_resolve_dispute_with_release_to_receiver()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute($this->senderAgentId, 'Services not provided');
        $aggregate->resolveDispute(
            'arbitrator_123',
            'release_to_receiver',
            ['receiver' => 5000.00],
            ['decision' => 'Services were provided as agreed']
        );
        $aggregate->persist();

        $this->assertEquals('resolved', $aggregate->getStatus());
        $this->assertEquals('release_to_receiver', $aggregate->getResolutionType());
        $this->assertEquals(['receiver' => 5000.00], $aggregate->getResolutionAllocation());
    }

    #[Test]
    public function it_can_resolve_dispute_with_return_to_sender()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute($this->receiverAgentId, 'Payment sent by mistake');
        $aggregate->resolveDispute(
            'arbitrator_123',
            'return_to_sender',
            ['sender'   => 5000.00],
            ['decision' => 'Payment was indeed sent by mistake']
        );
        $aggregate->persist();

        $this->assertEquals('resolved', $aggregate->getStatus());
        $this->assertEquals('return_to_sender', $aggregate->getResolutionType());
        $this->assertEquals(['sender' => 5000.00], $aggregate->getResolutionAllocation());
    }

    #[Test]
    public function it_can_resolve_dispute_with_split()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute($this->receiverAgentId, 'Partial service delivered');
        $aggregate->resolveDispute(
            'arbitrator_123',
            'split',
            ['sender'   => 2000.00, 'receiver' => 3000.00],
            ['decision' => '60% of services were delivered']
        );
        $aggregate->persist();

        $this->assertEquals('resolved', $aggregate->getStatus());
        $this->assertEquals('split', $aggregate->getResolutionType());
        $this->assertEquals(['sender' => 2000.00, 'receiver' => 3000.00], $aggregate->getResolutionAllocation());
    }

    #[Test]
    public function it_throws_exception_when_resolving_non_disputed_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No dispute to resolve');

        $aggregate->resolveDispute('arbitrator', 'split', ['sender' => 2500, 'receiver' => 2500]);
    }

    #[Test]
    public function it_can_expire_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD',
            expiresAt: date('Y-m-d H:i:s', strtotime('+1 hour'))
        );

        $aggregate->deposit(3000.00, $this->senderAgentId);
        $aggregate->expire();
        $aggregate->persist();

        $this->assertEquals('expired', $aggregate->getStatus());
    }

    #[Test]
    public function it_throws_exception_when_expiring_released_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->release($this->senderAgentId, 'Test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot expire escrow in status: released');

        $aggregate->expire();
    }

    #[Test]
    public function it_can_cancel_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(3000.00, $this->senderAgentId);
        $aggregate->cancel($this->senderAgentId, 'Transaction cancelled by sender');
        $aggregate->persist();

        $this->assertEquals('cancelled', $aggregate->getStatus());
    }

    #[Test]
    public function it_throws_exception_when_cancelling_released_escrow()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->release($this->senderAgentId, 'Test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel escrow in status: released');

        $aggregate->cancel($this->senderAgentId, 'Test');
    }

    #[Test]
    public function it_tracks_events_correctly()
    {
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute($this->receiverAgentId, 'Issue with service');
        $aggregate->resolveDispute('arbitrator', 'split', ['sender' => 2000, 'receiver' => 3000]);

        $events = $aggregate->getRecordedEvents();

        $this->assertCount(4, $events);
        $this->assertInstanceOf(EscrowCreated::class, $events[0]);
        $this->assertInstanceOf(EscrowFundsDeposited::class, $events[1]);
        $this->assertInstanceOf(EscrowDisputed::class, $events[2]);
        $this->assertInstanceOf(EscrowDisputeResolved::class, $events[3]);
    }

    #[Test]
    public function it_can_be_reconstituted_from_events()
    {
        // Create and persist initial aggregate
        $aggregate = EscrowAggregate::create(
            escrowId: $this->escrowId,
            transactionId: $this->transactionId,
            senderAgentId: $this->senderAgentId,
            receiverAgentId: $this->receiverAgentId,
            amount: 5000.00,
            currency: 'USD'
        );

        $aggregate->deposit(5000.00, $this->senderAgentId);
        $aggregate->dispute($this->receiverAgentId, 'Test dispute');
        $aggregate->persist();

        // Load aggregate from events
        $reconstituted = EscrowAggregate::retrieve($aggregate->uuid());

        $this->assertEquals($this->escrowId, $reconstituted->getEscrowId());
        $this->assertEquals('disputed', $reconstituted->getStatus());
        $this->assertEquals(5000.00, $reconstituted->getAmount());
        $this->assertEquals(5000.00, $reconstituted->getFundedAmount());
        $this->assertTrue($reconstituted->isDisputed());
    }
}
