<?php

use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\Services\SettlementService;
use App\Domain\Custodian\ValueObjects\TransactionReceipt;
use App\Models\Account;
use App\Models\CustodianAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Configure settlement service
    Config::set('custodians.settlement', [
        'type' => 'net',
        'batch_interval_minutes' => 60,
        'min_settlement_amount' => 10000, // $100.00
    ]);
    
    // Create test accounts
    $this->account1 = Account::factory()->create();
    $this->account2 = Account::factory()->create();
    $this->account3 = Account::factory()->create();
    
    // Create custodian accounts
    $this->payseraAccount1 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account1->uuid,
        'custodian_name' => 'paysera',
        'custodian_account_id' => 'PAYSERA_1',
    ]);
    
    $this->deutscheBankAccount1 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account2->uuid,
        'custodian_name' => 'deutsche_bank',
        'custodian_account_id' => 'DB_1',
    ]);
    
    $this->deutscheBankAccount2 = CustodianAccount::factory()->create([
        'account_uuid' => $this->account3->uuid,
        'custodian_name' => 'deutsche_bank',
        'custodian_account_id' => 'DB_2',
    ]);
    
    // Create default mock registry
    $this->mockPayseraConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $this->mockPayseraConnector->shouldReceive('isAvailable')->andReturn(true);
    $this->mockPayseraConnector->shouldReceive('getName')->andReturn('paysera');
    
    $this->mockDeutscheBankConnector = Mockery::mock(\App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $this->mockDeutscheBankConnector->shouldReceive('isAvailable')->andReturn(true);
    $this->mockDeutscheBankConnector->shouldReceive('getName')->andReturn('deutsche_bank');
    
    $this->mockRegistry = Mockery::mock(CustodianRegistry::class)->makePartial();
    $this->mockRegistry->shouldReceive('get')->with('paysera')->andReturn($this->mockPayseraConnector);
    $this->mockRegistry->shouldReceive('get')->with('deutsche_bank')->andReturn($this->mockDeutscheBankConnector);
    $this->mockRegistry->shouldReceive('getConnector')->with('paysera')->andReturn($this->mockPayseraConnector);
    $this->mockRegistry->shouldReceive('getConnector')->with('deutsche_bank')->andReturn($this->mockDeutscheBankConnector);
    
    app()->instance(CustodianRegistry::class, $this->mockRegistry);
    
    $this->service = app(SettlementService::class);
});

it('can process net settlements', function () {
    
    // Create transfers between custodians that net out
    DB::table('custodian_transfers')->insert([
        // Paysera -> Deutsche Bank: $500
        [
            'id' => 'TRANSFER_1',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->payseraAccount1->id,
            'to_custodian_account_id' => $this->deutscheBankAccount1->id,
            'amount' => 50000,
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        // Deutsche Bank -> Paysera: $300
        [
            'id' => 'TRANSFER_2',
            'from_account_uuid' => $this->account2->uuid,
            'to_account_uuid' => $this->account1->uuid,
            'from_custodian_account_id' => $this->deutscheBankAccount1->id,
            'to_custodian_account_id' => $this->payseraAccount1->id,
            'amount' => 30000,
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    // Add expectation for settlement
    $this->mockPayseraConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'SETTLEMENT_123',
            status: 'completed',
            amount: 20000, // Net amount: $200
            assetCode: 'USD',
            reference: 'NET_',
            createdAt: now()
        ));
    
    $results = $this->service->processPendingSettlements();
    
    expect($results['settlements'])->toBe(1);
    expect($results['total_net'])->toBe(20000); // $200 net
    expect($results['savings'])->toBe(60000); // $600 saved vs gross
    expect($results['savings_percentage'])->toBeGreaterThan(0);
    
    // Check settlement created
    $this->assertDatabaseHas('settlements', [
        'type' => 'net',
        'from_custodian' => 'paysera',
        'to_custodian' => 'deutsche_bank',
        'gross_amount' => 80000, // Total gross: $800
        'net_amount' => 20000, // Net: $200
        'status' => 'completed',
    ]);
    
    // Check transfers linked to settlement
    $this->assertDatabaseHas('custodian_transfers', [
        'id' => 'TRANSFER_1',
        'settlement_id' => DB::table('settlements')->first()->id,
    ]);
});

it('can process batch settlements', function () {
    Config::set('custodians.settlement.type', 'batch');
    
    // Recreate the service to pick up the new config
    $this->service = app()->make(SettlementService::class);
    
    // Create old transfers ready for batch
    DB::table('custodian_transfers')->insert([
        [
            'id' => 'BATCH_1',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->payseraAccount1->id,
            'to_custodian_account_id' => $this->deutscheBankAccount1->id,
            'amount' => 25000,
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now(),
        ],
        [
            'id' => 'BATCH_2',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account3->uuid,
            'from_custodian_account_id' => $this->payseraAccount1->id,
            'to_custodian_account_id' => $this->deutscheBankAccount2->id,
            'amount' => 35000,
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
            'updated_at' => now(),
        ],
    ]);
    
    // Add expectations to the mock connector
    $this->mockPayseraConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andReturn(new TransactionReceipt(
            id: 'BATCH_SETTLEMENT_123',
            status: 'completed',
            amount: 60000,
            assetCode: 'USD',
            reference: 'BATCH_',
            createdAt: now()
        ));
    
    $results = $this->service->processPendingSettlements();
    
    expect($results['batches'])->toBe(1);
    expect($results['transfers'])->toBe(2);
    expect($results['total_amount'])->toBe(60000);
    
    // Check batch settlement created
    $this->assertDatabaseHas('settlements', [
        'type' => 'batch',
        'from_custodian' => 'paysera',
        'to_custodian' => 'deutsche_bank',
        'gross_amount' => 60000,
        'net_amount' => 60000,
        'transfer_count' => 2,
        'status' => 'completed',
    ]);
});

it('respects minimum settlement amount', function () {
    // Create small transfers that don't meet minimum
    DB::table('custodian_transfers')->insert([
        [
            'id' => 'SMALL_1',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->payseraAccount1->id,
            'to_custodian_account_id' => $this->deutscheBankAccount1->id,
            'amount' => 5000, // $50 - below minimum
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    $results = $this->service->processPendingSettlements();
    
    expect($results['settlements'])->toBe(0);
    
    // No settlement should be created
    $this->assertDatabaseCount('settlements', 0);
});

it('can get settlement statistics', function () {
    // Create test settlements
    DB::table('settlements')->insert([
        [
            'id' => 'STAT_1',
            'type' => 'net',
            'from_custodian' => 'paysera',
            'to_custodian' => 'deutsche_bank',
            'asset_code' => 'USD',
            'gross_amount' => 100000,
            'net_amount' => 60000,
            'transfer_count' => 5,
            'status' => 'completed',
            'created_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ],
        [
            'id' => 'STAT_2',
            'type' => 'batch',
            'from_custodian' => 'deutsche_bank',
            'to_custodian' => 'santander',
            'asset_code' => 'EUR',
            'gross_amount' => 50000,
            'net_amount' => 50000,
            'transfer_count' => 3,
            'status' => 'pending',
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    $stats = $this->service->getSettlementStatistics();
    
    expect($stats['total'])->toBe(2);
    expect($stats['completed'])->toBe(1);
    expect($stats['pending'])->toBe(1);
    expect($stats['total_gross_amount'])->toBe(150000);
    expect($stats['total_net_amount'])->toBe(110000);
    expect($stats['total_savings'])->toBe(40000);
    expect($stats['savings_percentage'])->toBeGreaterThan(25);
    expect($stats['total_transfers_settled'])->toBe(8);
    expect($stats['by_type']['net']['count'])->toBe(1);
    expect($stats['by_type']['batch']['count'])->toBe(1);
});

it('handles settlement failures gracefully', function () {
    
    // Create transfer to settle
    DB::table('custodian_transfers')->insert([
        [
            'id' => 'FAIL_1',
            'from_account_uuid' => $this->account1->uuid,
            'to_account_uuid' => $this->account2->uuid,
            'from_custodian_account_id' => $this->payseraAccount1->id,
            'to_custodian_account_id' => $this->deutscheBankAccount1->id,
            'amount' => 50000,
            'asset_code' => 'USD',
            'transfer_type' => 'external',
            'status' => 'completed',
            'reference' => null,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    // Add expectation to throw exception
    $this->mockPayseraConnector->shouldReceive('initiateTransfer')
        ->once()
        ->andThrow(new \Exception('Settlement failed: Insufficient funds'));
    
    $results = $this->service->processPendingSettlements();
    
    expect($results['settlements'])->toBe(0);
    
    // Check settlement marked as failed
    $this->assertDatabaseHas('settlements', [
        'type' => 'net',
        'status' => 'failed',
        'failure_reason' => 'Settlement failed: Insufficient funds',
    ]);
});