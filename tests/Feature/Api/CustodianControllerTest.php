<?php

declare(strict_types=1);

use App\Domain\Custodian\Connectors\MockBankConnector;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Models\Account;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    
    // Register mock custodian
    $registry = app(CustodianRegistry::class);
    $mockConnector = new MockBankConnector([
        'name' => 'Mock Bank',
        'base_url' => 'https://mock.bank',
    ]);
    $registry->register('mock', $mockConnector);
});

it('can list available custodians', function () {
    $response = $this->getJson('/api/custodians');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'name',
                    'display_name',
                    'available',
                    'supported_assets',
                ],
            ],
            'default',
        ])
        ->assertJsonPath('data.0.name', 'mock')
        ->assertJsonPath('data.0.display_name', 'Mock Bank')
        ->assertJsonPath('data.0.available', true)
        ->assertJsonPath('data.0.supported_assets', ['USD', 'EUR', 'GBP', 'BTC', 'ETH']);
});

it('can get custodian account info', function () {
    $response = $this->getJson('/api/custodians/mock/account-info?' . http_build_query([
        'account_id' => 'mock-account-1',
    ]));
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'account_id',
                'name',
                'status',
                'balances',
                'currency',
                'type',
                'created_at',
                'metadata',
            ],
        ])
        ->assertJsonPath('data.account_id', 'mock-account-1')
        ->assertJsonPath('data.name', 'Mock Business Account')
        ->assertJsonPath('data.status', 'active');
});

it('can get custodian account balance', function () {
    $response = $this->getJson('/api/custodians/mock/balance?' . http_build_query([
        'account_id' => 'mock-account-1',
        'asset_code' => 'USD',
    ]));
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'account_id',
                'asset_code',
                'balance',
                'formatted_balance',
            ],
        ])
        ->assertJsonPath('data.account_id', 'mock-account-1')
        ->assertJsonPath('data.asset_code', 'USD')
        ->assertJsonPath('data.balance', 1000000)
        ->assertJsonPath('data.formatted_balance', '10,000.00');
});

it('can transfer funds from custodian to internal account', function () {
    \Workflow\WorkflowStub::fake();
    $account = Account::factory()->create(['user_uuid' => $this->user->uuid]);
    
    $response = $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => $account->uuid,
        'custodian_account_id' => 'mock-account-1',
        'asset_code' => 'USD',
        'amount' => 100.00,
        'direction' => 'deposit',
        'reference' => 'TEST-DEPOSIT-123',
    ]);
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'status',
                'transaction_id',
                'direction',
                'amount',
                'asset_code',
            ],
            'message',
        ])
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.direction', 'deposit')
        ->assertJsonPath('data.amount', 10000)
        ->assertJsonPath('data.asset_code', 'USD')
        ->assertJsonPath('message', 'Transfer deposit initiated successfully');
});

it('can transfer funds from internal account to custodian', function () {
    \Workflow\WorkflowStub::fake();
    
    // Create a unique user for this test to avoid conflicts
    $testUser = User::factory()->create();
    Sanctum::actingAs($testUser);
    
    $account = Account::factory()->withBalance(50000)->create(['user_uuid' => $testUser->uuid]);
    
    $response = $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => $account->uuid,
        'custodian_account_id' => 'mock-account-2',
        'asset_code' => 'USD',
        'amount' => 250.00,
        'direction' => 'withdraw',
        'reference' => 'TEST-WITHDRAW-456',
    ]);
    
    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.direction', 'withdraw')
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonPath('message', 'Transfer withdraw initiated successfully');
});

it('validates transfer request', function () {
    $response = $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => 'invalid-uuid',
        'custodian_account_id' => 'mock-account-1',
        'asset_code' => 'INVALID',
        'amount' => -100,
        'direction' => 'invalid',
    ]);
    
    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'internal_account_uuid',
            'asset_code',
            'amount',
            'direction',
        ]);
});

it('can get transaction history', function () {
    $response = $this->getJson('/api/custodians/mock/transactions?' . http_build_query([
        'account_id' => 'mock-account-1',
        'limit' => 50,
        'offset' => 0,
    ]));
    
    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'limit',
                'offset',
                'count',
            ],
        ])
        ->assertJsonPath('meta.limit', 50)
        ->assertJsonPath('meta.offset', 0);
});

it('can get transaction status', function () {
    // First create a transaction
    $registry = app(CustodianRegistry::class);
    $custodian = $registry->get('mock');
    
    $request = \App\Domain\Custodian\ValueObjects\TransferRequest::create(
        'mock-account-1',
        'mock-account-2',
        'USD',
        10000
    );
    
    $receipt = $custodian->initiateTransfer($request);
    
    // Get transaction status
    $response = $this->getJson("/api/custodians/mock/transactions/{$receipt->id}");
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'from_account',
                'to_account',
                'asset_code',
                'amount',
            ],
        ])
        ->assertJsonPath('data.id', $receipt->id)
        ->assertJsonPath('data.status', 'completed');
});

it('returns error for invalid custodian', function () {
    $response = $this->getJson('/api/custodians/invalid/account-info?' . http_build_query([
        'account_id' => 'mock-account-1',
    ]));
    
    $response->assertBadRequest()
        ->assertJsonPath('error', 'Failed to retrieve account information')
        ->assertJsonPath('message', "Custodian 'invalid' not found");
});

it('returns error for invalid custodian account in transfer', function () {
    $account = Account::factory()->create(['user_uuid' => $this->user->uuid]);
    
    $response = $this->postJson('/api/custodians/mock/transfer', [
        'internal_account_uuid' => $account->uuid,
        'custodian_account_id' => 'invalid-account',
        'asset_code' => 'USD',
        'amount' => 100.00,
        'direction' => 'deposit',
    ]);
    
    $response->assertBadRequest()
        ->assertJsonPath('error', 'Invalid custodian account');
});