<?php

declare(strict_types=1);

use App\Domain\Account\Services\Cache\TurnoverCacheService;
use App\Models\Account;
use App\Models\Turnover;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('caches latest turnover', function () {
    $account = Account::factory()->create();
    $turnover = Turnover::factory()->create([
        'account_uuid' => $account->uuid,
        'debit' => 1000,
        'credit' => 2000,
    ]);
    
    $cacheService = app(TurnoverCacheService::class);
    
    // First call should hit the database
    $cachedTurnover = $cacheService->getLatest((string) $account->uuid);
    
    expect($cachedTurnover)->toBeInstanceOf(Turnover::class);
    expect($cachedTurnover->id)->toBe($turnover->id);
    
    // Delete from database
    $turnover->delete();
    
    // Should still return from cache
    $cachedTurnover2 = $cacheService->getLatest((string) $account->uuid);
    
    expect($cachedTurnover2)->toBeInstanceOf(Turnover::class);
    expect($cachedTurnover2->id)->toBe($turnover->id);
});

it('caches turnover statistics', function () {
    $account = Account::factory()->create();
    
    // Create multiple turnovers
    Turnover::factory()->count(3)->create([
        'account_uuid' => $account->uuid,
        'debit' => 1000,
        'credit' => 2000,
    ]);
    
    $cacheService = app(TurnoverCacheService::class);
    
    $statistics = $cacheService->getStatistics((string) $account->uuid);
    
    expect($statistics)->toBeArray();
    expect($statistics['total_debit'])->toEqual(3000);
    expect($statistics['total_credit'])->toEqual(6000);
    expect($statistics['average_monthly_debit'])->toEqual(1000);
    expect($statistics['average_monthly_credit'])->toEqual(2000);
    expect($statistics['months_analyzed'])->toEqual(3);
});

it('invalidates turnover cache', function () {
    $account = Account::factory()->create();
    $turnover = Turnover::factory()->create([
        'account_uuid' => $account->uuid,
    ]);
    
    $cacheService = app(TurnoverCacheService::class);
    
    // Cache the data
    $cacheService->getLatest((string) $account->uuid);
    $cacheService->getStatistics((string) $account->uuid);
    
    // Delete from database
    $turnover->delete();
    
    // Invalidate cache
    $cacheService->forget((string) $account->uuid);
    
    // Should return null for latest
    $result = $cacheService->getLatest((string) $account->uuid);
    
    expect($result)->toBeNull();
});

it('returns empty statistics for account without turnovers', function () {
    $account = Account::factory()->create();
    $cacheService = app(TurnoverCacheService::class);
    
    $statistics = $cacheService->getStatistics((string) $account->uuid);
    
    expect($statistics['total_debit'])->toBe(0);
    expect($statistics['total_credit'])->toBe(0);
    expect($statistics['average_monthly_debit'])->toBe(0);
    expect($statistics['average_monthly_credit'])->toBe(0);
    expect($statistics['months_analyzed'])->toBe(0);
});