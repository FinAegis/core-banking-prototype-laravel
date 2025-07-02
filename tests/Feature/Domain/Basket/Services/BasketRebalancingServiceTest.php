<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Basket\Services;

use App\Domain\Basket\Services\BasketRebalancingService;
use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Domain\Basket\Events\BasketRebalanced;
use App\Domain\Asset\Models\Asset;
use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketValue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class BasketRebalancingServiceTest extends TestCase
{

    private BasketRebalancingService $service;
    private BasketValueCalculationService $valueCalculationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->valueCalculationService = Mockery::mock(BasketValueCalculationService::class);
        $this->service = new BasketRebalancingService($this->valueCalculationService);
        
        Event::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_checks_if_basket_needs_rebalancing()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
        ]);
        
        $this->assertTrue($this->service->needsRebalancing($basket));
        
        $basket->update(['last_rebalanced_at' => now()]);
        $this->assertFalse($this->service->needsRebalancing($basket));
    }

    /** @test */
    public function it_throws_exception_for_fixed_basket_rebalancing()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'fixed',
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only dynamic baskets can be rebalanced');
        
        $this->service->rebalance($basket);
    }

    /** @test */
    public function it_rebalances_basket_with_components_outside_bounds()
    {
        // Use existing assets
        $usd = Asset::where('code', 'USD')->first();
        $eur = Asset::where('code', 'EUR')->first();
        
        // Create dynamic basket with unique code
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);
        
        // Add components with bounds
        $component1 = BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 30.0, // Below min_weight
            'min_weight' => 40.0,
            'max_weight' => 60.0,
            'is_active' => true,
        ]);
        
        $component2 = BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 70.0, // Above max_weight
            'min_weight' => 40.0,
            'max_weight' => 60.0,
            'is_active' => true,
        ]);
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->with($basket)
            ->andReturn($mockValue);
            
        $this->valueCalculationService
            ->shouldReceive('invalidateCache')
            ->with($basket)
            ->zeroOrMoreTimes();
        
        // Perform rebalancing
        $result = $this->service->rebalance($basket);
        
        // Check results
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(2, $result['adjustments_count']);
        
        // Check adjustments
        $usdAdjustment = collect($result['adjustments'])->firstWhere('asset', 'USD');
        $eurAdjustment = collect($result['adjustments'])->firstWhere('asset', 'EUR');
        
        $this->assertEquals(30.0, $usdAdjustment['current_weight']);
        $this->assertEquals(40.0, $usdAdjustment['target_weight']);
        $this->assertEquals('increase', $usdAdjustment['action']);
        
        $this->assertEquals(70.0, $eurAdjustment['current_weight']);
        $this->assertEquals(60.0, $eurAdjustment['target_weight']);
        $this->assertEquals('decrease', $eurAdjustment['action']);
        
        // Check components were updated
        $this->assertEquals(40.0, $component1->fresh()->weight);
        $this->assertEquals(60.0, $component2->fresh()->weight);
        
        // Check event was dispatched
        Event::assertDispatched(BasketRebalanced::class, function ($event) use ($basket) {
            return $event->basketCode === $basket->code && count($event->adjustments) === 2;
        });
        
        // Check basket was updated
        $this->assertNotNull($basket->fresh()->last_rebalanced_at);
    }

    /** @test */
    public function it_skips_rebalancing_when_no_adjustments_needed()
    {
        // Create basket with components within bounds
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 50.0,
            'min_weight' => 40.0,
            'max_weight' => 60.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 50.0,
            'min_weight' => 40.0,
            'max_weight' => 60.0,
            'is_active' => true,
        ]);
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->with($basket)
            ->andReturn($mockValue);
        
        // Perform rebalancing
        $result = $this->service->rebalance($basket);
        
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(0, $result['adjustments_count']);
        $this->assertEmpty($result['adjustments']);
    }

    /** @test */
    public function it_throws_exception_for_zero_value_basket()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        // Mock zero value
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 0.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->with($basket)
            ->andReturn($mockValue);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot rebalance basket with zero or negative value');
        
        $this->service->rebalance($basket);
    }

    /** @test */
    public function it_normalizes_weights_after_rebalancing()
    {
        // Create basket with 3 components
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        Asset::where('code', 'GBP')->first();
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 25.0,
            'min_weight' => 30.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 35.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'GBP',
            'weight' => 35.0,
            'is_active' => true,
        ]);
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andReturn($mockValue);
            
        $this->valueCalculationService
            ->shouldReceive('invalidateCache')
            ->zeroOrMoreTimes();
        
        // Perform rebalancing
        $result = $this->service->rebalance($basket);
        
        // Check total weight is 100%
        $totalWeight = $basket->fresh()->activeComponents->sum('weight');
        $this->assertEqualsWithDelta(100.0, $totalWeight, 0.01);
    }

    /** @test */
    public function it_rebalances_if_needed()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 100.0,
            'is_active' => true,
        ]);
        
        Asset::where('code', 'USD')->first();
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andReturn($mockValue);
            
        $this->valueCalculationService
            ->shouldReceive('invalidateCache')
            ->zeroOrMoreTimes();
        
        $result = $this->service->rebalanceIfNeeded($basket);
        
        $this->assertNotNull($result);
        $this->assertEquals('completed', $result['status']);
    }

    /** @test */
    public function it_returns_null_when_rebalancing_not_needed()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now(),
        ]);
        
        $result = $this->service->rebalanceIfNeeded($basket);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_rebalances_all_baskets_that_need_it()
    {
        // Create baskets
        $basket1 = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
        ]);
        
        $basket2 = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'weekly',
            'last_rebalanced_at' => now()->subWeeks(2),
        ]);
        
        $basket3 = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now(), // Doesn't need rebalancing
        ]);
        
        // Add components
        Asset::where('code', 'USD')->first();
        
        foreach ([$basket1, $basket2] as $basket) {
            BasketComponent::create([
                'basket_asset_id' => $basket->id,
                'asset_code' => 'USD',
                'weight' => 100.0,
                'is_active' => true,
            ]);
        }
        
        // Mock value calculations
        $mockValue = new BasketValue([
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andReturn($mockValue);
            
        $this->valueCalculationService
            ->shouldReceive('invalidateCache')
            ->zeroOrMoreTimes();
        
        // Rebalance all
        $results = $this->service->rebalanceAll();
        
        $this->assertCount(0, $results['rebalanced']); // No adjustments needed
        $this->assertCount(2, $results['no_changes']); // Both baskets processed but no changes
        $this->assertCount(0, $results['failed']);
    }

    /** @test */
    public function it_simulates_rebalancing_without_executing()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        Asset::where('code', 'USD')->first();
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 80.0,
            'max_weight' => 60.0,
            'is_active' => true,
        ]);
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->with($basket)
            ->andReturn($mockValue);
        
        // Simulate rebalancing
        $result = $this->service->simulateRebalancing($basket);
        
        $this->assertEquals('simulated', $result['status']);
        $this->assertTrue($result['simulated']);
        $this->assertTrue($result['needs_rebalancing']);
        $this->assertEquals(1, $result['adjustments_count']);
        
        // Verify component weight wasn't actually changed
        $component = $basket->components()->first();
        $this->assertEquals(80.0, $component->weight);
    }

    /** @test */
    public function it_handles_rebalancing_failures_gracefully()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
        ]);
        
        // Mock value calculation to throw exception
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andThrow(new \Exception('Calculation error'));
        
        Log::shouldReceive('error')
            ->once()
            ->with(
                Mockery::pattern('/Failed to rebalance basket TEST_BASKET_/'),
                Mockery::any()
            );
        
        // Rebalance all
        $results = $this->service->rebalanceAll();
        
        $this->assertCount(0, $results['rebalanced']);
        $this->assertCount(0, $results['no_changes']);
        $this->assertCount(1, $results['failed']);
        $this->assertStringStartsWith('TEST_BASKET_', $results['failed'][0]['basket']);
        $this->assertEquals('Calculation error', $results['failed'][0]['error']);
    }

    /** @test */
    public function it_gets_rebalancing_history_placeholder()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        $history = $this->service->getRebalancingHistory($basket);
        
        $this->assertEquals($basket->code, $history['basket']);
        $this->assertEmpty($history['history']);
        $this->assertStringContainsString('event store integration', $history['message']);
    }

    /** @test */
    public function it_handles_components_without_bounds()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        
        // Components without min/max weights
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 60.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 40.0,
            'is_active' => true,
        ]);
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andReturn($mockValue);
        
        // Rebalance - should have no adjustments
        $result = $this->service->rebalance($basket);
        
        $this->assertEquals(0, $result['adjustments_count']);
    }

    /** @test */
    public function it_normalizes_weights_with_constraints()
    {
        $basket = BasketAsset::factory()->create([
            'code' => 'TEST_BASKET_' . uniqid(),
            'type' => 'dynamic',
        ]);
        
        Asset::where('code', 'USD')->first();
        Asset::where('code', 'EUR')->first();
        Asset::where('code', 'GBP')->first();
        
        // Create components that don't sum to 100%
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 20.0,
            'min_weight' => 15.0,
            'max_weight' => 40.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'EUR',
            'weight' => 25.0,
            'min_weight' => 20.0,
            'max_weight' => 50.0,
            'is_active' => true,
        ]);
        
        BasketComponent::create([
            'basket_asset_id' => $basket->id,
            'asset_code' => 'GBP',
            'weight' => 30.0,
            'min_weight' => 25.0,
            'max_weight' => 40.0,
            'is_active' => true,
        ]);
        
        // Total: 75%, needs to be scaled to 100%
        
        // Mock value calculation
        $mockValue = new BasketValue([
            'basket_asset_id' => $basket->id,
            'value' => 100.0,
            'calculated_at' => now(),
            'component_values' => [],
        ]);
        
        $this->valueCalculationService
            ->shouldReceive('calculateValue')
            ->andReturn($mockValue);
            
        $this->valueCalculationService
            ->shouldReceive('invalidateCache')
            ->zeroOrMoreTimes();
        
        // Rebalance
        $result = $this->service->rebalance($basket);
        
        // Check weights sum to 100%
        $totalWeight = $basket->fresh()->activeComponents->sum('weight');
        $this->assertEqualsWithDelta(100.0, $totalWeight, 0.01);
        
        // Check constraints are respected
        foreach ($basket->fresh()->activeComponents as $component) {
            if ($component->min_weight !== null) {
                $this->assertGreaterThanOrEqual($component->min_weight, $component->weight);
            }
            if ($component->max_weight !== null) {
                $this->assertLessThanOrEqual($component->max_weight, $component->weight);
            }
        }
    }
}
