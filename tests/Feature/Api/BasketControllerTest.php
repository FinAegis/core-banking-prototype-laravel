<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketValue;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BasketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
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
        
        Asset::firstOrCreate(
            ['code' => 'GBP'],
            [
                'name' => 'British Pound',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true
            ]
        );
        
        // Create exchange rates
        ExchangeRate::create([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.1,
            'provider' => 'test',
            'valid_at' => now(),
            'updated_at' => now(),
        ]);
        
        ExchangeRate::create([
            'from_asset_code' => 'GBP',
            'to_asset_code' => 'USD',
            'rate' => 1.25,
            'provider' => 'test',
            'valid_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_list_baskets()
    {
        $basket1 = BasketAsset::create([
            'code' => 'STABLE_BASKET',
            'name' => 'Stable Currency Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket1->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);
        
        $basket2 = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Currency Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
        ]);
        
        $basket2->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
            ['asset_code' => 'EUR', 'weight' => 50.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
        ]);
        
        $response = $this->getJson('/api/v2/baskets');
        
        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['code' => 'STABLE_BASKET'])
            ->assertJsonFragment(['code' => 'DYNAMIC_BASKET']);
    }

    /** @test */
    public function it_can_filter_baskets_by_type()
    {
        $fixedBasket = BasketAsset::create([
            'code' => 'FIXED_BASKET',
            'name' => 'Fixed Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $fixedBasket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $dynamicBasket = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
        ]);
        
        $dynamicBasket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $response = $this->getJson('/api/v2/baskets?type=fixed');
        
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['code' => 'FIXED_BASKET'])
            ->assertJsonMissing(['code' => 'DYNAMIC_BASKET']);
    }

    /** @test */
    public function it_can_filter_baskets_by_active_status()
    {
        $activeBasket = BasketAsset::create([
            'code' => 'ACTIVE_BASKET',
            'name' => 'Active Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $activeBasket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $inactiveBasket = BasketAsset::create([
            'code' => 'INACTIVE_BASKET',
            'name' => 'Inactive Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => false,
        ]);
        
        $inactiveBasket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $response = $this->getJson('/api/v2/baskets?active=true');
        
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['code' => 'ACTIVE_BASKET'])
            ->assertJsonMissing(['code' => 'INACTIVE_BASKET']);
    }

    /** @test */
    public function it_can_show_basket_details()
    {
        $basket = BasketAsset::create([
            'code' => 'TEST_BASKET',
            'name' => 'Test Basket',
            'description' => 'Test basket description',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);
        
        BasketValue::create([
            'basket_code' => 'TEST_BASKET',
            'basket_asset_code' => 'TEST_BASKET',
            'value' => 1.05,
            'component_values' => ['USD' => 0.40, 'EUR' => 0.385, 'GBP' => 0.265],
            'calculated_at' => now(),
        ]);
        
        $response = $this->getJson('/api/v2/baskets/TEST_BASKET');
        
        $response->assertOk()
            ->assertJsonFragment([
                'code' => 'TEST_BASKET',
                'name' => 'Test Basket',
                'description' => 'Test basket description',
                'type' => 'fixed',
            ])
            ->assertJsonCount(3, 'components')
            ->assertJsonStructure([
                'code',
                'name',
                'description',
                'type',
                'rebalance_frequency',
                'is_active',
                'created_at',
                'components',
                'recent_values',
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_basket()
    {
        $response = $this->getJson('/api/v2/baskets/NONEXISTENT');
        
        $response->assertNotFound();
    }

    /** @test */
    public function it_can_create_basket_with_authentication()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'NEW_BASKET',
            'name' => 'New Test Basket',
            'description' => 'A new basket for testing',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 40.0],
                ['asset_code' => 'EUR', 'weight' => 35.0],
                ['asset_code' => 'GBP', 'weight' => 25.0],
            ],
        ]);
        
        $response->assertCreated()
            ->assertJsonFragment([
                'code' => 'NEW_BASKET',
                'name' => 'New Test Basket',
                'type' => 'fixed',
            ])
            ->assertJsonCount(3, 'components');
        
        $this->assertDatabaseHas('basket_assets', [
            'code' => 'NEW_BASKET',
            'name' => 'New Test Basket',
        ]);
        
        // Find the basket to get its ID
        $basket = BasketAsset::where('code', 'NEW_BASKET')->first();
        
        $this->assertDatabaseHas('basket_components', [
            'basket_asset_id' => $basket->id,
            'asset_code' => 'USD',
            'weight' => 40.0,
        ]);
        
        // Check that basket was created as an asset
        $this->assertDatabaseHas('assets', [
            'code' => 'NEW_BASKET',
            'name' => 'New Test Basket',
            'type' => 'custom',
        ]);
    }

    /** @test */
    public function it_validates_component_weights_sum_to_100()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'INVALID_BASKET',
            'name' => 'Invalid Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 40.0],
                ['asset_code' => 'EUR', 'weight' => 35.0],
                ['asset_code' => 'GBP', 'weight' => 20.0], // Total = 95
            ],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Component weights must sum to 100%',
                'total_weight' => 95.0,
            ]);
    }

    /** @test */
    public function it_validates_basket_creation_fields()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/v2/baskets', []);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'components', 'type', 'rebalance_frequency']);
    }

    /** @test */
    public function it_validates_duplicate_basket_code()
    {
        Sanctum::actingAs($this->user);
        
        BasketAsset::create([
            'code' => 'EXISTING_BASKET',
            'name' => 'Existing Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'EXISTING_BASKET',
            'name' => 'Another Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 50.0],
                ['asset_code' => 'EUR', 'weight' => 50.0],
            ],
        ]);
        
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function it_can_get_basket_value()
    {
        // Clear any cached values
        \Cache::flush();
        
        $basket = BasketAsset::create([
            'code' => 'VALUE_BASKET',
            'name' => 'Value Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0, 'is_active' => true],
            ['asset_code' => 'EUR', 'weight' => 35.0, 'is_active' => true],
            ['asset_code' => 'GBP', 'weight' => 25.0, 'is_active' => true],
        ]);
        
        $response = $this->getJson('/api/v2/baskets/VALUE_BASKET/value');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'value',
                'calculated_at',
                'component_values',
            ])
            ->assertJsonFragment(['basket_code' => 'VALUE_BASKET']);
        
        // Check that value was stored
        $this->assertDatabaseHas('basket_values', [
            'basket_asset_code' => 'VALUE_BASKET',
        ]);
    }

    /** @test */
    public function it_can_rebalance_dynamic_basket()
    {
        Sanctum::actingAs($this->user);
        
        $basket = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 45.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
            ['asset_code' => 'EUR', 'weight' => 55.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
        ]);
        
        $response = $this->postJson('/api/v2/baskets/DYNAMIC_BASKET/rebalance');
        
        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'basket',
                'adjustments',
            ]);
    }

    /** @test */
    public function it_prevents_rebalancing_fixed_basket()
    {
        Sanctum::actingAs($this->user);
        
        $basket = BasketAsset::create([
            'code' => 'FIXED_BASKET',
            'name' => 'Fixed Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $response = $this->postJson('/api/v2/baskets/FIXED_BASKET/rebalance');
        
        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Only dynamic baskets can be rebalanced',
            ]);
    }

    /** @test */
    public function it_can_simulate_rebalancing()
    {
        Sanctum::actingAs($this->user);
        
        $basket = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 45.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
            ['asset_code' => 'EUR', 'weight' => 55.0, 'min_weight' => 40.0, 'max_weight' => 60.0],
        ]);
        
        $response = $this->postJson('/api/v2/baskets/DYNAMIC_BASKET/rebalance?simulate=true');
        
        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'basket',
                'adjustments',
            ]);
    }

    /** @test */
    public function it_can_get_basket_history()
    {
        $basket = BasketAsset::create([
            'code' => 'HISTORY_BASKET',
            'name' => 'History Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        // Create some historical values
        for ($i = 0; $i < 10; $i++) {
            BasketValue::create([
                'basket_code' => 'HISTORY_BASKET',
                'basket_asset_code' => 'HISTORY_BASKET',
                'value' => 1.0 + ($i * 0.01),
                'component_values' => ['USD' => 0.5, 'EUR' => 0.5 + ($i * 0.01)],
                'calculated_at' => now()->subDays($i),
            ]);
        }
        
        $response = $this->getJson('/api/v2/baskets/HISTORY_BASKET/history?days=7');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'period' => ['start', 'end'],
                'values',
            ])
            ->assertJsonFragment(['basket_code' => 'HISTORY_BASKET']);
    }

    /** @test */
    public function it_can_get_basket_performance()
    {
        $basket = BasketAsset::create([
            'code' => 'PERF_BASKET',
            'name' => 'Performance Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        // Create values for performance calculation
        BasketValue::create([
            'basket_code' => 'PERF_BASKET',
            'basket_asset_code' => 'PERF_BASKET',
            'value' => 1.0,
            'component_values' => ['USD' => 0.5, 'EUR' => 0.5],
            'calculated_at' => now()->subDays(30),
        ]);
        
        BasketValue::create([
            'basket_code' => 'PERF_BASKET',
            'basket_asset_code' => 'PERF_BASKET',
            'value' => 1.1,
            'component_values' => ['USD' => 0.5, 'EUR' => 0.6],
            'calculated_at' => now(),
        ]);
        
        $response = $this->getJson('/api/v2/baskets/PERF_BASKET/performance?period=30d');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'period',
                'performance',
            ])
            ->assertJsonFragment(['basket_code' => 'PERF_BASKET']);
    }

    /** @test */
    public function it_requires_authentication_for_basket_creation()
    {
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'NEW_BASKET',
            'name' => 'New Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 50.0],
                ['asset_code' => 'EUR', 'weight' => 50.0],
            ],
        ]);
        
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_requires_authentication_for_rebalancing()
    {
        $basket = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
        ]);
        
        $basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 50.0],
        ]);
        
        $response = $this->postJson('/api/v2/baskets/DYNAMIC_BASKET/rebalance');
        
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_validates_component_min_max_weights()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'monthly',
            'components' => [
                [
                    'asset_code' => 'USD',
                    'weight' => 50.0,
                    'min_weight' => 60.0, // Min > current weight
                    'max_weight' => 70.0,
                ],
                [
                    'asset_code' => 'EUR',
                    'weight' => 50.0,
                    'min_weight' => 30.0,
                    'max_weight' => 40.0,
                ],
            ],
        ]);
        
        $response->assertCreated(); // Validation allows this, rebalancing will adjust
    }
}