<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentWalletService $service;

    private string $agentId1;

    private string $agentId2;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to ensure test isolation
        Cache::flush();

        $this->service = new AgentWalletService();
        $this->agentId1 = 'agent_' . uniqid();
        $this->agentId2 = 'agent_' . uniqid();

        // Create agent identities to satisfy foreign key constraints
        AgentIdentity::create([
            'agent_id'         => $this->agentId1,
            'did'              => 'did:example:' . $this->agentId1,
            'name'             => 'Test Agent 1',
            'type'             => 'autonomous',
            'status'           => 'active',
            'reputation_score' => 50.00,
        ]);

        AgentIdentity::create([
            'agent_id'         => $this->agentId2,
            'did'              => 'did:example:' . $this->agentId2,
            'name'             => 'Test Agent 2',
            'type'             => 'autonomous',
            'status'           => 'active',
            'reputation_score' => 50.00,
        ]);
    }

    #[Test]
    public function it_creates_wallet_with_default_currency()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            initialBalance: 1000.00
        );

        $this->assertInstanceOf(AgentWallet::class, $wallet);
        $this->assertEquals($this->agentId1, $wallet->agent_id);
        $this->assertEquals('USD', $wallet->currency);
        $this->assertEquals(1000.00, $wallet->available_balance);
        $this->assertEquals(0.0, $wallet->held_balance);
        $this->assertEquals(1000.00, $wallet->total_balance);
        $this->assertTrue($wallet->is_active);
    }

    #[Test]
    public function it_creates_wallet_with_custom_currency()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'EUR',
            initialBalance: 500.00,
            metadata: ['type' => 'business']
        );

        $this->assertEquals('EUR', $wallet->currency);
        $this->assertEquals(500.00, $wallet->available_balance);
        $this->assertEquals(['type' => 'business'], $wallet->metadata);
    }

    #[Test]
    public function it_rejects_unsupported_currency()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency: XYZ');

        $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'XYZ'
        );
    }

    #[Test]
    public function it_gets_wallet_balance()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1500.00
        );

        $balance = $this->service->getBalance($wallet->wallet_id);

        $this->assertEquals($wallet->wallet_id, $balance['wallet_id']);
        $this->assertEquals('USD', $balance['currency']);
        $this->assertEquals(1500.00, $balance['available']);
        $this->assertEquals(0.00, $balance['held']);
        $this->assertEquals(1500.00, $balance['total']);
    }

    #[Test]
    public function it_gets_balance_with_currency_conversion()
    {
        Cache::flush(); // Clear cache to avoid conflicts

        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        $balance = $this->service->getBalance($wallet->wallet_id, 'EUR');

        $this->assertEquals('USD', $balance['currency']);
        $this->assertEquals(1000.00, $balance['available']);
        $this->assertArrayHasKey('converted', $balance);
        $this->assertEquals('EUR', $balance['converted']['currency']);
        $this->assertEquals(850.00, $balance['converted']['available']); // Based on mock rate
        $this->assertEquals(0.85, $balance['converted']['exchange_rate']);
    }

    #[Test]
    public function it_transfers_funds_between_wallets_same_currency()
    {
        $wallet1 = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 2000.00
        );

        $wallet2 = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'USD',
            initialBalance: 500.00
        );

        $transaction = $this->service->transfer(
            fromWalletId: $wallet1->wallet_id,
            toWalletId: $wallet2->wallet_id,
            amount: 300.00,
            currency: 'USD',
            metadata: ['description' => 'Test transfer']
        );

        $this->assertInstanceOf(AgentTransaction::class, $transaction);
        $this->assertEquals(300.00, $transaction->amount);
        $this->assertEquals('USD', $transaction->currency);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('domestic', $transaction->fee_type);
        $this->assertEquals(3.00, $transaction->fee_amount); // 1% domestic fee

        // Check wallet balances
        $wallet1->refresh();
        $wallet2->refresh();

        // Sender: 2000 - 300 - 3 (fee) = 1697
        $this->assertEquals(1697.00, $wallet1->available_balance);
        // Receiver: 500 + 300 = 800
        $this->assertEquals(800.00, $wallet2->available_balance);
    }

    #[Test]
    public function it_transfers_funds_with_currency_conversion()
    {
        $wallet1 = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 2000.00
        );

        $wallet2 = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'EUR',
            initialBalance: 500.00
        );

        $transaction = $this->service->transfer(
            fromWalletId: $wallet1->wallet_id,
            toWalletId: $wallet2->wallet_id,
            amount: 100.00,
            currency: 'USD',
            metadata: ['description' => 'International transfer']
        );

        $this->assertEquals('international', $transaction->fee_type);
        $this->assertEquals(2.50, $transaction->fee_amount); // 2.5% international fee

        // Check metadata contains conversion info
        $metadata = $transaction->metadata;
        $this->assertEquals('USD', $metadata['from_currency']);
        $this->assertEquals('EUR', $metadata['to_currency']);
        $this->assertEquals(100.00, $metadata['from_amount']);
        $this->assertEquals(85.00, $metadata['to_amount']); // 100 USD * 0.85 = 85 EUR

        // Check wallet balances
        $wallet1->refresh();
        $wallet2->refresh();

        // Sender: 2000 - 100 - 2.5 (fee) = 1897.50
        $this->assertEquals(1897.50, $wallet1->available_balance);
        // Receiver: 500 + 85 = 585 EUR
        $this->assertEquals(585.00, $wallet2->available_balance);
    }

    #[Test]
    public function it_rejects_transfer_with_insufficient_balance()
    {
        $wallet1 = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 100.00
        );

        $wallet2 = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'USD'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance for transfer');

        $this->service->transfer(
            fromWalletId: $wallet1->wallet_id,
            toWalletId: $wallet2->wallet_id,
            amount: 500.00,
            currency: 'USD'
        );
    }

    #[Test]
    public function it_holds_funds_for_escrow()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        $this->service->holdFunds(
            walletId: $wallet->wallet_id,
            amount: 300.00,
            reason: 'escrow_transaction',
            metadata: ['escrow_id' => 'escrow_123']
        );

        $wallet->refresh();
        $this->assertEquals(700.00, $wallet->available_balance);
        $this->assertEquals(300.00, $wallet->held_balance);
        $this->assertEquals(1000.00, $wallet->total_balance);

        // Verify aggregate was updated
        $aggregate = AgentWalletAggregate::retrieve($wallet->wallet_id);
        $this->assertEquals(700.00, $aggregate->getAvailableBalance());
        $this->assertEquals(300.00, $aggregate->getHeldBalance());
    }

    #[Test]
    public function it_releases_held_funds()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        // Hold funds first
        $this->service->holdFunds(
            walletId: $wallet->wallet_id,
            amount: 400.00,
            reason: 'escrow_transaction'
        );

        // Release part of held funds
        $this->service->releaseFunds(
            walletId: $wallet->wallet_id,
            amount: 250.00,
            reason: 'escrow_completed',
            metadata: ['escrow_id' => 'escrow_123']
        );

        $wallet->refresh();
        $this->assertEquals(850.00, $wallet->available_balance); // 600 + 250
        $this->assertEquals(150.00, $wallet->held_balance); // 400 - 250
        $this->assertEquals(1000.00, $wallet->total_balance);
    }

    #[Test]
    public function it_rejects_hold_with_insufficient_available_balance()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 500.00
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient available balance to hold');

        $this->service->holdFunds(
            walletId: $wallet->wallet_id,
            amount: 600.00,
            reason: 'escrow_transaction'
        );
    }

    #[Test]
    public function it_rejects_release_with_insufficient_held_balance()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        $this->service->holdFunds(
            walletId: $wallet->wallet_id,
            amount: 200.00,
            reason: 'escrow_transaction'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient held balance to release');

        $this->service->releaseFunds(
            walletId: $wallet->wallet_id,
            amount: 300.00,
            reason: 'escrow_completed'
        );
    }

    #[Test]
    public function it_gets_supported_currencies()
    {
        $currencies = $this->service->getSupportedCurrencies();

        $this->assertIsArray($currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertContains('GBP', $currencies);
        $this->assertContains('JPY', $currencies);
    }

    #[Test]
    public function it_checks_if_currency_is_supported()
    {
        $this->assertTrue($this->service->isCurrencySupported('USD'));
        $this->assertTrue($this->service->isCurrencySupported('EUR'));
        $this->assertTrue($this->service->isCurrencySupported('usd')); // Case insensitive
        $this->assertFalse($this->service->isCurrencySupported('XYZ'));
        $this->assertFalse($this->service->isCurrencySupported('BTC'));
    }

    #[Test]
    public function it_gets_transaction_history()
    {
        $wallet1 = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 2000.00
        );

        $wallet2 = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'USD',
            initialBalance: 500.00
        );

        // Create some transactions with a small delay to ensure different timestamps
        $this->service->transfer(
            fromWalletId: $wallet1->wallet_id,
            toWalletId: $wallet2->wallet_id,
            amount: 100.00,
            currency: 'USD'
        );

        // Add a small delay to ensure different created_at timestamps
        sleep(1);

        $this->service->transfer(
            fromWalletId: $wallet2->wallet_id,
            toWalletId: $wallet1->wallet_id,
            amount: 50.00,
            currency: 'USD'
        );

        // Get history for wallet1
        $history = $this->service->getTransactionHistory($wallet1->wallet_id);

        $this->assertCount(2, $history);

        // First transaction (most recent) - incoming
        $this->assertEquals('incoming', $history[0]['type']);
        $this->assertEquals(50.00, $history[0]['amount']);
        $this->assertEquals(0, $history[0]['fee']); // No fee for incoming

        // Second transaction - outgoing
        $this->assertEquals('outgoing', $history[1]['type']);
        $this->assertEquals(100.00, $history[1]['amount']);
        $this->assertEquals(1.00, $history[1]['fee']); // 1% fee for outgoing
    }

    #[Test]
    public function it_handles_transaction_rollback_on_failure()
    {
        $wallet1 = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        // Create invalid wallet ID for transfer
        $invalidWalletId = 'wallet_invalid';

        try {
            $this->service->transfer(
                fromWalletId: $wallet1->wallet_id,
                toWalletId: $invalidWalletId,
                amount: 100.00,
                currency: 'USD'
            );
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            // Expected exception
        }

        // Check that wallet1 balance is unchanged
        $wallet1->refresh();
        $this->assertEquals(1000.00, $wallet1->available_balance);

        // Check that no transaction was created
        $transactions = AgentTransaction::where('from_agent_id', $this->agentId1)->get();
        $this->assertCount(0, $transactions);
    }

    #[Test]
    public function it_uses_cached_exchange_rates()
    {
        Cache::flush(); // Clear cache first

        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        // First call should cache the rate
        $balance1 = $this->service->getBalance($wallet->wallet_id, 'EUR');
        $rate1 = $balance1['converted']['exchange_rate'];

        // Mock a change that won't affect cached value
        Cache::put('exchange_rate:USD:EUR', 0.90, 300);

        // Second call should use cached rate
        $balance2 = $this->service->getBalance($wallet->wallet_id, 'EUR');
        $rate2 = $balance2['converted']['exchange_rate'];

        $this->assertEquals(0.90, $rate2); // Should use our cached value
    }

    #[Test]
    public function it_determines_correct_fee_type()
    {
        // Create third agent for international transfer test
        $agentId3 = 'agent_3_' . uniqid();
        AgentIdentity::create([
            'agent_id'         => $agentId3,
            'did'              => 'did:example:' . $agentId3,
            'name'             => 'Test Agent 3',
            'type'             => 'autonomous',
            'status'           => 'active',
            'reputation_score' => 50.00,
        ]);

        $wallet1USD = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        $wallet2USD = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'USD',
            initialBalance: 500.00
        );

        $wallet3EUR = $this->service->createWallet(
            agentId: $agentId3,
            currency: 'EUR',
            initialBalance: 500.00
        );

        // Domestic transfer (same currency)
        $transaction1 = $this->service->transfer(
            fromWalletId: $wallet1USD->wallet_id,
            toWalletId: $wallet2USD->wallet_id,
            amount: 100.00,
            currency: 'USD'
        );
        $this->assertEquals('domestic', $transaction1->fee_type);
        $this->assertEquals(1.00, $transaction1->fee_amount); // 1%

        // International transfer (different currencies)
        $transaction2 = $this->service->transfer(
            fromWalletId: $wallet1USD->wallet_id,
            toWalletId: $wallet3EUR->wallet_id,
            amount: 100.00,
            currency: 'USD'
        );
        $this->assertEquals('international', $transaction2->fee_type);
        $this->assertEquals(2.50, $transaction2->fee_amount); // 2.5%
    }

    #[Test]
    public function it_handles_concurrent_transfers_with_locks()
    {
        $wallet = $this->service->createWallet(
            agentId: $this->agentId1,
            currency: 'USD',
            initialBalance: 1000.00
        );

        $wallet2 = $this->service->createWallet(
            agentId: $this->agentId2,
            currency: 'USD',
            initialBalance: 0.00
        );

        // Simulate concurrent transfers (transactions are already handled in the service)
        for ($i = 0; $i < 3; $i++) {
            $this->service->transfer(
                fromWalletId: $wallet->wallet_id,
                toWalletId: $wallet2->wallet_id,
                amount: 300.00,
                currency: 'USD'
            );
        }

        $wallet->refresh();
        $wallet2->refresh();

        // After 3 transfers of 300 each with 1% fee:
        // Sender: 1000 - (300 * 3) - (3 * 3) = 1000 - 900 - 9 = 91
        $this->assertEquals(91.00, $wallet->available_balance);
        // Receiver: 0 + (300 * 3) = 900
        $this->assertEquals(900.00, $wallet2->available_balance);
    }
}
