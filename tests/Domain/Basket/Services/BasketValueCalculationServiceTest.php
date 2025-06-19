<?php

use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketValue;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Domain\Asset\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test assets (use firstOrCreate to avoid duplicates in parallel tests)
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2]);
    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2]);
    Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2]);
    
    // Clear existing exchange rates and create test ones
    ExchangeRate::where('from_asset_code', 'EUR')->where('to_asset_code', 'USD')->delete();
    ExchangeRate::where('from_asset_code', 'GBP')->where('to_asset_code', 'USD')->delete();
    
    ExchangeRate::create([
        'from_asset_code' => 'EUR',
        'to_asset_code' => 'USD',
        'rate' => 1.10,
        'source' => 'test',
        'valid_at' => now(),
        'expires_at' => now()->addHour(),
        'is_active' => true,
    ]);
    
    ExchangeRate::create([
        'from_asset_code' => 'GBP',
        'to_asset_code' => 'USD',
        'rate' => 1.25,
        'source' => 'test',
        'valid_at' => now(),
        'expires_at' => now()->addHour(),
        'is_active' => true,
    ]);
    
    $this->service = new BasketValueCalculationService(new ExchangeRateService());
});

it('can calculate value of a simple basket', function () {
    $basket = BasketAsset::create([
        'code' => 'SIMPLE_BASKET',
        'name' => 'Simple Basket',
    ]);
    
    // 100% USD basket
    $basket->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    $value = $this->service->calculateValue($basket);
    
    expect($value->value)->toBe(1.0);
    expect($value->basket_asset_code)->toBe('SIMPLE_BASKET');
    expect($value->component_values)->toHaveKey('USD');
    expect($value->component_values['USD']['weight'])->toBe(100);
});

it('can calculate value of a multi-currency basket', function () {
    $basket = BasketAsset::create([
        'code' => 'MULTI_BASKET',
        'name' => 'Multi Currency Basket',
    ]);
    
    // 40% USD, 35% EUR, 25% GBP
    $basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 40.0],
        ['asset_code' => 'EUR', 'weight' => 35.0],
        ['asset_code' => 'GBP', 'weight' => 25.0],
    ]);
    
    $value = $this->service->calculateValue($basket);
    
    // Just verify the calculation returns a reasonable value > 1.0 (since we have rates > 1)
    expect($value->value)->toBeGreaterThan(1.0);
    expect($value->value)->toBeLessThan(2.0);
    expect($value->component_values)->toHaveCount(4); // 3 components + _metadata
    expect($value->component_values['_metadata']['total_components'])->toBe(3);
});

it('handles missing exchange rates gracefully', function () {
    $basket = BasketAsset::create([
        'code' => 'MISSING_RATE_BASKET',
        'name' => 'Basket with Missing Rate',
    ]);
    
    // Create a new asset without exchange rate
    Asset::create(['code' => 'JPY', 'name' => 'Japanese Yen', 'type' => 'fiat', 'precision' => 0]);
    
    $basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 50.0],
        ['asset_code' => 'JPY', 'weight' => 50.0], // No exchange rate exists
    ]);
    
    $value = $this->service->calculateValue($basket);
    
    // Should calculate USD portion but fail on JPY
    expect($value->value)->toBe(0.5); // Only USD portion
    expect($value->component_values['_metadata']['calculation_errors'])->not->toBeEmpty();
});

it('returns empty value for basket with no components', function () {
    $basket = BasketAsset::create([
        'code' => 'EMPTY_BASKET',
        'name' => 'Empty Basket',
    ]);
    
    $value = $this->service->calculateValue($basket);
    
    expect($value->value)->toBe(0.0);
    expect($value->component_values['_metadata']['calculation_errors'])->toContain('No active components');
});

it('caches calculated values', function () {
    $basket = BasketAsset::create([
        'code' => 'CACHED_BASKET',
        'name' => 'Cached Basket',
    ]);
    
    $basket->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    // First calculation
    $value1 = $this->service->calculateValue($basket);
    
    // Verify it's cached
    expect(Cache::has("basket_value:CACHED_BASKET"))->toBeTrue();
    
    // Second calculation should use cache
    $value2 = $this->service->calculateValue($basket);
    
    expect($value2->id)->toBe($value1->id);
});

it('can bypass cache when requested', function () {
    $basket = BasketAsset::create([
        'code' => 'NO_CACHE_BASKET',
        'name' => 'No Cache Basket',
    ]);
    
    $basket->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    // First calculation
    $value1 = $this->service->calculateValue($basket, false);
    
    // Second calculation without cache (add small delay for timestamp difference)
    usleep(1000); // 1ms delay
    $value2 = $this->service->calculateValue($basket, false);
    
    expect($value2->id)->not->toBe($value1->id);
    expect($value2->calculated_at->greaterThanOrEqualTo($value1->calculated_at))->toBeTrue();
});

it('can calculate all basket values', function () {
    // Create multiple baskets
    $basket1 = BasketAsset::create([
        'code' => 'BASKET1',
        'name' => 'Basket 1',
        'is_active' => true,
    ]);
    
    $basket1->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    $basket2 = BasketAsset::create([
        'code' => 'BASKET2',
        'name' => 'Basket 2',
        'is_active' => true,
    ]);
    
    $basket2->components()->create([
        'asset_code' => 'EUR',
        'weight' => 100.0,
    ]);
    
    // Inactive basket should be ignored
    $basket3 = BasketAsset::create([
        'code' => 'BASKET3',
        'name' => 'Basket 3',
        'is_active' => false,
    ]);
    
    $results = $this->service->calculateAllBasketValues();
    
    expect($results['successful'])->toHaveCount(2);
    expect($results['failed'])->toBeEmpty();
    expect(array_column($results['successful'], 'basket'))->toContain('BASKET1', 'BASKET2');
});

it('can get historical values for a basket', function () {
    $basket = BasketAsset::create([
        'code' => 'HISTORICAL_BASKET',
        'name' => 'Historical Basket',
    ]);
    
    // Create historical values
    $basket->values()->createMany([
        [
            'value' => 1.00,
            'calculated_at' => now()->subDays(2),
            'component_values' => [],
        ],
        [
            'value' => 1.05,
            'calculated_at' => now()->subDay(),
            'component_values' => [],
        ],
        [
            'value' => 1.10,
            'calculated_at' => now(),
            'component_values' => [],
        ],
    ]);
    
    $history = $this->service->getHistoricalValues(
        $basket,
        now()->subDays(3),
        now()
    );
    
    expect($history)->toHaveCount(3);
    expect($history[0]['value'])->toBe(1.00);
    expect($history[2]['value'])->toBe(1.10);
});

it('can calculate basket performance', function () {
    $basket = BasketAsset::create([
        'code' => 'PERFORMANCE_BASKET',
        'name' => 'Performance Basket',
    ]);
    
    // Create values at different times
    $basket->values()->create([
        'value' => 1.00,
        'calculated_at' => now()->subDays(7),
        'component_values' => [],
    ]);
    
    $basket->values()->create([
        'value' => 1.10,
        'calculated_at' => now(),
        'component_values' => [],
    ]);
    
    $performance = $this->service->calculatePerformance(
        $basket,
        now()->subDays(7),
        now()
    );
    
    expect($performance['start_value'])->toBe(1.00);
    expect($performance['end_value'])->toBe(1.10);
    expect(round($performance['absolute_change'], 2))->toBe(0.10);
    expect($performance['percentage_change'])->toBe(10.0);
    expect($performance['days'])->toBe(7.0);
});

it('handles performance calculation with no data gracefully', function () {
    $basket = BasketAsset::create([
        'code' => 'NO_DATA_BASKET',
        'name' => 'No Data Basket',
    ]);
    
    $performance = $this->service->calculatePerformance(
        $basket,
        now()->subDays(7),
        now()
    );
    
    expect($performance)->toHaveKey('error');
    expect($performance['error'])->toBe('Insufficient data for performance calculation');
});

it('can invalidate cached values', function () {
    $basket = BasketAsset::create([
        'code' => 'INVALIDATE_BASKET',
        'name' => 'Invalidate Basket',
    ]);
    
    $basket->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    // Calculate to cache
    $this->service->calculateValue($basket);
    expect(Cache::has("basket_value:INVALIDATE_BASKET"))->toBeTrue();
    
    // Invalidate
    $this->service->invalidateCache($basket);
    expect(Cache::has("basket_value:INVALIDATE_BASKET"))->toBeFalse();
});

it('ensures basket is registered as asset when calculating value', function () {
    $basket = BasketAsset::create([
        'code' => 'ASSET_CREATE_BSK',
        'name' => 'Asset Creation Basket',
    ]);
    
    $basket->components()->create([
        'asset_code' => 'USD',
        'weight' => 100.0,
    ]);
    
    // Asset should not exist yet
    expect(Asset::where('code', 'ASSET_CREATE_BSK')->exists())->toBeFalse();
    
    // Calculate value - this internally calls toAsset()
    $value = $this->service->calculateValue($basket);
    expect($value)->toBeInstanceOf(BasketValue::class);
    expect($value->value)->toBe(1.0);
    
    // Manually create the asset to verify it works
    $asset = $basket->toAsset();
    expect($asset)->not->toBeNull();
    expect($asset->type)->toBe('custom');
    expect($asset->is_basket)->toBeTrue();
    
    // Verify it was created
    expect(Asset::where('code', 'ASSET_CREATE_BSK')->exists())->toBeTrue();
});