<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Domain\Account\Models\Account;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\AgentProtocol\Services\AgentPaymentIntegrationService;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for Agent Payment Integration with Main Payment System.
 */
class AgentPaymentIntegrationTest extends TestCase
{
    protected User $user;

    private Account $mainAccount;

    private AgentWallet $agentWallet;

    private string $agentDid;

    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if SQLite transaction nesting not supported (PHP 8.4+ issue)
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Skipping: SQLite transaction nesting not fully supported in test environment');
        }

        $this->user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_level'  => 'enhanced',
        ]);

        // Create main account
        $this->mainAccount = Account::create([
            'name'      => 'Test Main Account',
            'user_uuid' => $this->user->uuid ?? Str::uuid()->toString(),
            'balance'   => 500000, // $5,000 in cents
            'frozen'    => false,
        ]);

        $this->agentDid = 'did:key:integration_test_' . Str::random(32);

        // Create agent identity
        AgentIdentity::create([
            'agent_id' => $this->agentDid,
            'did'      => $this->agentDid,
            'name'     => 'Integration Test Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
            'metadata' => [
                'linked_user_id'      => $this->user->id,
                'linked_main_account' => $this->mainAccount->uuid,
            ],
        ]);

        // Create agent wallet
        $this->agentWallet = AgentWallet::create([
            'wallet_id'         => 'wallet_integration_' . Str::uuid()->toString(),
            'agent_id'          => $this->agentDid,
            'currency'          => 'USD',
            'available_balance' => 1000.00,
            'held_balance'      => 0.00,
            'total_balance'     => 1000.00,
            'is_active'         => true,
            'metadata'          => [
                'linked_main_account' => $this->mainAccount->uuid,
            ],
        ]);
    }

    /** @test */
    public function it_creates_integration_service(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->assertInstanceOf(AgentPaymentIntegrationService::class, $service);
    }

    /** @test */
    public function it_links_agent_wallet_to_main_account(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $result = $service->linkMainAccount(
            $this->agentWallet->wallet_id,
            $this->mainAccount->uuid
        );

        $this->assertTrue($result);

        // Verify the link was saved
        $this->agentWallet->refresh();
        $this->assertEquals(
            $this->mainAccount->uuid,
            $this->agentWallet->metadata['linked_main_account']
        );
    }

    /** @test */
    public function it_gets_linked_main_account(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $linkedAccount = $service->getLinkedMainAccount($this->agentDid);

        $this->assertNotNull($linkedAccount);
        $this->assertEquals($this->mainAccount->uuid, $linkedAccount->uuid);
    }

    /** @test */
    public function it_returns_null_for_unlinked_agent(): void
    {
        // Create agent without linked account
        $unlinkedDid = 'did:key:unlinked_' . Str::random(32);
        AgentIdentity::create([
            'agent_id' => $unlinkedDid,
            'did'      => $unlinkedDid,
            'name'     => 'Unlinked Agent',
            'type'     => 'autonomous',
            'status'   => 'active',
            'metadata' => [],
        ]);

        AgentWallet::create([
            'wallet_id'         => 'wallet_unlinked_' . Str::uuid()->toString(),
            'agent_id'          => $unlinkedDid,
            'currency'          => 'USD',
            'available_balance' => 0,
            'held_balance'      => 0,
            'total_balance'     => 0,
            'is_active'         => true,
            'metadata'          => [], // No linked account
        ]);

        $service = app(AgentPaymentIntegrationService::class);
        $linkedAccount = $service->getLinkedMainAccount($unlinkedDid);

        $this->assertNull($linkedAccount);
    }

    /** @test */
    public function it_gets_integration_transaction_history(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $history = $service->getIntegrationTransactionHistory($this->agentDid);

        $this->assertIsArray($history);
        // Initially empty
        $this->assertEmpty($history);
    }

    /** @test */
    public function it_validates_amount_greater_than_zero_for_funding(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $service->fundAgentWallet(
            $this->agentWallet->wallet_id,
            $this->mainAccount->uuid,
            0, // Invalid amount
            'USD'
        );
    }

    /** @test */
    public function it_validates_amount_greater_than_zero_for_withdrawal(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $service->withdrawToMainAccount(
            $this->agentWallet->wallet_id,
            $this->mainAccount->uuid,
            -50, // Invalid amount
            'USD'
        );
    }

    /** @test */
    public function it_validates_sufficient_balance_for_withdrawal(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $service->withdrawToMainAccount(
            $this->agentWallet->wallet_id,
            $this->mainAccount->uuid,
            10000, // More than available balance
            'USD'
        );
    }

    /** @test */
    public function it_validates_agent_wallet_exists(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->fundAgentWallet(
            'nonexistent_wallet',
            $this->mainAccount->uuid,
            100,
            'USD'
        );
    }

    /** @test */
    public function it_validates_main_account_exists(): void
    {
        $service = app(AgentPaymentIntegrationService::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->fundAgentWallet(
            $this->agentWallet->wallet_id,
            'nonexistent_uuid',
            100,
            'USD'
        );
    }
}
