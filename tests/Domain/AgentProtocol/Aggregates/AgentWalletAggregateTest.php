<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class AgentWalletAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_create_wallet(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $currency = 'USD';
        $initialBalance = 1000.0;
        $metadata = ['type' => 'primary'];

        $aggregate = AgentWalletAggregate::create(
            $walletId,
            $agentId,
            $currency,
            $initialBalance,
            $metadata
        );

        $aggregate->persist();

        $this->assertInstanceOf(AgentWalletAggregate::class, $aggregate);
        $this->assertEquals($walletId, $aggregate->getWalletId());
        $this->assertEquals($agentId, $aggregate->getAgentId());
        $this->assertEquals($currency, $aggregate->getCurrency());
        $this->assertEquals($initialBalance, $aggregate->getBalance());
        $this->assertEquals($initialBalance, $aggregate->getAvailableBalance());
        $this->assertEquals(0.0, $aggregate->getHeldBalance());
        $this->assertTrue($aggregate->isActive());
    }

    public function test_can_initiate_payment(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);
        $aggregate->initiatePayment($transactionId, $toAgentId, 250.0, 'transfer');

        $aggregate->persist();

        $transactions = $aggregate->getTransactions();
        $this->assertArrayHasKey($transactionId, $transactions);
        $this->assertEquals('pending', $transactions[$transactionId]['status']);
        $this->assertEquals(250.0, $transactions[$transactionId]['amount']);
        $this->assertEquals($toAgentId, $transactions[$transactionId]['to_agent_id']);

        // Check that balance is held
        $this->assertEquals(1000.0, $aggregate->getBalance());
        $this->assertEquals(750.0, $aggregate->getAvailableBalance());
        $this->assertEquals(250.0, $aggregate->getHeldBalance());
    }

    public function test_cannot_initiate_payment_with_insufficient_balance(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 100.0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $aggregate->initiatePayment($transactionId, $toAgentId, 500.0, 'transfer');
    }

    public function test_cannot_initiate_payment_with_negative_amount(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be positive');

        $aggregate->initiatePayment($transactionId, $toAgentId, -50.0, 'transfer');
    }

    public function test_can_complete_payment(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);
        $aggregate->initiatePayment($transactionId, $toAgentId, 250.0, 'transfer');
        $aggregate->completePayment($transactionId, 250.0, $toAgentId);

        $aggregate->persist();

        $transactions = $aggregate->getTransactions();
        $this->assertEquals('completed', $transactions[$transactionId]['status']);

        // Check final balances
        $this->assertEquals(750.0, $aggregate->getBalance());
        $this->assertEquals(750.0, $aggregate->getAvailableBalance());
        $this->assertEquals(0.0, $aggregate->getHeldBalance());
    }

    public function test_can_receive_payment(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $fromAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 500.0);
        $aggregate->receivePayment($transactionId, $fromAgentId, 300.0);

        $aggregate->persist();

        $transactions = $aggregate->getTransactions();
        $this->assertArrayHasKey($transactionId, $transactions);
        $this->assertEquals('completed', $transactions[$transactionId]['status']);
        $this->assertEquals('received', $transactions[$transactionId]['type']);
        $this->assertEquals($fromAgentId, $transactions[$transactionId]['from_agent_id']);

        // Check updated balance
        $this->assertEquals(800.0, $aggregate->getBalance());
        $this->assertEquals(800.0, $aggregate->getAvailableBalance());
    }

    public function test_has_sufficient_balance(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);

        $this->assertTrue($aggregate->hassufficientBalance(500.0));
        $this->assertTrue($aggregate->hassufficientBalance(1000.0));
        $this->assertFalse($aggregate->hassufficientBalance(1001.0));
    }

    public function test_is_within_limit(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);

        // Default limits are set in the aggregate
        $this->assertTrue($aggregate->isWithinLimit('per_transaction', 5000.0));
        $this->assertTrue($aggregate->isWithinLimit('per_transaction', 10000.0));
        $this->assertFalse($aggregate->isWithinLimit('per_transaction', 10001.0));

        // Non-existent limit type should return true (no limit)
        $this->assertTrue($aggregate->isWithinLimit('non_existent', PHP_FLOAT_MAX));
    }

    public function test_can_retrieve_and_reconstitute_wallet(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId = Str::uuid()->toString();
        $transactionId = Str::uuid()->toString();

        // Create and persist wallet with transactions
        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);
        $aggregate->initiatePayment($transactionId, $toAgentId, 250.0, 'transfer');
        $aggregate->persist();

        // Retrieve and verify
        $retrievedAggregate = AgentWalletAggregate::retrieve($walletId);

        $this->assertEquals($walletId, $retrievedAggregate->getWalletId());
        $this->assertEquals($agentId, $retrievedAggregate->getAgentId());
        $this->assertEquals(1000.0, $retrievedAggregate->getBalance());
        $this->assertEquals(750.0, $retrievedAggregate->getAvailableBalance());
        $this->assertEquals(250.0, $retrievedAggregate->getHeldBalance());

        $transactions = $retrievedAggregate->getTransactions();
        $this->assertArrayHasKey($transactionId, $transactions);
    }

    public function test_multiple_concurrent_transactions(): void
    {
        $walletId = Str::uuid()->toString();
        $agentId = Str::uuid()->toString();
        $toAgentId1 = Str::uuid()->toString();
        $toAgentId2 = Str::uuid()->toString();
        $transactionId1 = Str::uuid()->toString();
        $transactionId2 = Str::uuid()->toString();

        $aggregate = AgentWalletAggregate::create($walletId, $agentId, 'USD', 1000.0);
        $aggregate->initiatePayment($transactionId1, $toAgentId1, 200.0, 'transfer');
        $aggregate->initiatePayment($transactionId2, $toAgentId2, 300.0, 'transfer');

        $aggregate->persist();

        // Both payments should be held
        $this->assertEquals(1000.0, $aggregate->getBalance());
        $this->assertEquals(500.0, $aggregate->getAvailableBalance());
        $this->assertEquals(500.0, $aggregate->getHeldBalance());

        // Complete first payment
        $aggregate->completePayment($transactionId1, 200.0, $toAgentId1);
        $aggregate->persist();

        $this->assertEquals(800.0, $aggregate->getBalance());
        $this->assertEquals(500.0, $aggregate->getAvailableBalance());
        $this->assertEquals(300.0, $aggregate->getHeldBalance());

        // Complete second payment
        $aggregate->completePayment($transactionId2, 300.0, $toAgentId2);
        $aggregate->persist();

        $this->assertEquals(500.0, $aggregate->getBalance());
        $this->assertEquals(500.0, $aggregate->getAvailableBalance());
        $this->assertEquals(0.0, $aggregate->getHeldBalance());
    }
}
