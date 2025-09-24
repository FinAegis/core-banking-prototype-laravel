<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol\Integration;

use App\Domain\Account\Models\Account;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use App\Domain\AgentProtocol\Services\Integration\WalletIntegrationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tests for Agent Protocol wallet integration with main payment system.
 */
class WalletIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private WalletIntegrationService $integrationService;

    private AgentWalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->integrationService = app(WalletIntegrationService::class);
        $this->walletService = app(AgentWalletService::class);
    }

    /**
     * Test linking agent wallet to main account.
     */
    public function test_can_link_agent_wallet_to_main_account(): void
    {
        Event::fake();

        // Create agent identity with wallet
        $agent = AgentIdentity::factory()->create();
        $wallet = $this->walletService->createWallet(
            $agent->agent_id,
            'USD',
            1000.00
        );

        // Create main account
        $account = Account::factory()->create([
            'balance' => 50000, // in cents
        ]);

        // Link wallet to account
        $result = $this->integrationService->linkAgentWalletToAccount(
            $wallet->wallet_id,
            $account->uuid,
            ['enable_blockchain' => false]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals($wallet->wallet_id, $result['agent_wallet_id']);
        $this->assertEquals($account->uuid, $result['account_uuid']);

        // Verify wallet is linked
        $updatedWallet = AgentWallet::find($wallet->id);
        $this->assertEquals($account->uuid, $updatedWallet->linked_account_uuid);
        $this->assertNotNull($updatedWallet->linked_at);

        Event::assertDispatched('App\Domain\AgentProtocol\Events\Integration\AgentWalletLinked');
    }

    /**
     * Test cross-domain transaction processing.
     */
    public function test_can_process_cross_domain_transaction(): void
    {
        Event::fake();

        // Create linked wallet and account
        $agent = AgentIdentity::factory()->create();
        $wallet = $this->walletService->createWallet(
            $agent->agent_id,
            'USD',
            1000.00
        );

        $account = Account::factory()->create([
            'balance' => 0,
        ]);

        $this->integrationService->linkAgentWalletToAccount(
            $wallet->wallet_id,
            $account->uuid
        );

        // Process cross-domain transaction
        $result = $this->integrationService->processCrossDomainTransaction(
            $wallet->wallet_id,
            $account->uuid,
            250.00,
            'USD',
            ['description' => 'Test transfer']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(250.00, $result['amount']);
        $this->assertEquals('USD', $result['currency']);

        // Verify balances updated
        $updatedWallet = AgentWallet::find($wallet->id);
        $this->assertEquals(750.00, $updatedWallet->balance);

        $updatedAccount = Account::find($account->id);
        $this->assertEquals(25000, $updatedAccount->balance); // 250.00 * 100 cents

        Event::assertDispatched('App\Domain\AgentProtocol\Events\Integration\CrossDomainTransactionInitiated');
    }

    /**
     * Test wallet balance synchronization.
     */
    public function test_can_synchronize_wallet_balance(): void
    {
        Event::fake();

        // Create linked wallet and account with different balances
        $agent = AgentIdentity::factory()->create();
        $wallet = $this->walletService->createWallet(
            $agent->agent_id,
            'USD',
            1000.00
        );

        $account = Account::factory()->create([
            'balance' => 150000, // 1500.00 in dollars
        ]);

        $this->integrationService->linkAgentWalletToAccount(
            $wallet->wallet_id,
            $account->uuid
        );

        // Sync balance
        $result = $this->integrationService->syncWalletBalance($wallet->wallet_id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['synced']);
        $this->assertEquals(1000.00, $result['old_balance']);
        $this->assertEquals(1500.00, $result['new_balance']);

        // Verify wallet balance updated
        $updatedWallet = AgentWallet::find($wallet->id);
        $this->assertEquals(1500.00, $updatedWallet->balance);

        Event::assertDispatched('App\Domain\AgentProtocol\Events\Integration\WalletBalanceSynchronized');
    }

    /**
     * Test blockchain transaction handling.
     */
    public function test_can_handle_blockchain_transaction(): void
    {
        // Create wallet with blockchain integration
        $agent = AgentIdentity::factory()->create();
        $wallet = AgentWallet::factory()->create([
            'agent_id'           => $agent->agent_id,
            'blockchain_address' => '0x1234567890abcdef',
            'balance'            => 1000.00,
        ]);

        $account = Account::factory()->create([
            'balance' => 100000,
        ]);

        $wallet->linked_account_uuid = $account->uuid;
        $wallet->save();

        // Handle incoming blockchain transaction
        $result = $this->integrationService->handleBlockchainTransaction(
            $wallet->wallet_id,
            '0xtxhash123',
            500.00,
            'incoming'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(500.00, $result['amount']);
        $this->assertEquals('incoming', $result['direction']);
        $this->assertEquals(1500.00, $result['new_balance']);

        // Verify account balance updated
        $updatedAccount = Account::find($account->id);
        $this->assertEquals(150000, $updatedAccount->balance); // Added 500.00 * 100
    }

    /**
     * Test integration status retrieval.
     */
    public function test_can_get_integration_status(): void
    {
        // Create linked wallet
        $agent = AgentIdentity::factory()->create();
        $wallet = $this->walletService->createWallet(
            $agent->agent_id,
            'USD',
            1000.00
        );

        $account = Account::factory()->create([
            'balance' => 100000,
        ]);

        $this->integrationService->linkAgentWalletToAccount(
            $wallet->wallet_id,
            $account->uuid,
            ['enable_blockchain' => true]
        );

        // Get status
        $status = $this->integrationService->getIntegrationStatus($wallet->wallet_id);

        $this->assertEquals($wallet->wallet_id, $status['wallet_id']);
        $this->assertEquals($agent->agent_id, $status['agent_id']);
        $this->assertTrue($status['is_linked']);
        $this->assertEquals($account->uuid, $status['linked_account']);
        $this->assertNotNull($status['linked_at']);
        $this->assertEquals(1000.00, $status['balance']);
        $this->assertTrue($status['balance_in_sync']);
    }

    /**
     * Test transaction with insufficient balance.
     */
    public function test_fails_transaction_with_insufficient_balance(): void
    {
        // Create wallet with low balance
        $agent = AgentIdentity::factory()->create();
        $wallet = $this->walletService->createWallet(
            $agent->agent_id,
            'USD',
            100.00
        );

        $account = Account::factory()->create();

        $this->integrationService->linkAgentWalletToAccount(
            $wallet->wallet_id,
            $account->uuid
        );

        // Try to transfer more than available
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->integrationService->processCrossDomainTransaction(
            $wallet->wallet_id,
            $account->uuid,
            500.00,
            'USD'
        );
    }

    /**
     * Test multiple cross-domain transactions.
     */
    public function test_can_process_multiple_cross_domain_transactions(): void
    {
        // Create multiple wallets and accounts
        $agents = AgentIdentity::factory()->count(3)->create();
        $wallets = [];
        $accounts = [];

        foreach ($agents as $agent) {
            $wallet = $this->walletService->createWallet(
                $agent->agent_id,
                'USD',
                1000.00
            );
            $wallets[] = $wallet;

            $account = Account::factory()->create([
                'balance' => 0,
            ]);
            $accounts[] = $account;

            $this->integrationService->linkAgentWalletToAccount(
                $wallet->wallet_id,
                $account->uuid
            );
        }

        // Process multiple transactions
        $transactions = [];
        foreach ($wallets as $i => $wallet) {
            $result = $this->integrationService->processCrossDomainTransaction(
                $wallet->wallet_id,
                $accounts[$i]->uuid,
                100.00 * ($i + 1),
                'USD'
            );
            $transactions[] = $result;
        }

        // Verify all transactions succeeded
        $this->assertCount(3, $transactions);
        foreach ($transactions as $tx) {
            $this->assertTrue($tx['success']);
        }

        // Verify balances
        for ($i = 0; $i < 3; $i++) {
            $wallet = AgentWallet::find($wallets[$i]->id);
            $expectedWalletBalance = 1000.00 - (100.00 * ($i + 1));
            $this->assertEquals($expectedWalletBalance, $wallet->balance);

            $account = Account::find($accounts[$i]->id);
            $expectedAccountBalance = 100.00 * ($i + 1) * 100; // Convert to cents
            $this->assertEquals($expectedAccountBalance, $account->balance);
        }
    }
}
