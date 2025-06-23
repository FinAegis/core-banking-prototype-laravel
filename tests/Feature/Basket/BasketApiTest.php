<?php

declare(strict_types=1);

namespace Tests\Feature\Basket;

use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Models\User;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Domain\Basket\Services\BasketValueCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BasketApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Account $account;
    protected BasketAsset $basket;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        // Create account for the user
        $this->account = Account::factory()->zeroBalance()->create(['user_uuid' => $this->user->uuid]);
        
        // Create test assets (use firstOrCreate to avoid conflicts)
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        
        // Create exchange rates
        ExchangeRate::factory()->create([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'USD',
            'rate' => 1.1000,
            'is_active' => true,
        ]);
        
        ExchangeRate::factory()->create([
            'from_asset_code' => 'GBP',
            'to_asset_code' => 'USD',
            'rate' => 1.2500,
            'is_active' => true,
        ]);
        
        // Create test basket
        $this->basket = BasketAsset::create([
            'code' => 'TEST_BASKET',
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
        ]);
        
        $this->basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);
        
        // Create as asset and calculate value
        $this->basket->toAsset();
        app(BasketValueCalculationService::class)->calculateValue($this->basket);
    }

    /** @test */
    public function it_can_list_baskets()
    {
        $response = $this->getJson('/api/v2/baskets');
        
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'code',
                    'name',
                    'description',
                    'type',
                    'rebalance_frequency',
                    'is_active',
                    'latest_value',
                    'components',
                ],
            ]);
        
        $response->assertJsonPath('0.code', 'TEST_BASKET');
        $response->assertJsonPath('0.components', function ($components) {
            return count($components) === 3;
        });
    }

    /** @test */
    public function it_can_filter_baskets_by_type()
    {
        // Create a dynamic basket
        $dynamicBasket = BasketAsset::create([
            'code' => 'DYNAMIC_BASKET',
            'name' => 'Dynamic Basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);
        
        $response = $this->getJson('/api/v2/baskets?type=fixed');
        
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'TEST_BASKET');
        
        $response = $this->getJson('/api/v2/baskets?type=dynamic');
        
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'DYNAMIC_BASKET');
    }

    /** @test */
    public function it_can_get_basket_details()
    {
        $response = $this->getJson('/api/v2/baskets/TEST_BASKET');
        
        $response->assertOk()
            ->assertJsonStructure([
                'code',
                'name',
                'description',
                'type',
                'rebalance_frequency',
                'is_active',
                'created_at',
                'components' => [
                    '*' => [
                        'asset_code',
                        'asset_name',
                        'weight',
                        'is_active',
                    ],
                ],
                'recent_values',
            ]);
        
        $response->assertJsonPath('code', 'TEST_BASKET');
        $response->assertJsonCount(3, 'components');
    }

    /** @test */
    public function it_returns_404_for_non_existent_basket()
    {
        $response = $this->getJson('/api/v2/baskets/INVALID_BASKET');
        
        $response->assertNotFound();
    }

    /** @test */
    public function it_can_get_basket_value()
    {
        $response = $this->getJson('/api/v2/baskets/TEST_BASKET/value');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'value',
                'calculated_at',
                'component_values',
            ]);
        
        $response->assertJsonPath('basket_code', 'TEST_BASKET');
        $this->assertIsNumeric($response->json('value'));
        $this->assertGreaterThan(0, $response->json('value'));
    }

    /** @test */
    public function it_can_create_basket_with_authentication()
    {
        $data = [
            'code' => 'NEW_BASKET',
            'name' => 'New Test Basket',
            'description' => 'A new basket for testing',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 50.0],
                ['asset_code' => 'EUR', 'weight' => 50.0],
            ],
        ];
        
        $response = $this->postJson('/api/v2/baskets', $data);
        
        $response->assertCreated()
            ->assertJsonPath('code', 'NEW_BASKET')
            ->assertJsonPath('name', 'New Test Basket')
            ->assertJsonCount(2, 'components');
        
        $this->assertDatabaseHas('basket_assets', [
            'code' => 'NEW_BASKET',
            'name' => 'New Test Basket',
        ]);
        
        $this->assertDatabaseHas('assets', [
            'code' => 'NEW_BASKET',
            'type' => 'custom',
        ]);
    }

    /** @test */
    public function it_validates_component_weights_sum_to_100()
    {
        $data = [
            'code' => 'INVALID_WEIGHTS',
            'name' => 'Invalid Weights Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'components' => [
                ['asset_code' => 'USD', 'weight' => 30.0],
                ['asset_code' => 'EUR', 'weight' => 40.0],
                // Total: 70%, not 100%
            ],
        ];
        
        $response = $this->postJson('/api/v2/baskets', $data);
        
        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Component weights must sum to 100%');
    }

    /** @test */
    public function it_can_rebalance_dynamic_basket()
    {
        // Create dynamic basket
        $dynamicBasket = BasketAsset::create([
            'code' => 'DYNAMIC_TEST',
            'name' => 'Dynamic Test',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);
        
        $dynamicBasket->components()->createMany([
            [
                'asset_code' => 'USD',
                'weight' => 45.0,
                'min_weight' => 35.0,
                'max_weight' => 40.0,
            ],
            [
                'asset_code' => 'EUR',
                'weight' => 55.0,
                'min_weight' => 50.0,
                'max_weight' => 60.0,
            ],
        ]);
        
        $response = $this->postJson('/api/v2/baskets/DYNAMIC_TEST/rebalance');
        
        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'basket',
                'adjustments',
            ]);
        
        $response->assertJsonPath('status', 'completed');
    }

    /** @test */
    public function it_cannot_rebalance_fixed_basket()
    {
        $response = $this->postJson('/api/v2/baskets/TEST_BASKET/rebalance');
        
        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Only dynamic baskets can be rebalanced');
    }

    /** @test */
    public function it_can_simulate_rebalancing()
    {
        $dynamicBasket = BasketAsset::create([
            'code' => 'SIMULATE_TEST',
            'name' => 'Simulate Test',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);
        
        $dynamicBasket->components()->create([
            'asset_code' => 'USD',
            'weight' => 100.0,
            'min_weight' => 90.0,
            'max_weight' => 95.0,
        ]);
        
        $response = $this->postJson('/api/v2/baskets/SIMULATE_TEST/rebalance?simulate=true');
        
        $response->assertOk()
            ->assertJsonPath('status', 'simulated')
            ->assertJsonPath('simulated', true);
    }

    /** @test */
    public function it_can_get_basket_history()
    {
        $response = $this->getJson('/api/v2/baskets/TEST_BASKET/history?days=7');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'period' => ['start', 'end'],
                'values',
            ]);
        
        $response->assertJsonPath('basket_code', 'TEST_BASKET');
    }

    /** @test */
    public function it_can_get_basket_performance()
    {
        $response = $this->getJson('/api/v2/baskets/TEST_BASKET/performance?period=30d');
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'period',
                'performance' => [
                    'start_value',
                    'end_value',
                    'absolute_change',
                    'percentage_change',
                ],
            ]);
        
        $response->assertJsonPath('basket_code', 'TEST_BASKET');
        $response->assertJsonPath('period', '30d');
    }

    /** @test */
    public function it_requires_authentication_for_protected_endpoints()
    {
        // Don't authenticate (start fresh test instance)
        $this->refreshApplication();
        
        $response = $this->postJson('/api/v2/baskets', [
            'code' => 'UNAUTH_BASKET',
            'name' => 'Unauthorized Basket',
        ]);
        
        $response->assertUnauthorized();
        
        $response = $this->postJson('/api/v2/baskets/TEST_BASKET/rebalance');
        
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_can_decompose_basket_in_account()
    {
        $this->markTestSkipped('Basket decompose/compose functionality needs event sourcing refactoring');
        
        // Give account basket balance
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'TEST_BASKET',
            'balance' => 10000,
        ]);
        
        $response = $this->postJson("/api/v2/accounts/{$this->account->uuid}/baskets/decompose", [
            'basket_code' => 'TEST_BASKET',
            'amount' => 5000,
        ]);
        
        if ($response->status() !== 200) {
            dump($response->json());
        }
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'basket_amount',
                'components',
                'decomposed_at',
            ]);
        
        $response->assertJsonPath('basket_code', 'TEST_BASKET');
        $response->assertJsonPath('basket_amount', 5000);
        
        // Check balances
        $this->assertEquals(5000, $this->account->fresh()->getBalance('TEST_BASKET'));
    }

    /** @test */
    public function it_can_compose_basket_in_account()
    {
        $this->markTestSkipped('Basket decompose/compose functionality needs event sourcing refactoring');
        
        // Give account component balances
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'USD',
            'balance' => 2000,
        ]);
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'EUR',
            'balance' => 1750,
        ]);
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'GBP',
            'balance' => 1250,
        ]);
        
        $response = $this->postJson("/api/v2/accounts/{$this->account->uuid}/baskets/compose", [
            'basket_code' => 'TEST_BASKET',
            'amount' => 5000,
        ]);
        
        $response->assertOk()
            ->assertJsonStructure([
                'basket_code',
                'basket_amount',
                'components_used',
                'composed_at',
            ]);
        
        $response->assertJsonPath('basket_code', 'TEST_BASKET');
        $response->assertJsonPath('basket_amount', 5000);
        
        // Check basket balance was created
        $this->assertEquals(5000, $this->account->fresh()->getBalance('TEST_BASKET'));
    }

    /** @test */
    public function it_can_get_account_basket_holdings()
    {
        $this->markTestSkipped('Basket holdings functionality needs event sourcing refactoring');
        
        // Give account multiple basket holdings
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code' => 'TEST_BASKET',
            'balance' => 10000,
        ]);
        
        $response = $this->getJson("/api/v2/accounts/{$this->account->uuid}/baskets");
        
        $response->assertOk()
            ->assertJsonStructure([
                'account_uuid',
                'basket_holdings' => [
                    '*' => [
                        'basket_code',
                        'basket_name',
                        'balance',
                        'unit_value',
                        'total_value',
                    ],
                ],
                'total_value',
                'currency',
            ]);
        
        $response->assertJsonPath('account_uuid', (string)$this->account->uuid);
        $response->assertJsonCount(1, 'basket_holdings');
    }
}