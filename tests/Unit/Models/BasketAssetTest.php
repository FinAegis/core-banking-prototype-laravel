<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketValue;
use App\Domain\Asset\Models\Asset;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasketAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test assets
        Asset::firstOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        Asset::firstOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
    }

    /** @test */
    public function it_can_create_a_basket_asset()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'description' => 'A test basket asset',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        $this->assertEquals('TEST_BSK', $basket->code);
        $this->assertEquals('Test Basket', $basket->name);
        $this->assertEquals('fixed', $basket->type);
        $this->assertTrue($basket->is_active);
    }

    /** @test */
    public function it_has_components_relationship()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);

        $this->assertCount(2, $basket->components);
        $this->assertInstanceOf(BasketComponent::class, $basket->components->first());
    }

    /** @test */
    public function it_has_values_relationship()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.05,
            'calculated_at' => now(),
            'component_values' => ['USD' => 0.5, 'EUR' => 0.55],
        ]);

        $this->assertCount(1, $basket->values);
        $this->assertInstanceOf(BasketValue::class, $basket->values->first());
    }

    /** @test */
    public function it_can_check_if_needs_rebalancing()
    {
        $basket = BasketAsset::create([
            'code' => 'DYNAMIC',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        $this->assertTrue($basket->needsRebalancing());

        $basket->update(['last_rebalanced_at' => now()]);
        $this->assertFalse($basket->needsRebalancing());
    }

    /** @test */
    public function it_can_convert_to_asset()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        $asset = $basket->toAsset();

        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertEquals('TEST_BSK', $asset->code);
        $this->assertEquals('Test Basket', $asset->name);
        $this->assertEquals('custom', $asset->type);
        $this->assertTrue($asset->is_basket);
    }

    /** @test */
    public function it_has_active_scope()
    {
        BasketAsset::create([
            'code' => 'ACTIVE',
            'name' => 'Active Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        BasketAsset::create([
            'code' => 'INACTIVE',
            'name' => 'Inactive Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => false,
        ]);

        $activeBaskets = BasketAsset::active()->get();

        $this->assertCount(1, $activeBaskets);
        $this->assertEquals('ACTIVE', $activeBaskets->first()->code);
    }

    /** @test */
    public function it_has_needs_rebalancing_scope()
    {
        BasketAsset::create([
            'code' => 'NEEDS_REB',
            'name' => 'Needs Rebalancing',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
            'is_active' => true,
        ]);

        BasketAsset::create([
            'code' => 'NO_REBAL',
            'name' => 'No Rebalancing',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);

        $needsRebalancing = BasketAsset::query()->needsRebalancing()->get();

        $this->assertCount(1, $needsRebalancing);
        $this->assertEquals('NEEDS_REB', $needsRebalancing->first()->code);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'last_rebalanced_at' => '2025-06-18 12:00:00',
            'is_active' => true,
            'metadata' => ['key' => 'value'],
        ]);

        $fresh = BasketAsset::find($basket->id);

        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->last_rebalanced_at);
        $this->assertIsBool($fresh->is_active);
        $this->assertIsArray($fresh->metadata);
        $this->assertEquals('value', $fresh->metadata['key']);
    }
}