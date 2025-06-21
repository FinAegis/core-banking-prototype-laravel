<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Asset\Workflows\OptimizedAssetTransferWorkflow;
use App\Domain\Asset\Workflows\Activities\OptimizedInitiateAssetTransferActivity;
use App\Domain\Performance\Services\TransferOptimizationService;
use App\Models\Account;
use App\Models\AccountBalance;
use Illuminate\Support\Facades\Cache;
use Workflow\WorkflowStub;

beforeEach(function () {
    // Create test accounts with zero balance
    $this->account1 = Account::factory()->zeroBalance()->create();
    $this->account2 = Account::factory()->zeroBalance()->create();
    
    // Create balances
    AccountBalance::create([
        'account_uuid' => $this->account1->uuid,
        'asset_code' => 'USD',
        'balance' => 100000, // $1000
    ]);
    
    AccountBalance::create([
        'account_uuid' => $this->account2->uuid,
        'asset_code' => 'USD',
        'balance' => 50000, // $500
    ]);
    
    $this->optimizationService = app(TransferOptimizationService::class);
});

it('can get account with cache', function () {
    $accountUuid = AccountUuid::fromString((string) $this->account1->uuid);
    
    // First call should hit database
    $account = $this->optimizationService->getAccountWithCache($accountUuid);
    expect($account)->toBeInstanceOf(Account::class);
    expect($account->uuid)->toBe((string) $this->account1->uuid);
    
    // Second call should hit cache
    $cachedAccount = $this->optimizationService->getAccountWithCache($accountUuid);
    expect($cachedAccount)->toBeInstanceOf(Account::class);
    expect((string) $cachedAccount->uuid)->toBe((string) $this->account1->uuid);
});

it('can pre-validate transfer in single query', function () {
    $fromUuid = AccountUuid::fromString((string) $this->account1->uuid);
    $toUuid = AccountUuid::fromString((string) $this->account2->uuid);
    
    $result = $this->optimizationService->preValidateTransfer(
        $fromUuid,
        $toUuid,
        'USD',
        10000 // $100
    );
    
    expect($result)->toHaveKey('from_balance');
    expect($result['from_balance'])->toBe(100000);
    expect($result['validation_passed'])->toBeTrue();
});

it('throws exception for insufficient balance in pre-validation', function () {
    $fromUuid = AccountUuid::fromString((string) $this->account1->uuid);
    $toUuid = AccountUuid::fromString((string) $this->account2->uuid);
    
    expect(fn() => $this->optimizationService->preValidateTransfer(
        $fromUuid,
        $toUuid,
        'USD',
        200000 // $2000 - more than available
    ))->toThrow(\Exception::class, 'Insufficient USD balance');
});

it('can batch validate multiple transfers', function () {
    $transfers = [
        [
            'from_account' => (string) $this->account1->uuid,
            'to_account' => (string) $this->account2->uuid,
            'from_asset' => 'USD',
            'amount' => 10000,
        ],
        [
            'from_account' => (string) $this->account2->uuid,
            'to_account' => (string) $this->account1->uuid,
            'from_asset' => 'USD',
            'amount' => 5000,
        ],
        [
            'from_account' => (string) $this->account1->uuid,
            'to_account' => (string) $this->account2->uuid,
            'from_asset' => 'USD',
            'amount' => 200000, // This should fail
        ],
    ];
    
    $results = $this->optimizationService->batchValidateTransfers($transfers);
    
    expect($results[0]['valid'])->toBeTrue();
    expect($results[1]['valid'])->toBeTrue();
    expect($results[2]['valid'])->toBeFalse();
    expect($results[2]['error'])->toContain('Insufficient balance');
});

it('can warm up caches for accounts', function () {
    Cache::flush();
    
    $this->optimizationService->warmUpCaches([
        (string) $this->account1->uuid,
        (string) $this->account2->uuid,
    ]);
    
    // Check that accounts are cached
    $cacheKey1 = "account:{$this->account1->uuid}";
    $cacheKey2 = "account:{$this->account2->uuid}";
    
    expect(Cache::has($cacheKey1))->toBeTrue();
    expect(Cache::has($cacheKey2))->toBeTrue();
});

it('optimized transfer workflow exists and can be instantiated', function () {
    // Test that the optimized workflow class exists
    expect(class_exists(OptimizedAssetTransferWorkflow::class))->toBeTrue();
    
    // Test that the optimized activity exists
    expect(class_exists(OptimizedInitiateAssetTransferActivity::class))->toBeTrue();
    
    // Test that the optimization service is registered
    $service = app(TransferOptimizationService::class);
    expect($service)->toBeInstanceOf(TransferOptimizationService::class);
});

it('clears transfer caches after successful transfer', function () {
    $accountUuid = (string) $this->account1->uuid;
    $assetCode = 'USD';
    
    // Set some cache values
    Cache::put("account:{$accountUuid}", $this->account1, 300);
    Cache::put("balance:{$accountUuid}:{$assetCode}", 100000, 60);
    
    // Clear caches
    $this->optimizationService->clearTransferCaches($accountUuid, $assetCode);
    
    // Verify caches are cleared
    expect(Cache::has("account:{$accountUuid}"))->toBeFalse();
    expect(Cache::has("balance:{$accountUuid}:{$assetCode}"))->toBeFalse();
});

it('validates frozen accounts in pre-validation', function () {
    // Freeze account
    $this->account1->update(['frozen' => true]);
    
    $fromUuid = AccountUuid::fromString((string) $this->account1->uuid);
    $toUuid = AccountUuid::fromString((string) $this->account2->uuid);
    
    expect(fn() => $this->optimizationService->preValidateTransfer(
        $fromUuid,
        $toUuid,
        'USD',
        1000
    ))->toThrow(\Exception::class, 'Source account is frozen');
});