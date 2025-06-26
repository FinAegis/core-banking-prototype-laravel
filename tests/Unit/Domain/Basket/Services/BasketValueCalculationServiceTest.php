<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Basket\Services;

use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BasketValueCalculationServiceTest extends TestCase
{

    private BasketValueCalculationService $service;
    private ExchangeRateService $exchangeRateService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->exchangeRateService = Mockery::mock(ExchangeRateService::class);
        $this->service = new BasketValueCalculationService($this->exchangeRateService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calculates_basket_value_with_single_component()
    {
        // Create assets
        $usd = Asset::where('code', 'USD')->first();
        $eur = Asset::where('code', 'EUR')->first();
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        // Mock exchange rate
        $mockRate = new ExchangeRate([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.2,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('EUR', 'USD')
            ->andReturn($mockRate);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        $this->assertInstanceOf(BasketValue::class, $value);
        $this->assertEquals(1.2, $value->value);
        $this->assertEquals($basket->code, $value->basket_asset_code);
        $this->assertArrayHasKey('EUR', $value->component_values);
        $this->assertEquals(1.2, $value->component_values['EUR']['weighted_value']);
    }

    /** @test */
    public function it_calculates_basket_value_with_multiple_components()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        Asset::where('code', 'GBP')->first();
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add components
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 60.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'GBP',
            'weight' => 40.0,
            'is_active' => true,
        ]);
        
        // Mock exchange rates
        $mockRateEUR = new ExchangeRate([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.2,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('EUR', 'USD')
            ->andReturn($mockRateEUR);
            
        $mockRateGBP = new ExchangeRate([
            'from_asset_code' => 'GBP',
            'to_asset_code' => 'USD',
            'rate' => 1.5,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('GBP', 'USD')
            ->andReturn($mockRateGBP);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        // EUR: 1.2 * 0.6 = 0.72
        // GBP: 1.5 * 0.4 = 0.60
        // Total: 1.32
        $this->assertEquals(1.32, $value->value);
        $this->assertCount(3, $value->component_values); // EUR, GBP, and _metadata
        $this->assertEqualsWithDelta(0.72, $value->component_values['EUR']['weighted_value'], 0.001);
        $this->assertEqualsWithDelta(0.60, $value->component_values['GBP']['weighted_value'], 0.001);
    }

    /** @test */
    public function it_handles_usd_component_without_exchange_rate()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add USD component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        // Calculate value - no exchange rate needed for USD
        $value = $this->service->calculateValue($basket, false);
        
        $this->assertEquals(1.0, $value->value);
        $this->assertEquals(1.0, $value->component_values['USD']['value']);
    }

    /** @test */
    public function it_creates_empty_value_for_basket_without_components()
    {
        // Create basket without components
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        $this->assertEquals(0.0, $value->value);
        $this->assertArrayHasKey('_metadata', $value->component_values);
        $this->assertContains('No active components', $value->component_values['_metadata']['calculation_errors']);
    }

    /** @test */
    public function it_ignores_inactive_components()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add active component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 50.0,
            'is_active' => true,
        ]);
        
        // Add inactive component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 50.0,
            'is_active' => false,
        ]);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        // Only USD component should be calculated
        $this->assertEquals(0.5, $value->value); // 1.0 * 0.5
        $this->assertArrayHasKey('USD', $value->component_values);
        $this->assertArrayNotHasKey('EUR', $value->component_values);
    }

    /** @test */
    public function it_handles_missing_exchange_rate_gracefully()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        Asset::factory()->create(['code' => 'XYZ', 'name' => 'Unknown Currency']);
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add components
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 50.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'XYZ',
            'weight' => 50.0,
            'is_active' => true,
        ]);
        
        // Mock exchange rates
        $mockRateEUR = new ExchangeRate([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.2,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('EUR', 'USD')
            ->andReturn($mockRateEUR);
            
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('XYZ', 'USD')
            ->andReturn(null);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        // Only EUR component should contribute
        $this->assertEquals(0.6, $value->value); // 1.2 * 0.5
        $this->assertArrayHasKey('EUR', $value->component_values);
        $this->assertArrayNotHasKey('XYZ', $value->component_values);
        $this->assertNotEmpty($value->component_values['_metadata']['calculation_errors']);
    }

    /** @test */
    public function it_uses_cache_when_enabled()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Add component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        // Calculate value with cache
        $value1 = $this->service->calculateValue($basket, true);
        
        // Verify cache was set
        $cachedValue = Cache::get("basket_value:{$basket->code}");
        $this->assertNotNull($cachedValue);
        $this->assertEquals($value1->id, $cachedValue->id);
        
        // Calculate again - should use cache
        $value2 = $this->service->calculateValue($basket, true);
        $this->assertEquals($value1->id, $value2->id);
    }

    /** @test */
    public function it_calculates_all_basket_values()
    {
        // Create assets
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        
        // Create baskets
        $basket1 = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
            'is_active' => true,
        ]);
        
        $basket2 = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
            'is_active' => true,
        ]);
        
        $inactiveBasket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
            'is_active' => false,
        ]);
        
        // Add components
        BasketComponent::create([
            'basket_asset_id' => $basket1->id,
            'asset_code' => 'USD',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket2->id,
            'asset_code' => 'EUR',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        // Mock exchange rate
        $mockRate = new ExchangeRate([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.2,
            'source' => ExchangeRate::SOURCE_API,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        
        $this->exchangeRateService
            ->shouldReceive('getRate')
            ->with('EUR', 'USD')
            ->andReturn($mockRate);
        
        // Calculate all values
        $results = $this->service->calculateAllBasketValues();
        
        $this->assertCount(2, $results['successful']);
        $this->assertCount(0, $results['failed']);
        
        // Check results
        $basket1Result = collect($results['successful'])->firstWhere('basket', $basket1->code);
        $basket2Result = collect($results['successful'])->firstWhere('basket', $basket2->code);
        
        $this->assertEquals(1.0, $basket1Result['value']);
        $this->assertEquals(1.2, $basket2Result['value']);
    }

    /** @test */
    public function it_gets_historical_values()
    {
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Create historical values
        $value1 = BasketValue::factory()->create([
            'basket_asset_code' => $basket->code,
            'value' => 1.0,
            'calculated_at' => now()->subDays(5),
        ]);
        
        $value2 = BasketValue::factory()->create([
            'basket_asset_code' => $basket->code,
            'value' => 1.1,
            'calculated_at' => now()->subDays(3),
        ]);
        
        $value3 = BasketValue::factory()->create([
            'basket_asset_code' => $basket->code,
            'value' => 1.2,
            'calculated_at' => now()->subDay(),
        ]);
        
        // Get historical values
        $values = $this->service->getHistoricalValues(
            $basket,
            now()->subDays(4),
            now()
        );
        
        // Should only include values within date range
        $this->assertCount(2, $values);
        $this->assertEquals(1.1, $values[0]['value']);
        $this->assertEquals(1.2, $values[1]['value']);
    }

    /** @test */
    public function it_calculates_performance_metrics()
    {
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Create values
        BasketValue::factory()->create([
            'basket_asset_code' => $basket->code,
            'value' => 100.0,
            'calculated_at' => now()->subDays(30),
        ]);
        
        BasketValue::factory()->create([
            'basket_asset_code' => $basket->code,
            'value' => 110.0,
            'calculated_at' => now(),
        ]);
        
        // Calculate performance
        $performance = $this->service->calculatePerformance(
            $basket,
            now()->subDays(31),
            now()->addDay()
        );
        
        $this->assertEquals(100.0, $performance['start_value']);
        $this->assertEquals(110.0, $performance['end_value']);
        $this->assertEquals(10.0, $performance['absolute_change']);
        $this->assertEquals(10.0, $performance['percentage_change']);
        $this->assertEquals(30, $performance['days']);
    }

    /** @test */
    public function it_handles_insufficient_data_for_performance()
    {
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Calculate performance with no data
        $performance = $this->service->calculatePerformance(
            $basket,
            now()->subDays(30),
            now()
        );
        
        $this->assertNull($performance['start_value']);
        $this->assertNull($performance['end_value']);
        $this->assertEquals(0, $performance['absolute_change']);
        $this->assertEquals(0, $performance['percentage_change']);
        $this->assertArrayHasKey('error', $performance);
    }

    /** @test */
    public function it_invalidates_cache()
    {
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Set cache
        $cacheKey = "basket_value:{$basket->code}";
        Cache::put($cacheKey, 'test_value', 300);
        
        // Verify cache exists
        $this->assertEquals('test_value', Cache::get($cacheKey));
        
        // Invalidate cache
        $this->service->invalidateCache($basket);
        
        // Verify cache is cleared
        $this->assertNull(Cache::get($cacheKey));
    }

    /** @test */
    public function it_handles_component_without_asset_gracefully()
    {
        // Create basket
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Create a fake asset for testing missing asset handling
        Asset::create([
            'code' => 'MISSING',
            'name' => 'Missing Asset',
            'type' => 'custom',
            'precision' => 2,
            'is_active' => false, // Inactive to simulate "missing"
        ]);
        
        // Add component with inactive asset
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'MISSING',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        $this->assertEquals(0.0, $value->value);
        $this->assertNotEmpty($value->component_values['_metadata']['calculation_errors']);
        $this->assertStringContainsString('MISSING', $value->component_values['_metadata']['calculation_errors'][0]['asset']);
    }

    /** @test */
    public function it_creates_basket_asset_if_not_exists()
    {
        // Create basket without corresponding asset
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        // Verify asset doesn't exist yet
        $this->assertNull(Asset::find($basket->code));
        
        // Add component
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        Asset::where('code', 'USD')->first();
        
        // Calculate value
        $value = $this->service->calculateValue($basket, false);
        
        // Verify value was created
        $this->assertNotNull($value);
        $this->assertEquals($basket->code, $value->basket_asset_code);
    }
}
