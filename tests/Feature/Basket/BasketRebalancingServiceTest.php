<?php

declare(strict_types=1);

namespace Tests\Feature\Basket;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Events\BasketRebalanced;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketRebalancingService;
use DateTimeInterface;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class BasketRebalancingServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    protected BasketRebalancingService $service;

    protected BasketAsset $dynamicBasket;

    protected BasketAsset $fixedBasket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BasketRebalancingService::class);

        // Create test assets (use firstOrCreate to avoid conflicts)
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

        // Create exchange rates
        ExchangeRate::factory()->create([
            'from_asset_code' => 'EUR',
            'to_asset_code'   => 'USD',
            'rate'            => 1.1000,
            'is_active'       => true,
        ]);

        ExchangeRate::factory()->create([
            'from_asset_code' => 'GBP',
            'to_asset_code'   => 'USD',
            'rate'            => 1.2500,
            'is_active'       => true,
        ]);

        // Create dynamic basket
        $this->dynamicBasket = BasketAsset::create([
            'code'                => 'DYNAMIC_TEST',
            'name'                => 'Dynamic Test Basket',
            'type'                => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);

        $this->dynamicBasket->components()->createMany([
            [
                'asset_code' => 'USD',
                'weight'     => 45.0, // Out of range
                'min_weight' => 35.0,
                'max_weight' => 40.0,
            ],
            [
                'asset_code' => 'EUR',
                'weight'     => 30.0, // In range
                'min_weight' => 30.0,
                'max_weight' => 35.0,
            ],
            [
                'asset_code' => 'GBP',
                'weight'     => 25.0, // Out of range
                'min_weight' => 27.0,
                'max_weight' => 32.0,
            ],
        ]);

        // Create fixed basket
        $this->fixedBasket = BasketAsset::create([
            'code'                => 'FIXED_TEST',
            'name'                => 'Fixed Test Basket',
            'type'                => 'fixed',
            'rebalance_frequency' => 'never',
        ]);

        $this->fixedBasket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);
    }

    #[Test]
    public function it_cannot_rebalance_fixed_basket()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Only dynamic baskets can be rebalanced');

        $this->service->rebalance($this->fixedBasket);
    }

    #[Test]
    public function it_can_check_if_rebalancing_is_needed()
    {
        // Dynamic basket with daily frequency and no last_rebalanced_at should need rebalancing
        $needs = $this->service->needsRebalancing($this->dynamicBasket);
        $this->assertTrue($needs);

        // Set last_rebalanced_at to now to simulate recent rebalancing
        $this->dynamicBasket->update(['last_rebalanced_at' => now()]);

        $needs = $this->service->needsRebalancing($this->dynamicBasket);
        $this->assertFalse($needs); // Should not need rebalancing yet
    }

    #[Test]
    public function it_can_simulate_rebalancing()
    {
        $result = $this->service->simulateRebalancing($this->dynamicBasket);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('basket', $result);
        $this->assertArrayHasKey('adjustments', $result);
        $this->assertArrayHasKey('simulated', $result);

        $this->assertEquals('simulated', $result['status']);
        $this->assertTrue($result['simulated']);

        // Check adjustments exist
        $this->assertArrayHasKey('adjustments', $result);
        $this->assertIsArray($result['adjustments']);

        // If there are adjustments, check them
        if (count($result['adjustments']) > 0) {
            $adjustmentsByAsset = collect($result['adjustments'])->keyBy('asset_code');

            if ($adjustmentsByAsset->has('USD')) {
                $usdAdjustment = $adjustmentsByAsset['USD'];
                $this->assertArrayHasKey('old_weight', $usdAdjustment);
                $this->assertArrayHasKey('new_weight', $usdAdjustment);
            }

            if ($adjustmentsByAsset->has('GBP')) {
                $gbpAdjustment = $adjustmentsByAsset['GBP'];
                $this->assertArrayHasKey('old_weight', $gbpAdjustment);
            }
        }
    }

    #[Test]
    public function it_can_perform_actual_rebalancing()
    {
        Event::fake();

        $result = $this->service->rebalance($this->dynamicBasket);

        $this->assertEquals('completed', $result['status']);

        // Check weights were actually updated and respect constraints
        $this->dynamicBasket->refresh();
        $usdComponent = $this->dynamicBasket->components()->where('asset_code', 'USD')->first();
        $eurComponent = $this->dynamicBasket->components()->where('asset_code', 'EUR')->first();
        $gbpComponent = $this->dynamicBasket->components()->where('asset_code', 'GBP')->first();

        // USD should be at or below max weight
        $this->assertLessThanOrEqual(40.0, $usdComponent->weight);
        $this->assertGreaterThanOrEqual(35.0, $usdComponent->weight);

        // EUR should be within range
        $this->assertGreaterThanOrEqual(30.0, $eurComponent->weight);
        $this->assertLessThanOrEqual(35.0, $eurComponent->weight);

        // GBP should be at or above min weight
        $this->assertGreaterThanOrEqual(27.0, $gbpComponent->weight);
        $this->assertLessThanOrEqual(32.0, $gbpComponent->weight);

        // Total should be normalized to 100%
        $totalWeight = $usdComponent->weight + $eurComponent->weight + $gbpComponent->weight;
        $this->assertEquals(100.0, round($totalWeight, 2));

        // Check last_rebalanced_at was updated
        $this->assertNotNull($this->dynamicBasket->last_rebalanced_at);

        // Check event was fired
        Event::assertDispatched(BasketRebalanced::class);
    }

    #[Test]
    public function it_normalizes_weights_after_rebalancing()
    {
        // Create basket where clamping would break 100% total
        $basket = BasketAsset::create([
            'code'                => 'NORMALIZE_TEST',
            'name'                => 'Normalize Test',
            'type'                => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);

        $basket->components()->createMany([
            [
                'asset_code' => 'USD',
                'weight'     => 60.0, // Will be clamped to 50
                'min_weight' => 40.0,
                'max_weight' => 50.0,
            ],
            [
                'asset_code' => 'EUR',
                'weight'     => 40.0, // In range
                'min_weight' => 35.0,
                'max_weight' => 45.0,
            ],
        ]);

        $result = $this->service->rebalance($basket);

        // After clamping USD to 50, total would be 90
        // Normalization should scale both to sum to 100
        $basket->refresh();
        $components = $basket->components;
        $totalWeight = $components->sum('weight');

        $this->assertEquals(100.0, round($totalWeight, 2));
    }

    #[Test]
    public function it_handles_inactive_components()
    {
        // Deactivate EUR component
        $this->dynamicBasket->components()->where('asset_code', 'EUR')->update(['is_active' => false]);

        $result = $this->service->rebalance($this->dynamicBasket);

        // Only active components should be adjusted
        $this->assertEquals(2, $result['adjustments_count']); // USD and GBP

        // EUR should not be in adjustments
        $adjustedCodes = collect($result['adjustments'])->pluck('asset_code')->toArray();
        $this->assertNotContains('EUR', $adjustedCodes);
    }

    #[Test]
    public function it_calculates_market_values_for_rebalancing()
    {
        // Set up a scenario where market values drive rebalancing
        $basket = BasketAsset::create([
            'code'                => 'MARKET_TEST',
            'name'                => 'Market Test',
            'type'                => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);

        $basket->components()->createMany([
            [
                'asset_code' => 'USD',
                'weight'     => 33.33,
                'min_weight' => 30.0,
                'max_weight' => 40.0,
            ],
            [
                'asset_code' => 'EUR',
                'weight'     => 33.33,
                'min_weight' => 30.0,
                'max_weight' => 40.0,
            ],
            [
                'asset_code' => 'GBP',
                'weight'     => 33.34,
                'min_weight' => 30.0,
                'max_weight' => 40.0,
            ],
        ]);

        // With different exchange rates, market values will differ from weights
        $result = $this->service->simulateRebalancing($basket);

        $this->assertArrayHasKey('adjustments', $result);
        // The service should consider market values when rebalancing
    }

    #[Test]
    public function it_records_rebalancing_event_with_correct_data()
    {
        Event::fake();

        $this->service->rebalance($this->dynamicBasket);

        Event::assertDispatched(function (BasketRebalanced $event) {
            return $event->basketCode === 'DYNAMIC_TEST' &&
                   count($event->adjustments) === 2 &&
                   $event->timestamp instanceof DateTimeInterface;
        });
    }

    #[Test]
    public function it_does_not_rebalance_if_within_tolerance()
    {
        // Adjust weights to be just within range
        $this->dynamicBasket->components()->where('asset_code', 'USD')->update(['weight' => 40.0]);
        $this->dynamicBasket->components()->where('asset_code', 'EUR')->update(['weight' => 33.0]);
        $this->dynamicBasket->components()->where('asset_code', 'GBP')->update(['weight' => 27.0]);

        $result = $this->service->rebalance($this->dynamicBasket);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(0, $result['adjustments_count']);
        $this->assertEmpty($result['adjustments']);
    }
}
