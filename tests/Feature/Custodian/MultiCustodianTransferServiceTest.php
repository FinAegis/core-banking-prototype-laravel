<?php

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\Services\MultiCustodianTransferService;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Models\Account;
use App\Models\CustodianAccount;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create test accounts
    $this->account1 = Account::factory()->create();
    $this->account2 = Account::factory()->create();
    
    // Create custodian accounts
    $this->custodianAccount1 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account1->uuid,
        'custodian_name' => 'paysera',
        'custodian_account_id' => 'PAYSERA_ACC_1',
        'status' => 'active',
        'is_primary' => true,
    ]);
    
    $this->custodianAccount2 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account2->uuid,
        'custodian_name' => 'paysera',
        'custodian_account_id' => 'PAYSERA_ACC_2',
        'status' => 'active',
        'is_primary' => true,
    ]);
    
    // Create default mock registry with paysera connector
    $this->mockConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $this->mockConnector->shouldReceive('isAvailable')->andReturn(true);
    $this->mockConnector->shouldReceive('getName')->andReturn('paysera');
    
    $this->mockRegistry = Mockery::mock(CustodianRegistry::class)->makePartial();
    $this->mockRegistry->shouldReceive('get')->with('paysera')->andReturn($this->mockConnector);
    $this->mockRegistry->shouldReceive('getConnector')->with('paysera')->andReturn($this->mockConnector);
    
    app()->instance(CustodianRegistry::class, $this->mockRegistry);
    
    $this->service = app(MultiCustodianTransferService::class);
});

it('can transfer between accounts on the same custodian', function () {
    // Add expectation to the default mock
    $this->mockConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'TRANSFER_123',
            status: 'completed',
            amount: 10000,
            assetCode: 'EUR',
            reference: 'REF123',
            createdAt: now()
        ));
    
    $receipt = $this->service->transfer(
        $this->account1,
        $this->account2,
        new Money(10000),
        'EUR',
        'REF123'
    );
    
    expect($receipt)->toBeInstanceOf(TransactionReceipt::class);
    expect($receipt->status)->toBe('completed');
    expect($receipt->amount)->toBe(10000);
    
    // Check database record
    $this->assertDatabaseHas('custodian_transfers', [
        'from_account_uuid' => $this->account1->uuid,
        'to_account_uuid' => $this->account2->uuid,
        'amount' => 10000,
        'asset_code' => 'EUR',
        'transfer_type' => 'internal',
    ]);
});

it('can route transfers between different custodians', function () {
    // Create account on different custodian
    $custodianAccount3 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account2->uuid,
        'custodian_name' => 'deutsche_bank',
        'custodian_account_id' => 'DB_ACC_2',
        'status' => 'active',
        'is_primary' => false,
    ]);
    
    // Remove paysera account from account2 to force external transfer
    $this->custodianAccount2->delete();
    
    // Mock connector with external transfer capability
    $mockPayseraConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockPayseraConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockPayseraConnector->shouldReceive('getName')->andReturn('paysera');
    $mockPayseraConnector->shouldReceive('getInfo')
        ->andReturn([
            'features' => ['external_transfers' => true],
            'supported_destinations' => ['deutsche_bank'],
            'supported_assets' => ['EUR'],
        ]);
    $mockPayseraConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'EXT_TRANSFER_123',
            status: 'pending',
            amount: 10000,
            assetCode: 'EUR',
            reference: 'REF123',
            createdAt: now()
        ));
    
    $mockDeutscheBankConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockDeutscheBankConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockDeutscheBankConnector->shouldReceive('getName')->andReturn('deutsche_bank');
    
    $mockRegistry = Mockery::mock(CustodianRegistry::class);
    $mockRegistry->shouldReceive('get')->with('paysera')->andReturn($mockPayseraConnector);
    $mockRegistry->shouldReceive('get')->with('deutsche_bank')->andReturn($mockDeutscheBankConnector);
    $mockRegistry->shouldReceive('getConnector')->with('paysera')->andReturn($mockPayseraConnector);
    $mockRegistry->shouldReceive('getConnector')->with('deutsche_bank')->andReturn($mockDeutscheBankConnector);
    $mockRegistry->shouldReceive('listCustodians')
        ->andReturn([
            ['id' => 'paysera', 'name' => 'Paysera'],
            ['id' => 'deutsche_bank', 'name' => 'Deutsche Bank'],
        ]);
    
    app()->instance(CustodianRegistry::class, $mockRegistry);
    
    // Re-instantiate the service with the new registry
    $this->service = app(MultiCustodianTransferService::class);
    
    $receipt = $this->service->transfer(
        $this->account1,
        $this->account2,
        new Money(10000),
        'EUR',
        'REF123'
    );
    
    expect($receipt)->toBeInstanceOf(TransactionReceipt::class);
    expect($receipt->status)->toBe('pending');
    
    // Check database record
    $this->assertDatabaseHas('custodian_transfers', [
        'from_account_uuid' => $this->account1->uuid,
        'to_account_uuid' => $this->account2->uuid,
        'amount' => 10000,
        'asset_code' => 'EUR',
        'transfer_type' => 'external',
    ]);
});

it('can perform bridge transfers through intermediate custodian', function () {
    // Create accounts that require bridge
    $custodianAccount3 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account2->uuid,
        'custodian_name' => 'santander',
        'custodian_account_id' => 'SANTANDER_ACC_2',
        'status' => 'active',
        'is_primary' => false,
    ]);
    
    // Remove direct connection
    $this->custodianAccount2->delete();
    
    // Mock connectors with bridge capability
    $mockPayseraConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockPayseraConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockPayseraConnector->shouldReceive('getName')->andReturn('paysera');
    $mockPayseraConnector->shouldReceive('getInfo')
        ->andReturn([
            'features' => ['external_transfers' => true],
            'supported_destinations' => ['deutsche_bank'], // Can't send to santander directly
            'supported_assets' => ['EUR'],
        ]);
    $mockPayseraConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'BRIDGE_LEG1_123',
            status: 'completed',
            amount: 10000,
            assetCode: 'EUR',
            reference: 'REF123_LEG1',
            createdAt: now()
        ));
    $mockPayseraConnector->shouldReceive('getTransactionStatus')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'BRIDGE_LEG1_123',
            status: 'completed',
            amount: 10000,
            assetCode: 'EUR',
            reference: 'REF123_LEG1',
            createdAt: now(),
            completedAt: now()
        ));
    
    $mockDeutscheBankConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockDeutscheBankConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockDeutscheBankConnector->shouldReceive('getName')->andReturn('deutsche_bank');
    $mockDeutscheBankConnector->shouldReceive('getInfo')
        ->andReturn([
            'features' => ['external_transfers' => true],
            'supported_destinations' => ['santander'], // Can send to santander
            'supported_assets' => ['EUR'],
        ]);
    $mockDeutscheBankConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'BRIDGE_LEG2_123',
            status: 'pending',
            amount: 10000,
            assetCode: 'EUR',
            reference: 'REF123_LEG2',
            createdAt: now()
        ));
    
    // Need to add santander connector too
    $mockSantanderConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockSantanderConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockSantanderConnector->shouldReceive('getName')->andReturn('santander');
    
    $mockRegistry = Mockery::mock(CustodianRegistry::class);
    $mockRegistry->shouldReceive('get')->with('paysera')->andReturn($mockPayseraConnector);
    $mockRegistry->shouldReceive('get')->with('deutsche_bank')->andReturn($mockDeutscheBankConnector);
    $mockRegistry->shouldReceive('get')->with('santander')->andReturn($mockSantanderConnector);
    $mockRegistry->shouldReceive('getConnector')->with('paysera')->andReturn($mockPayseraConnector);
    $mockRegistry->shouldReceive('getConnector')->with('deutsche_bank')->andReturn($mockDeutscheBankConnector);
    $mockRegistry->shouldReceive('getConnector')->with('santander')->andReturn($mockSantanderConnector);
    $mockRegistry->shouldReceive('listCustodians')
        ->andReturn([
            ['id' => 'paysera', 'name' => 'Paysera'],
            ['id' => 'deutsche_bank', 'name' => 'Deutsche Bank'],
            ['id' => 'santander', 'name' => 'Santander'],
        ]);
    
    app()->instance(CustodianRegistry::class, $mockRegistry);
    
    // Re-instantiate the service with the new registry
    $this->service = app(MultiCustodianTransferService::class);
    
    $receipt = $this->service->transfer(
        $this->account1,
        $this->account2,
        new Money(10000),
        'EUR',
        'REF123'
    );
    
    expect($receipt)->toBeInstanceOf(TransactionReceipt::class);
    expect($receipt->id)->toContain('BRIDGE_');
    expect($receipt->metadata['type'])->toBe('bridge');
    
    // Check database record
    $this->assertDatabaseHas('custodian_transfers', [
        'from_account_uuid' => $this->account1->uuid,
        'to_account_uuid' => $this->account2->uuid,
        'amount' => 10000,
        'asset_code' => 'EUR',
        'transfer_type' => 'bridge',
    ]);
});

it('throws exception when no valid route is found', function () {
    // Create isolated accounts
    CustodianAccount::query()->delete(); // Remove all custodian accounts
    
    $this->service->transfer(
        $this->account1,
        $this->account2,
        new Money(10000),
        'EUR'
    );
})->throws(\RuntimeException::class, 'No valid transfer route found');

it('can get transfer statistics', function () {
    // Create some test transfers
    DB::table('custodian_transfers')->insert([
        [
            'id' => 'TEST_1',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->custodianAccount1->id,
            'to_custodian_account_id' => $this->custodianAccount2->id,
            'amount' => 10000,
            'asset_code' => 'USD',
            'transfer_type' => 'internal',
            'status' => 'completed',
            'reference' => null,
            'created_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(3),
            'updated_at' => now(),
        ],
        [
            'id' => 'TEST_2',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->custodianAccount1->id,
            'to_custodian_account_id' => $this->custodianAccount2->id,
            'amount' => 20000,
            'asset_code' => 'EUR',
            'transfer_type' => 'external',
            'status' => 'pending',
            'reference' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    $stats = $this->service->getTransferStatistics();
    
    expect($stats['total'])->toBe(2);
    expect($stats['completed'])->toBe(1);
    expect($stats['pending'])->toBe(1);
    expect($stats['by_type']['internal'])->toBe(1);
    expect($stats['by_type']['external'])->toBe(1);
    expect($stats['success_rate'])->toBe(50.0);
});