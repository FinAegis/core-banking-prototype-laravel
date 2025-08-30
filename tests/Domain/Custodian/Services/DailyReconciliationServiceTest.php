<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Events\ReconciliationCompleted;
use App\Domain\Custodian\Events\ReconciliationDiscrepancyFound;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\BalanceSynchronizationService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\Services\DailyReconciliationService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->syncService = Mockery::mock(BalanceSynchronizationService::class);
    $this->custodianRegistry = Mockery::mock(CustodianRegistry::class);
    $this->reconciliationService = new DailyReconciliationService($this->syncService, $this->custodianRegistry);
});

it('performs daily reconciliation successfully', function () {
    Event::fake();

    // Mock sync service
    $this->syncService->shouldReceive('synchronizeAllBalances')
        ->once()
        ->andReturn([
            'synchronized' => 10,
            'failed'       => 0,
            'skipped'      => 2,
        ]);

    // Create test accounts with custodian accounts so no orphaned balances
    $account = Account::factory()->create();
    $account->balances()->create([
        'asset_code' => 'USD',
        'balance'    => 100000, // $1000
    ]);

    // Create custodian account for this account
    CustodianAccount::factory()->create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'test_bank',
        'custodian_account_id' => '123',
        'status'               => 'active',
    ]);

    // Mock custodian connector with proper interface
    $mockConnector = Mockery::mock(App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockConnector->shouldReceive('getAccountInfo')->andReturn(
        new App\Domain\Custodian\ValueObjects\AccountInfo(
            accountId: '123',
            name: 'Test Account',
            status: 'active',
            balances: ['USD' => 100000], // Same balance - no discrepancy
            currency: 'USD',
            type: 'checking',
            createdAt: now()
        )
    );

    $this->custodianRegistry->shouldReceive('getConnector')
        ->andReturn($mockConnector);

    $result = $this->reconciliationService->performDailyReconciliation();

    expect($result)->toBeArray();
    expect($result['summary']['status'])->toBe('completed');

    // We can't control other tests creating accounts with orphaned balances
    // So just check that our specific account has no discrepancies
    $ourAccountDiscrepancies = collect($result['discrepancies'])
        ->where('account_uuid', $account->uuid)
        ->count();
    expect($ourAccountDiscrepancies)->toBe(0);

    Event::assertDispatched(ReconciliationCompleted::class);
});

it('detects balance discrepancies', function () {
    Event::fake();

    $this->syncService->shouldReceive('synchronizeAllBalances')
        ->once()
        ->andReturn(['synchronized' => 10, 'failed' => 0, 'skipped' => 0]);

    // Create account with balance
    $account = Account::factory()->create();
    $account->balances()->create([
        'asset_code' => 'USD',
        'balance'    => 100000, // $1000 internal
    ]);

    // Create custodian account
    $custodianAccount = CustodianAccount::factory()->create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'test_bank',
        'custodian_account_id' => '123',
        'status'               => 'active',
    ]);

    // Mock custodian with different balance
    $mockConnector = Mockery::mock(App\Domain\Custodian\Contracts\ICustodianConnector::class);
    $mockConnector->shouldReceive('isAvailable')->andReturn(true);
    $mockConnector->shouldReceive('getAccountInfo')->andReturn(
        new App\Domain\Custodian\ValueObjects\AccountInfo(
            accountId: '123',
            name: 'Test Account',
            status: 'active',
            balances: ['USD' => 95000], // $950 external - $50 discrepancy
            currency: 'USD',
            type: 'checking',
            createdAt: now()
        )
    );

    $this->custodianRegistry->shouldReceive('getConnector')
        ->with('test_bank')
        ->andReturn($mockConnector);

    $result = $this->reconciliationService->performDailyReconciliation();

    expect($result['summary']['discrepancies_found'])->toBe(1);
    expect($result['summary']['total_discrepancy_amount'])->toBe(5000); // $50
    expect($result['discrepancies'])->toHaveCount(1);
    expect($result['discrepancies'][0]['type'])->toBe('balance_mismatch');
    expect($result['discrepancies'][0]['difference'])->toBe(5000);

    Event::assertDispatched(ReconciliationDiscrepancyFound::class);
    Event::assertDispatched(ReconciliationCompleted::class);
});

it('detects orphaned balances', function () {
    Event::fake();

    $this->syncService->shouldReceive('synchronizeAllBalances')
        ->once()
        ->andReturn(['synchronized' => 0, 'failed' => 0, 'skipped' => 0]);

    // Create account with balance but no custodian accounts
    $account = Account::factory()->create();
    $account->balances()->create([
        'asset_code' => 'USD',
        'balance'    => 50000,
    ]);

    $result = $this->reconciliationService->performDailyReconciliation();

    expect($result['summary']['discrepancies_found'])->toBeGreaterThanOrEqual(1);

    // Find the orphaned balance discrepancy for our account
    $orphanedDiscrepancy = collect($result['discrepancies'])
        ->where('account_uuid', $account->uuid)
        ->where('type', 'orphaned_balance')
        ->first();

    expect($orphanedDiscrepancy)->not->toBeNull();
    expect($orphanedDiscrepancy['type'])->toBe('orphaned_balance');
});

// TODO: Re-enable this test when last_synced_at column is added
// it('detects stale data', function () {
//     Event::fake();
//
//     $this->syncService->shouldReceive('synchronizeAllBalances')
//         ->once()
//         ->andReturn(['synchronized' => 0, 'failed' => 0, 'skipped' => 0]);
//
//     // Create account with old custodian sync
//     $account = Account::factory()->zeroBalance()->create();
//     $custodianAccount = CustodianAccount::factory()->create([
//         'account_uuid' => $account->uuid,
//         'custodian_name' => 'test_bank',
//         'last_synced_at' => now()->subHours(25), // Over 24 hours old
//         'status' => 'active',
//     ]);
//
//     $result = $this->reconciliationService->performDailyReconciliation();
//
//     expect($result['summary']['discrepancies_found'])->toBe(1);
//     expect($result['discrepancies'][0]['type'])->toBe('stale_data');
// });

it('generates recommendations based on findings', function () {
    $this->syncService->shouldReceive('synchronizeAllBalances')
        ->once()
        ->andReturn(['synchronized' => 0, 'failed' => 0, 'skipped' => 0]);

    // Create account with orphaned balance
    $account1 = Account::factory()->create();
    $account1->balances()->create(['asset_code' => 'USD', 'balance' => 100000]);

    $result = $this->reconciliationService->performDailyReconciliation();

    expect($result['recommendations'])->toBeArray();
    expect($result['recommendations'])->toContain('Investigate and resolve balance discrepancies immediately');
});
