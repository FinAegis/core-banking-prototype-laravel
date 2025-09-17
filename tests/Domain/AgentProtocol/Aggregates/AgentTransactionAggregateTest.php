<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
use App\Domain\AgentProtocol\Events\FeeCalculated;
use App\Domain\AgentProtocol\Events\TransactionCompleted;
use App\Domain\AgentProtocol\Events\TransactionInitiated;
use App\Domain\AgentProtocol\Events\TransactionValidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AgentTransactionAggregateTest extends TestCase
{
    use RefreshDatabase;

    private string $transactionId;

    private string $fromAgentId;

    private string $toAgentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionId = 'txn_' . uniqid();
        $this->fromAgentId = 'agent_sender_' . uniqid();
        $this->toAgentId = 'agent_receiver_' . uniqid();
    }

    /** @test */
    public function it_can_initiate_direct_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->persist();

        $this->assertInstanceOf(AgentTransactionAggregate::class, $aggregate);
        $this->assertEquals($this->transactionId, $aggregate->getTransactionId());
        $this->assertEquals($this->fromAgentId, $aggregate->getFromAgentId());
        $this->assertEquals($this->toAgentId, $aggregate->getToAgentId());
        $this->assertEquals(1000.00, $aggregate->getAmount());
        $this->assertEquals('USD', $aggregate->getCurrency());
        $this->assertEquals('direct', $aggregate->getType());
        $this->assertEquals('initiated', $aggregate->getStatus());
    }

    /** @test */
    public function it_can_initiate_escrow_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'EUR',
            type: 'escrow'
        );

        $aggregate->persist();

        $this->assertEquals('escrow', $aggregate->getType());
        $this->assertNotNull($aggregate->getEscrowId());
        $this->assertStringStartsWith('escrow_', $aggregate->getEscrowId());
        $this->assertTrue($aggregate->isEscrowTransaction());
    }

    /** @test */
    public function it_throws_exception_for_zero_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction amount must be greater than zero');

        AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 0,
            currency: 'USD',
            type: 'direct'
        );
    }

    /** @test */
    public function it_throws_exception_for_negative_amount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction amount must be greater than zero');

        AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: -100,
            currency: 'USD',
            type: 'direct'
        );
    }

    /** @test */
    public function it_throws_exception_for_invalid_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid transaction type: invalid');

        AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000,
            currency: 'USD',
            type: 'invalid'
        );
    }

    /** @test */
    public function it_can_validate_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $validationData = [
            'kyc_verified' => true,
            'aml_checked'  => true,
            'risk_score'   => 0.2,
        ];

        $aggregate->validate($validationData);
        $aggregate->persist();

        $this->assertEquals('validated', $aggregate->getStatus());
        $metadata = $aggregate->getMetadata();
        $this->assertArrayHasKey('validation', $metadata);
        $this->assertEquals($validationData, $metadata['validation']);
    }

    /** @test */
    public function it_throws_exception_when_validating_non_initiated_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot validate transaction in status: validated');

        $aggregate->validate(); // Try to validate again
    }

    /** @test */
    public function it_can_calculate_fees()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->calculateFees(25.00, 'processing', ['rate' => '2.5%']);
        $aggregate->calculateFees(5.00, 'network', ['type' => 'blockchain']);
        $aggregate->persist();

        $fees = $aggregate->getFees();
        $this->assertCount(2, $fees);
        $this->assertEquals(25.00, $fees[0]['amount']);
        $this->assertEquals('processing', $fees[0]['type']);
        $this->assertEquals(5.00, $fees[1]['amount']);
        $this->assertEquals('network', $fees[1]['type']);
    }

    /** @test */
    public function it_throws_exception_for_negative_fee()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fee amount cannot be negative');

        $aggregate->calculateFees(-10.00, 'processing');
    }

    /** @test */
    public function it_can_hold_funds_in_escrow()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $aggregate->validate();
        $aggregate->holdInEscrow(5000.00, ['condition' => 'delivery_confirmed']);
        $aggregate->persist();

        $this->assertEquals('processing', $aggregate->getStatus());
        $this->assertTrue($aggregate->hasEscrowHeld());
    }

    /** @test */
    public function it_throws_exception_when_holding_escrow_for_non_escrow_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct' // Not escrow
        );

        $aggregate->validate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only hold escrow for escrow-type transactions');

        $aggregate->holdInEscrow(1000.00);
    }

    /** @test */
    public function it_throws_exception_when_holding_escrow_amount_exceeds_transaction_amount()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $aggregate->validate();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid escrow amount');

        $aggregate->holdInEscrow(2000.00); // More than transaction amount
    }

    /** @test */
    public function it_can_release_funds_from_escrow()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $aggregate->validate();
        $aggregate->holdInEscrow(5000.00);
        $aggregate->releaseFromEscrow('system', 'conditions_met', ['condition' => 'delivery_confirmed']);
        $aggregate->persist();

        $this->assertFalse($aggregate->hasEscrowHeld());
    }

    /** @test */
    public function it_throws_exception_when_releasing_escrow_without_holding()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No escrow funds to release');

        $aggregate->releaseFromEscrow('system', 'test');
    }

    /** @test */
    public function it_can_complete_direct_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();
        $aggregate->calculateFees(25.00, 'processing');
        $aggregate->complete('success', ['confirmation_number' => 'CONF123']);
        $aggregate->persist();

        $this->assertEquals('completed', $aggregate->getStatus());
    }

    /** @test */
    public function it_can_complete_escrow_transaction_after_release()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $aggregate->validate();
        $aggregate->holdInEscrow(5000.00);
        $aggregate->releaseFromEscrow('system', 'conditions_met');
        $aggregate->complete('success');
        $aggregate->persist();

        $this->assertEquals('completed', $aggregate->getStatus());
    }

    /** @test */
    public function it_throws_exception_when_completing_escrow_with_held_funds()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 5000.00,
            currency: 'USD',
            type: 'escrow'
        );

        $aggregate->validate();
        $aggregate->holdInEscrow(5000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must release escrow before completing transaction');

        $aggregate->complete();
    }

    /** @test */
    public function it_can_fail_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();
        $aggregate->fail('Insufficient funds', ['balance' => 500.00]);
        $aggregate->persist();

        $this->assertEquals('failed', $aggregate->getStatus());
        $this->assertEquals('Insufficient funds', $aggregate->getFailureReason());
    }

    /** @test */
    public function it_throws_exception_when_failing_completed_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();
        $aggregate->complete();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot fail transaction in status: completed');

        $aggregate->fail('Test');
    }

    /** @test */
    public function it_can_add_split_recipients()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'split'
        );

        $aggregate->addSplitRecipient('agent_1', 400.00, 'fixed');
        $aggregate->addSplitRecipient('agent_2', 300.00, 'fixed');
        $aggregate->addSplitRecipient('agent_3', 300.00, 'fixed');
        $aggregate->persist();

        $splits = $aggregate->getSplitDetails();
        $this->assertCount(3, $splits);
        $this->assertEquals(1000.00, array_sum(array_column($splits, 'amount')));
    }

    /** @test */
    public function it_throws_exception_when_split_exceeds_transaction_amount()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'split'
        );

        $aggregate->addSplitRecipient('agent_1', 600.00, 'fixed');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Total split amount exceeds transaction amount');

        $aggregate->addSplitRecipient('agent_2', 600.00, 'fixed'); // Total would be 1200
    }

    /** @test */
    public function it_throws_exception_when_adding_split_to_non_split_transaction()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only add split recipients to split-type transactions');

        $aggregate->addSplitRecipient('agent_1', 500.00, 'fixed');
    }

    /** @test */
    public function it_stores_metadata_correctly()
    {
        $metadata = [
            'order_id'    => 'ORD123',
            'description' => 'Payment for services',
            'tags'        => ['urgent', 'verified'],
        ];

        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct',
            metadata: $metadata
        );

        $aggregate->persist();

        $storedMetadata = $aggregate->getMetadata();
        $this->assertEquals('ORD123', $storedMetadata['order_id']);
        $this->assertEquals('Payment for services', $storedMetadata['description']);
        $this->assertEquals(['urgent', 'verified'], $storedMetadata['tags']);
    }

    /** @test */
    public function it_tracks_events_correctly()
    {
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();
        $aggregate->calculateFees(25.00, 'processing');
        $aggregate->complete();

        $events = $aggregate->getRecordedEvents();

        $this->assertCount(4, $events);
        $this->assertInstanceOf(TransactionInitiated::class, $events[0]);
        $this->assertInstanceOf(TransactionValidated::class, $events[1]);
        $this->assertInstanceOf(FeeCalculated::class, $events[2]);
        $this->assertInstanceOf(TransactionCompleted::class, $events[3]);
    }

    /** @test */
    public function it_can_be_reconstituted_from_events()
    {
        // Create and persist initial aggregate
        $aggregate = AgentTransactionAggregate::initiate(
            transactionId: $this->transactionId,
            fromAgentId: $this->fromAgentId,
            toAgentId: $this->toAgentId,
            amount: 1000.00,
            currency: 'USD',
            type: 'direct'
        );

        $aggregate->validate();
        $aggregate->calculateFees(25.00, 'processing');
        $aggregate->complete();
        $aggregate->persist();

        // Load aggregate from events
        $reconstituted = AgentTransactionAggregate::retrieve($aggregate->uuid());

        $this->assertEquals($this->transactionId, $reconstituted->getTransactionId());
        $this->assertEquals('completed', $reconstituted->getStatus());
        $this->assertEquals(1000.00, $reconstituted->getAmount());
        $this->assertCount(1, $reconstituted->getFees());
    }
}
