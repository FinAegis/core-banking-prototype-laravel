<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\BasketAsset;
use App\Models\BasketValue;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasketValueTest extends TestCase
{

    protected BasketAsset $basket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basket = BasketAsset::create([
            'code' => 'TEST_BSK',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_a_basket_value()
    {
        $value = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.2345,
            'calculated_at' => now(),
            'component_values' => [
                'USD' => ['value' => 0.5, 'weight' => 40.0],
                'EUR' => ['value' => 0.7345, 'weight' => 60.0],
            ],
        ]);

        $this->assertEquals('TEST_BSK', $value->basket_asset_code);
        $this->assertEquals(1.2345, $value->value);
        $this->assertIsArray($value->component_values);
    }

    /** @test */
    public function it_belongs_to_basket()
    {
        $value = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now(),
        ]);

        $this->assertInstanceOf(BasketAsset::class, $value->basket);
        $this->assertEquals('TEST_BSK', $value->basket->code);
    }

    /** @test */
    public function it_can_check_if_fresh()
    {
        $freshValue = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now(),
        ]);

        $this->assertTrue($freshValue->isFresh(5));
        $this->assertTrue($freshValue->isFresh(10));

        $oldValue = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($oldValue->isFresh(5));
        $this->assertTrue($oldValue->isFresh(15));
    }

    /** @test */
    public function it_can_get_component_value()
    {
        $value = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now(),
            'component_values' => [
                'USD' => ['weighted_value' => 0.4],
                'EUR' => ['weighted_value' => 0.6],
            ],
        ]);

        $this->assertEquals(0.4, $value->getComponentValue('USD'));
        $this->assertEquals(0.6, $value->getComponentValue('EUR'));
        $this->assertNull($value->getComponentValue('GBP'));
    }

    /** @test */
    public function it_can_get_actual_weight()
    {
        $value = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now(),
            'component_values' => [
                'USD' => ['weighted_value' => 0.4],
                'EUR' => ['weighted_value' => 0.6],
            ],
        ]);

        $this->assertEquals(40.0, $value->getActualWeight('USD'));
        $this->assertEquals(60.0, $value->getActualWeight('EUR'));
        $this->assertNull($value->getActualWeight('GBP'));
    }

    /** @test */
    public function it_can_calculate_performance()
    {
        $previousValue = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now()->subDay(),
        ]);

        $currentValue = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.1,
            'calculated_at' => now(),
        ]);

        $performance = $currentValue->getPerformance($previousValue);

        $this->assertEquals(1.0, $performance['previous_value']);
        $this->assertEquals(1.1, $performance['current_value']);
        $this->assertEqualsWithDelta(0.1, $performance['change'], 0.0000001);
        $this->assertEqualsWithDelta(10.0, $performance['percentage_change'], 0.0000001);
    }

    /** @test */
    public function it_has_between_dates_scope()
    {
        BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.0,
            'calculated_at' => now()->subDays(5),
        ]);

        BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.1,
            'calculated_at' => now()->subDays(3),
        ]);

        BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.2,
            'calculated_at' => now()->subDay(),
        ]);

        $values = BasketValue::betweenDates(
            now()->subDays(4),
            now()
        )->get();

        $this->assertCount(2, $values);
        $this->assertEquals(1.1, $values->first()->value);
        $this->assertEquals(1.2, $values->last()->value);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $value = BasketValue::create([
            'basket_asset_code' => 'TEST_BSK',
            'value' => 1.2345,
            'calculated_at' => '2025-06-18 12:00:00',
            'component_values' => ['USD' => 0.5],
        ]);

        $fresh = BasketValue::find($value->id);

        $this->assertIsFloat($fresh->value);
        $this->assertInstanceOf(\Carbon\Carbon::class, $fresh->calculated_at);
        $this->assertIsArray($fresh->component_values);
    }
}