<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Domain\Asset\Models\Asset;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasketComponentTest extends TestCase
{

    protected BasketAsset $basket;

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

        $this->basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_a_basket_component()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 60.0,
            'min_weight' => 50.0,
            'max_weight' => 70.0,
            'is_active' => true,
        ]);

        $this->assertEquals('USD', $component->asset_code);
        $this->assertEquals(60.0, $component->weight);
        $this->assertEquals(50.0, $component->min_weight);
        $this->assertEquals(70.0, $component->max_weight);
        $this->assertTrue($component->is_active);
    }

    /** @test */
    public function it_belongs_to_basket()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 50.0,
        ]);

        $this->assertInstanceOf(BasketAsset::class, $component->basket);
        $this->assertEquals('TEST_BSK', $component->basket->code);
    }

    /** @test */
    public function it_belongs_to_asset()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 50.0,
        ]);

        $this->assertInstanceOf(Asset::class, $component->asset);
        $this->assertEquals('USD', $component->asset->code);
    }

    /** @test */
    public function it_can_check_if_within_bounds()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 60.0,
            'min_weight' => 50.0,
            'max_weight' => 70.0,
        ]);

        $this->assertTrue($component->isWithinBounds(60.0));
        $this->assertTrue($component->isWithinBounds(50.0));
        $this->assertTrue($component->isWithinBounds(70.0));
        $this->assertFalse($component->isWithinBounds(49.0));
        $this->assertFalse($component->isWithinBounds(71.0));
    }

    /** @test */
    public function it_has_active_scope()
    {
        $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 50.0,
            'is_active' => true,
        ]);

        $this->basket->components()->create([
            'asset_code' => 'EUR',
            'weight' => 50.0,
            'is_active' => false,
        ]);

        $activeComponents = $this->basket->components()->active()->get();

        $this->assertCount(1, $activeComponents);
        $this->assertEquals('USD', $activeComponents->first()->asset_code);
    }

    /** @test */
    public function it_validates_component_configuration()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 60.0,
            'min_weight' => 50.0,
            'max_weight' => 70.0,
        ]);

        $errors = $component->validate();
        $this->assertEmpty($errors);

        // Test invalid weight
        $component->weight = 150.0;
        $errors = $component->validate();
        $this->assertContains('Weight must be between 0 and 100', $errors);

        // Test min > max
        $component->weight = 60.0;
        $component->min_weight = 80.0;
        $component->max_weight = 70.0;
        $errors = $component->validate();
        $this->assertContains('Minimum weight cannot be greater than maximum weight', $errors);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $component = $this->basket->components()->create([
            'asset_code' => 'USD',
            'weight' => 60.5,
            'min_weight' => 50.25,
            'max_weight' => 70.75,
            'is_active' => true,
        ]);

        $fresh = BasketComponent::find($component->id);

        $this->assertIsFloat($fresh->weight);
        $this->assertIsFloat($fresh->min_weight);
        $this->assertIsFloat($fresh->max_weight);
        $this->assertIsBool($fresh->is_active);
    }
}