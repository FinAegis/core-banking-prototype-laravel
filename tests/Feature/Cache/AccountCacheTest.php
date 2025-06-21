<?php

declare(strict_types=1);

use App\Domain\Account\Services\Cache\AccountCacheService;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('caches account data', function () {
    $account = Account::factory()->create();
    $cacheService = app(AccountCacheService::class);
    
    // First call should hit the database
    $cachedAccount = $cacheService->get((string) $account->uuid);
    
    expect($cachedAccount)->toBeInstanceOf(Account::class);
    expect($cachedAccount->uuid)->toBe((string) $account->uuid);
    
    // Manually put the account in cache before deleting to ensure it's properly cached
    $cacheService->put($cachedAccount);
    
    // Delete the account from database
    $account->delete();
    
    // Second call should return from cache
    $cachedAccount2 = $cacheService->get((string) $account->uuid);
    
    expect($cachedAccount2)->toBeInstanceOf(Account::class);
    expect($cachedAccount2->uuid)->toBe((string) $account->uuid);
});

it('caches balance separately with shorter TTL', function () {
    $account = Account::factory()->withBalance(5000)->create();
    $cacheService = app(AccountCacheService::class);
    
    // Cache the balance explicitly first
    $balance = $cacheService->getBalance((string) $account->uuid);
    expect($balance)->toBe(5000);
    
    // Ensure the balance is actually cached by checking the cache directly
    $cacheKey = 'account:' . $account->uuid . ':balance';
    $cachedValue = Cache::get($cacheKey);
    expect($cachedValue)->not->toBeNull();
    
    // Update account balance in database using raw query to avoid any model events
    \DB::table('accounts')
        ->where('uuid', $account->uuid)
        ->update(['balance' => 10000]);
    
    // Also update the USD balance in account_balances table
    \DB::table('account_balances')
        ->where('account_uuid', $account->uuid)
        ->where('asset_code', 'USD')
        ->update(['balance' => 10000]);
    
    // Should still return cached balance since we didn't clear the balance cache
    $cachedBalance = $cacheService->getBalance((string) $account->uuid);
    expect($cachedBalance)->toBe(5000);
    
    // Verify the account in database has new balance
    $dbAccount = Account::find($account->id);
    expect($dbAccount->getBalance('USD'))->toBe(10000);
});

it('updates balance cache', function () {
    $account = Account::factory()->withBalance(5000)->create();
    $cacheService = app(AccountCacheService::class);
    
    // Cache the balance
    $cacheService->getBalance((string) $account->uuid);
    
    // Update balance in cache
    $cacheService->updateBalance((string) $account->uuid, 10000);
    
    // Should return updated balance
    $balance = $cacheService->getBalance((string) $account->uuid);
    
    expect($balance)->toBe(10000);
});

it('forgets cached account', function () {
    $account = Account::factory()->create();
    $cacheService = app(AccountCacheService::class);
    
    // Cache the account
    $cacheService->get((string) $account->uuid);
    
    // Delete from database
    $account->delete();
    
    // Forget from cache
    $cacheService->forget((string) $account->uuid);
    
    // Should return null
    $result = $cacheService->get((string) $account->uuid);
    
    expect($result)->toBeNull();
});

it('returns null for non-existent account', function () {
    $cacheService = app(AccountCacheService::class);
    $fakeUuid = \Illuminate\Support\Str::uuid()->toString();
    
    $result = $cacheService->get($fakeUuid);
    
    expect($result)->toBeNull();
});