<?php

namespace Tests\Feature\Http\Controllers\Api\V2;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\BankConnectionModel;
use App\Models\BankAccountModel;
use App\Domain\Banking\Contracts\IBankIntegrationService;
use App\Domain\Banking\Contracts\IBankConnector;
use App\Domain\Banking\Models\BankCapabilities;
use Laravel\Sanctum\Sanctum;
use Mockery;

class BankIntegrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $apiPrefix = '/api/v2';
    protected $mockBankService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Mock the bank integration service
        $this->mockBankService = Mockery::mock(IBankIntegrationService::class);
        $this->app->instance(IBankIntegrationService::class, $this->mockBankService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_lists_available_banks()
    {
        Sanctum::actingAs($this->user);
        
        // Create mock connectors
        $capabilities1 = new BankCapabilities(
            supportedCurrencies: ['EUR', 'USD'],
            supportedTransferTypes: ['SEPA', 'SWIFT'],
            features: ['instant_balance', 'transaction_history'],
            limits: [],
            fees: [],
            supportsInstantTransfers: true,
            supportsScheduledTransfers: false,
            supportsBulkTransfers: false,
            supportsDirectDebits: false,
            supportsStandingOrders: false,
            supportsVirtualAccounts: false,
            supportsMultiCurrency: true,
            supportsWebhooks: false,
            supportsStatements: true,
            supportsCardIssuance: false,
            maxAccountsPerUser: 5,
            requiredDocuments: [],
            availableCountries: ['DE', 'FR', 'ES'],
        );
        
        $capabilities2 = new BankCapabilities(
            supportedCurrencies: ['GBP', 'EUR', 'USD'],
            supportedTransferTypes: ['SWIFT', 'FASTER_PAYMENTS'],
            features: ['instant_balance'],
            limits: [],
            fees: [],
            supportsInstantTransfers: false,
            supportsScheduledTransfers: true,
            supportsBulkTransfers: false,
            supportsDirectDebits: true,
            supportsStandingOrders: true,
            supportsVirtualAccounts: false,
            supportsMultiCurrency: true,
            supportsWebhooks: true,
            supportsStatements: true,
            supportsCardIssuance: false,
            maxAccountsPerUser: 10,
            requiredDocuments: [],
            availableCountries: ['GB', 'US'],
        );
        
        $mockConnector1 = Mockery::mock(IBankConnector::class);
        $mockConnector1->shouldReceive('getBankName')->andReturn('Deutsche Bank');
        $mockConnector1->shouldReceive('isAvailable')->andReturn(true);
        $mockConnector1->shouldReceive('getCapabilities')->andReturn($capabilities1);
        
        $mockConnector2 = Mockery::mock(IBankConnector::class);
        $mockConnector2->shouldReceive('getBankName')->andReturn('HSBC');
        $mockConnector2->shouldReceive('isAvailable')->andReturn(true);
        $mockConnector2->shouldReceive('getCapabilities')->andReturn($capabilities2);
        
        $connectors = collect([
            'deutsche_bank' => $mockConnector1,
            'hsbc' => $mockConnector2,
        ]);
        
        $this->mockBankService
            ->shouldReceive('getAvailableConnectors')
            ->once()
            ->andReturn($connectors);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/available");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'code',
                    'name',
                    'available',
                    'supported_currencies',
                    'supported_transfer_types',
                    'features',
                    'supports_instant_transfers',
                    'supports_multi_currency',
                ],
            ],
        ]);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'Deutsche Bank']);
        $response->assertJsonFragment(['name' => 'HSBC']);
    }

    /** @test */
    public function it_gets_user_bank_connections()
    {
        Sanctum::actingAs($this->user);
        
        $connection1 = Mockery::mock();
        $connection1->id = 'conn_1';
        $connection1->bankCode = 'deutsche_bank';
        $connection1->status = 'active';
        $connection1->permissions = ['accounts', 'transactions'];
        $connection1->lastSyncAt = now()->subHours(2);
        $connection1->expiresAt = now()->addDays(60);
        $connection1->createdAt = now()->subDays(30);
        $connection1->shouldReceive('isActive')->andReturn(true);
        $connection1->shouldReceive('needsRenewal')->andReturn(false);
        
        $connection2 = Mockery::mock();
        $connection2->id = 'conn_2';
        $connection2->bankCode = 'hsbc';
        $connection2->status = 'expired';
        $connection2->permissions = ['accounts'];
        $connection2->lastSyncAt = now()->subDays(7);
        $connection2->expiresAt = now()->subDays(1);
        $connection2->createdAt = now()->subDays(90);
        $connection2->shouldReceive('isActive')->andReturn(false);
        $connection2->shouldReceive('needsRenewal')->andReturn(true);
        
        $connections = collect([$connection1, $connection2]);
        
        $this->mockBankService
            ->shouldReceive('getUserBankConnections')
            ->with($this->user)
            ->once()
            ->andReturn($connections);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/connections");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'bank_code',
                    'status',
                    'active',
                    'needs_renewal',
                    'permissions',
                    'last_sync_at',
                    'expires_at',
                    'created_at',
                ],
            ],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_connects_to_a_bank()
    {
        Sanctum::actingAs($this->user);
        
        $connectionData = [
            'bank_code' => 'deutsche_bank',
            'consent_duration_days' => 90,
        ];
        
        $this->mockBankService
            ->shouldReceive('connectBank')
            ->with($this->user->uuid, 'deutsche_bank', Mockery::type('array'))
            ->once()
            ->andReturn([
                'connection_id' => 'conn_123',
                'bank_code' => 'deutsche_bank',
                'bank_name' => 'Deutsche Bank',
                'status' => 'pending',
                'authorization_url' => 'https://deutsche-bank.com/auth?ref=123',
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/banks/connect", $connectionData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'connection_id',
                'bank_code',
                'bank_name',
                'status',
                'authorization_url',
            ],
        ]);
    }

    /** @test */
    public function it_validates_bank_connection_request()
    {
        Sanctum::actingAs($this->user);
        
        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/banks/connect", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bank_code']);
        
        // Invalid consent duration
        $response = $this->postJson("{$this->apiPrefix}/banks/connect", [
            'bank_code' => 'deutsche_bank',
            'consent_duration_days' => 'invalid',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['consent_duration_days']);
    }

    /** @test */
    public function it_disconnects_from_a_bank()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockBankService
            ->shouldReceive('disconnectBank')
            ->with($this->user->uuid, 'deutsche_bank')
            ->once()
            ->andReturn(true);
        
        $response = $this->deleteJson("{$this->apiPrefix}/banks/disconnect/deutsche_bank");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Bank disconnected successfully',
        ]);
    }

    /** @test */
    public function it_gets_bank_accounts()
    {
        Sanctum::actingAs($this->user);
        
        $accounts = collect([
            [
                'id' => 'acc_1',
                'bank_code' => 'deutsche_bank',
                'bank_name' => 'Deutsche Bank',
                'account_name' => 'Main Checking',
                'iban' => 'DE89370400440532013000',
                'currency' => 'EUR',
                'balance' => 150000,
                'available_balance' => 145000,
                'status' => 'active',
            ],
            [
                'id' => 'acc_2',
                'bank_code' => 'hsbc',
                'bank_name' => 'HSBC',
                'account_name' => 'Business Account',
                'iban' => 'GB29NWBK60161331926819',
                'currency' => 'GBP',
                'balance' => 250000,
                'available_balance' => 250000,
                'status' => 'active',
            ],
        ]);
        
        $this->mockBankService
            ->shouldReceive('getBankAccounts')
            ->with($this->user->uuid)
            ->once()
            ->andReturn($accounts);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/accounts");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'bank_code',
                    'bank_name',
                    'account_name',
                    'iban',
                    'currency',
                    'balance',
                    'available_balance',
                    'status',
                ],
            ],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_syncs_bank_accounts()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockBankService
            ->shouldReceive('syncBankAccounts')
            ->with($this->user->uuid, 'deutsche_bank')
            ->once()
            ->andReturn([
                'synced_accounts' => 3,
                'new_accounts' => 1,
                'updated_accounts' => 2,
                'sync_timestamp' => now()->toISOString(),
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/banks/accounts/sync/deutsche_bank");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'synced_accounts',
                'new_accounts',
                'updated_accounts',
                'sync_timestamp',
            ],
        ]);
    }

    /** @test */
    public function it_gets_aggregated_balance()
    {
        Sanctum::actingAs($this->user);
        
        $balanceData = [
            'total_balance_eur' => 375000,
            'balances_by_currency' => [
                ['currency' => 'EUR', 'balance' => 150000],
                ['currency' => 'GBP', 'balance' => 250000],
                ['currency' => 'USD', 'balance' => 50000],
            ],
            'accounts_count' => 5,
            'last_sync' => now()->toISOString(),
        ];
        
        $this->mockBankService
            ->shouldReceive('getAggregatedBalance')
            ->with($this->user->uuid)
            ->once()
            ->andReturn($balanceData);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/balance/aggregate");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_balance_eur',
                'balances_by_currency',
                'accounts_count',
                'last_sync',
            ],
        ]);
    }

    /** @test */
    public function it_initiates_bank_transfer()
    {
        Sanctum::actingAs($this->user);
        
        $transferData = [
            'source_account_id' => 'acc_123',
            'beneficiary_name' => 'John Doe',
            'beneficiary_iban' => 'DE89370400440532013001',
            'amount' => 10000,
            'currency' => 'EUR',
            'reference' => 'Invoice payment #123',
            'transfer_type' => 'SEPA',
        ];
        
        $this->mockBankService
            ->shouldReceive('initiateTransfer')
            ->with($this->user->uuid, Mockery::type('array'))
            ->once()
            ->andReturn([
                'transfer_id' => 'txn_123',
                'status' => 'pending',
                'estimated_arrival' => now()->addDays(1)->toISOString(),
                'fees' => 150,
                'exchange_rate' => null,
            ]);
        
        $response = $this->postJson("{$this->apiPrefix}/banks/transfer", $transferData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'transfer_id',
                'status',
                'estimated_arrival',
                'fees',
            ],
        ]);
    }

    /** @test */
    public function it_validates_transfer_request()
    {
        Sanctum::actingAs($this->user);
        
        // Missing required fields
        $response = $this->postJson("{$this->apiPrefix}/banks/transfer", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'source_account_id',
            'beneficiary_name',
            'beneficiary_iban',
            'amount',
            'currency',
        ]);
        
        // Invalid amount
        $response = $this->postJson("{$this->apiPrefix}/banks/transfer", [
            'source_account_id' => 'acc_123',
            'beneficiary_name' => 'John Doe',
            'beneficiary_iban' => 'DE89370400440532013001',
            'amount' => -100,
            'currency' => 'EUR',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_checks_bank_connector_health()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockBankService
            ->shouldReceive('checkBankHealth')
            ->with('deutsche_bank')
            ->once()
            ->andReturn([
                'status' => 'operational',
                'response_time_ms' => 145,
                'last_check' => now()->toISOString(),
                'issues' => [],
            ]);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/health/deutsche_bank");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'status',
                'response_time_ms',
                'last_check',
                'issues',
            ],
        ]);
    }

    /** @test */
    public function it_gets_recommended_banks()
    {
        Sanctum::actingAs($this->user);
        
        $recommendations = collect([
            [
                'bank_code' => 'revolut',
                'bank_name' => 'Revolut',
                'score' => 95,
                'reasons' => [
                    'Multi-currency support',
                    'Low fees',
                    'Instant transfers',
                ],
            ],
            [
                'bank_code' => 'wise',
                'bank_name' => 'Wise',
                'score' => 90,
                'reasons' => [
                    'Excellent exchange rates',
                    'International transfers',
                ],
            ],
        ]);
        
        $this->mockBankService
            ->shouldReceive('getRecommendedBanks')
            ->with($this->user->uuid, Mockery::type('array'))
            ->once()
            ->andReturn($recommendations);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/recommendations?country=DE&needs[]=multi_currency");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'bank_code',
                    'bank_name',
                    'score',
                    'reasons',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson("{$this->apiPrefix}/banks/available");
        $response->assertStatus(401);
        
        $response = $this->postJson("{$this->apiPrefix}/banks/connect", []);
        $response->assertStatus(401);
        
        $response = $this->getJson("{$this->apiPrefix}/banks/accounts");
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_bank_service_failures()
    {
        Sanctum::actingAs($this->user);
        
        $this->mockBankService
            ->shouldReceive('getAvailableConnectors')
            ->once()
            ->andThrow(new \Exception('Service temporarily unavailable'));
        
        $response = $this->getJson("{$this->apiPrefix}/banks/available");

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Failed to retrieve available banks',
        ]);
    }
}