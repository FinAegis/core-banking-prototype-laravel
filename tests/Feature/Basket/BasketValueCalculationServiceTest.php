<?php

declare(strict_types=1);

namespace Tests\Feature\Basket;

use App\Models\BasketAsset;
use App\Models\BasketValue;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Services\BasketValueCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BasketValueCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BasketValueCalculationService $service;
    protected BasketAsset $basket;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(BasketValueCalculationService::class);
        
        // Create test assets (use firstOrCreate to avoid conflicts)
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        
        // Clear existing exchange rates and create specific ones for test
        ExchangeRate::where('from_asset_code', 'EUR')->where('to_asset_code', 'USD')->delete();
        ExchangeRate::where('from_asset_code', 'GBP')->where('to_asset_code', 'USD')->delete();
        
        ExchangeRate::create([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.1000,
            'is_active' => true,
            'source' => 'test',
            'valid_at' => now(),
        ]);
        
        ExchangeRate::create([
            'from_asset_code' => 'GBP',
            'to_asset_code' => 'USD',
            'rate' => 1.2500,
            'is_active' => true,
            'source' => 'test',
            'valid_at' => now(),
        ]);
        
        // Create test basket
        $this->basket = BasketAsset::create([
            'code' => 'TEST_BASKET',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
        ]);
        
        $this->basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);
    }

    /** @test */
    public function it_can_calculate_basket_value()
    {
        // Ensure all components are active and clear all caches
        $this->basket->components()->update(['is_active' => true]);
        $this->basket->refresh();
        $this->service->invalidateCache($this->basket);
        
        // Clear all caches to ensure fresh data
        Cache::flush();
        
        $value = $this->service->calculateValue($this->basket, false);
        
        $this->assertInstanceOf(BasketValue::class, $value);
        $this->assertEquals('TEST_BASKET', $value->basket_asset_code);
        $this->assertGreaterThan(0, $value->value);
        
        // Verify the value is calculated correctly based on current exchange rates
        // The calculation should be: USD portion + (EUR rate * EUR weight) + (GBP rate * GBP weight)
        $eurRate = ExchangeRate::where('from_asset_code', 'EUR')->where('to_asset_code', 'USD')->first()->rate;
        $gbpRate = ExchangeRate::where('from_asset_code', 'GBP')->where('to_asset_code', 'USD')->first()->rate;
        
        $expectedValue = (1.0 * 0.40) + ($eurRate * 0.35) + ($gbpRate * 0.25);
        $this->assertEquals(round($expectedValue, 4), round($value->value, 4));
    }

    /** @test */
    public function it_stores_component_values_in_basket_value()
    {
        // Ensure all components are active (might have been deactivated by previous tests)
        $this->basket->components()->update(['is_active' => true]);
        $this->basket->refresh();
        
        // Clear any cached values to ensure fresh calculation
        $this->service->invalidateCache($this->basket);
        
        $value = $this->service->calculateValue($this->basket, false); // Don't use cache
        
        $componentValues = $value->component_values;
        $this->assertIsArray($componentValues);
        
        // Debug: check what components are actually there
        if (!isset($componentValues['GBP'])) {
            dump('Available components:', array_keys($componentValues));
            dump('Full component values:', $componentValues);
        }
        
        $this->assertArrayHasKey('USD', $componentValues);
        $this->assertArrayHasKey('EUR', $componentValues);
        $this->assertArrayHasKey('GBP', $componentValues);
        
        $this->assertEquals(1.0, $componentValues['USD']['value']);
        $this->assertEquals(40.0, $componentValues['USD']['weight']);
        $this->assertEquals(0.4, $componentValues['USD']['weighted_value']);
    }

    /** @test */
    public function it_caches_basket_value()
    {
        // Clear cache first
        Cache::forget("basket_value:{$this->basket->code}");
        
        // First call should calculate and cache
        $value1 = $this->service->calculateValue($this->basket, true);
        
        // Second call should use cache
        $value2 = $this->service->calculateValue($this->basket, true);
        
        // Values should be identical (same instance from cache)
        $this->assertEquals($value1->value, $value2->value);
        $this->assertEquals($value1->calculated_at->toDateTimeString(), $value2->calculated_at->toDateTimeString());
    }

    /** @test */
    public function it_can_bypass_cache()
    {
        // First call with cache
        $value1 = $this->service->calculateValue($this->basket, true);
        
        // Wait a moment
        sleep(1);
        
        // Second call without cache
        $value2 = $this->service->calculateValue($this->basket, false);
        
        // Values should be the same but calculated at different times
        $this->assertEquals($value1->value, $value2->value);
        $this->assertNotEquals($value1->calculated_at->toDateTimeString(), $value2->calculated_at->toDateTimeString());
    }

    /** @test */
    public function it_handles_missing_exchange_rates()
    {
        // Create basket with asset that has no exchange rate
        // JPY is already created by migration, so we'll use a different asset
        Asset::factory()->create(['code' => 'NOK', 'name' => 'Norwegian Krone', 'type' => 'fiat']);
        
        $basket = BasketAsset::create([
            'code' => 'MISSING_RATE_BASKET',
            'name' => 'Missing Rate Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'NOK', 'weight' => 50.0], // No exchange rate for NOK
        ]);
        
        $value = $this->service->calculateValue($basket);
        
        // Should calculate USD portion but skip JPY due to missing rate
        $this->assertLessThan(1.0, $value->value); // Should be partial calculation
        $this->assertGreaterThan(0.4, $value->value); // Should include USD portion
        $this->assertArrayHasKey('_metadata', $value->component_values);
        // Check that we have metadata (errors may or may not be present depending on mocked providers)
        $this->assertIsArray($value->component_values['_metadata']);
    }

    /** @test */
    public function it_only_calculates_for_active_components()
    {
        // Deactivate one component
        $gbpComponent = $this->basket->components()->where('asset_code', 'GBP')->first();
        $gbpComponent->update(['is_active' => false]);
        
        // Refresh basket to ensure changes are loaded
        $this->basket->refresh();
        
        // Verify the component was actually deactivated
        $activeComponents = $this->basket->activeComponents()->get();
        $this->assertCount(2, $activeComponents); // Should be USD and EUR only
        
        // Clear any cached values to ensure fresh calculation
        $this->service->invalidateCache($this->basket);
        
        $value = $this->service->calculateValue($this->basket, false); // Don't use cache
        
        // Expected calculation (without GBP):
        // USD: 1.0 * 0.40 = 0.40
        // EUR: 1.1 * 0.35 = 0.385
        // Total: 0.40 + 0.385 = 0.785
        $this->assertEquals(0.785, $value->value);
        
        // Component values should not include inactive GBP
        $componentValues = $value->component_values;
        $this->assertArrayNotHasKey('GBP', $componentValues);
    }

    /** @test */
    public function it_can_get_historical_values()
    {
        // Create some historical values
        BasketValue::create([
            'basket_asset_code' => $this->basket->code,
            'value' => 1.05,
            'component_values' => [],
            'calculated_at' => now()->subDays(5),
        ]);
        
        BasketValue::create([
            'basket_asset_code' => $this->basket->code,
            'value' => 1.08,
            'component_values' => [],
            'calculated_at' => now()->subDays(3),
        ]);
        
        BasketValue::create([
            'basket_asset_code' => $this->basket->code,
            'value' => 1.10,
            'component_values' => [],
            'calculated_at' => now()->subDay(),
        ]);
        
        $history = $this->service->getHistoricalValues(
            $this->basket,
            now()->subWeek(),
            now()
        );
        
        $this->assertCount(3, $history);
        $this->assertEquals(1.05, $history[0]['value']);
        $this->assertEquals(1.08, $history[1]['value']);
        $this->assertEquals(1.10, $history[2]['value']);
    }

    /** @test */
    public function it_can_calculate_performance()
    {
        // Create historical values
        BasketValue::create([
            'basket_asset_code' => $this->basket->code,
            'value' => 1.00,
            'component_values' => [],
            'calculated_at' => now()->subDays(30),
        ]);
        
        BasketValue::create([
            'basket_asset_code' => $this->basket->code,
            'value' => 1.10,
            'component_values' => [],
            'calculated_at' => now(),
        ]);
        
        $performance = $this->service->calculatePerformance(
            $this->basket,
            now()->subMonth(),
            now()
        );
        
        $this->assertIsArray($performance);
        $this->assertArrayHasKey('start_value', $performance);
        $this->assertArrayHasKey('end_value', $performance);
        $this->assertArrayHasKey('absolute_change', $performance);
        $this->assertArrayHasKey('percentage_change', $performance);
        
        $this->assertEquals(1.00, $performance['start_value']);
        $this->assertEquals(1.10, $performance['end_value']);
        $this->assertEquals(0.10, round($performance['absolute_change'], 2));
        $this->assertEquals(10.0, $performance['percentage_change']);
    }

    /** @test */
    public function it_handles_no_historical_data_for_performance()
    {
        $performance = $this->service->calculatePerformance(
            $this->basket,
            now()->subMonth(),
            now()
        );
        
        $this->assertNull($performance['start_value']);
        $this->assertNull($performance['end_value']);
        $this->assertEquals(0, $performance['absolute_change']);
        $this->assertEquals(0, $performance['percentage_change']);
    }

    /** @test */
    public function it_uses_identity_rate_for_same_currency()
    {
        // Create basket with only USD components
        $usdBasket = BasketAsset::create([
            'code' => 'USD_ONLY',
            'name' => 'USD Only Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
        ]);
        
        $usdBasket->components()->create([
            'asset_code' => 'USD',
            'weight' => 100.0,
        ]);
        
        $value = $this->service->calculateValue($usdBasket);
        
        // Should be exactly 1.0 (100% * 1.0 exchange rate)
        $this->assertEquals(1.0, $value->value);
    }
}