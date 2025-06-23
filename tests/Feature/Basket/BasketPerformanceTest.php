<?php

namespace Tests\Feature\Basket;

use App\Domain\Basket\Services\BasketPerformanceService;
use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Models\BasketPerformance;
use App\Models\BasketValue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BasketPerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected BasketAsset $basket;
    protected User $user;
    protected BasketPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->service = app(BasketPerformanceService::class);
        
        // Create a test basket with a unique code to avoid conflicts
        // Use process ID and timestamp for better uniqueness in parallel tests
        $uniqueCode = 'T' . substr(md5(uniqid(mt_rand(), true)), 0, 9);
        
        // Create basket without using factory's configure method to avoid auto-components
        $this->basket = new BasketAsset([
            'code' => $uniqueCode,
            'name' => 'Test Basket',
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active' => true,
            'description' => 'Test basket for performance testing',
            'metadata' => ['test' => true],
        ]);
        $this->basket->save();
        
        // Add components only if they don't exist
        if ($this->basket->components()->count() === 0) {
            $this->basket->components()->create([
                'asset_code' => 'USD',
                'weight' => 50.0,
                'is_active' => true,
            ]);
            
            $this->basket->components()->create([
                'asset_code' => 'EUR',
                'weight' => 30.0,
                'is_active' => true,
            ]);
            
            $this->basket->components()->create([
                'asset_code' => 'GBP',
                'weight' => 20.0,
                'is_active' => true,
            ]);
        }
    }

    public function test_can_calculate_performance_for_basket()
    {
        // Create historical values
        $now = now();
        $values = [
            ['value' => 1.0000, 'calculated_at' => $now->copy()->subDays(7)],
            ['value' => 1.0200, 'calculated_at' => $now->copy()->subDays(6)],
            ['value' => 1.0150, 'calculated_at' => $now->copy()->subDays(5)],
            ['value' => 1.0300, 'calculated_at' => $now->copy()->subDays(4)],
            ['value' => 1.0250, 'calculated_at' => $now->copy()->subDays(3)],
            ['value' => 1.0400, 'calculated_at' => $now->copy()->subDays(2)],
            ['value' => 1.0350, 'calculated_at' => $now->copy()->subDays(1)],
            ['value' => 1.0500, 'calculated_at' => $now],
        ];
        
        foreach ($values as $data) {
            BasketValue::factory()->create([
                'basket_asset_code' => $this->basket->code,
                'value' => $data['value'],
                'calculated_at' => $data['calculated_at'],
                'component_values' => [
                    'USD' => ['weight' => 50.0, 'weighted_value' => $data['value'] * 0.5],
                    'EUR' => ['weight' => 30.0, 'weighted_value' => $data['value'] * 0.3],
                    'GBP' => ['weight' => 20.0, 'weighted_value' => $data['value'] * 0.2],
                ],
            ]);
        }
        
        // Calculate weekly performance
        $performance = $this->service->calculatePerformance(
            $this->basket,
            'week',
            $now->copy()->subWeek(),
            $now
        );
        
        $this->assertNotNull($performance);
        $this->assertEquals($this->basket->code, $performance->basket_asset_code);
        $this->assertEquals('week', $performance->period_type);
        $this->assertEquals(1.0000, $performance->start_value);
        $this->assertEquals(1.0500, $performance->end_value);
        $this->assertEqualsWithDelta(0.0500, $performance->return_value, 0.0001);
        $this->assertEqualsWithDelta(5.00, $performance->return_percentage, 0.01);
        $this->assertGreaterThan(0, $performance->volatility);
        $this->assertNotNull($performance->sharpe_ratio);
        $this->assertGreaterThan(0, $performance->max_drawdown);
        $this->assertEquals(8, $performance->value_count);
    }

    public function test_can_calculate_all_periods()
    {
        // Create values for different time periods
        $now = now();
        $times = [
            $now->copy()->subYear(),
            $now->copy()->subQuarter(),
            $now->copy()->subMonth(),
            $now->copy()->subWeek(),
            $now->copy()->subDay(),
            $now->copy()->subHour(),
            $now,
        ];
        
        foreach ($times as $index => $time) {
            BasketValue::factory()->create([
                'basket_asset_code' => $this->basket->code,
                'value' => 1.0 + ($index * 0.01),
                'calculated_at' => $time,
            ]);
        }
        
        $performances = $this->service->calculateAllPeriods($this->basket);
        
        $this->assertGreaterThan(0, $performances->count());
        
        // Check that we have different period types
        $periodTypes = $performances->pluck('period_type')->unique();
        $this->assertContains('hour', $periodTypes);
        $this->assertContains('day', $periodTypes);
        $this->assertContains('week', $periodTypes);
    }

    public function test_can_get_performance_summary()
    {
        // Create some performance records
        BasketPerformance::factory()->create([
            'basket_asset_code' => $this->basket->code,
            'period_type' => 'day',
            'period_end' => now(),
            'return_percentage' => 1.5,
            'volatility' => 2.5,
            'sharpe_ratio' => 1.2,
        ]);
        
        BasketPerformance::factory()->create([
            'basket_asset_code' => $this->basket->code,
            'period_type' => 'week',
            'period_end' => now(),
            'return_percentage' => 3.2,
            'volatility' => 3.8,
            'sharpe_ratio' => 1.5,
        ]);
        
        $summary = $this->service->getPerformanceSummary($this->basket);
        
        $this->assertArrayHasKey('basket_code', $summary);
        $this->assertArrayHasKey('basket_name', $summary);
        $this->assertArrayHasKey('performances', $summary);
        $this->assertEquals($this->basket->code, $summary['basket_code']);
        $this->assertEquals('Test Basket', $summary['basket_name']);
        
        if (isset($summary['performances']['day'])) {
            $this->assertEquals(1.5, $summary['performances']['day']['return_percentage']);
            $this->assertEquals('+1.50%', $summary['performances']['day']['formatted_return']);
        }
    }

    public function test_api_can_get_basket_performance()
    {
        Sanctum::actingAs($this->user);
        
        // Create a performance record
        $performance = BasketPerformance::factory()->create([
            'basket_asset_code' => $this->basket->code,
            'period_type' => 'month',
            'period_start' => now()->subMonth(),
            'period_end' => now(),
            'return_percentage' => 2.5,
            'volatility' => 5.0,
            'sharpe_ratio' => 1.8,
            'max_drawdown' => 3.2,
        ]);
        
        // Create some basket values for performance calculation
        BasketValue::factory()->forBasket($this->basket->code)->create([
            'value' => 1.0000,
            'calculated_at' => now()->subDays(30),
        ]);
        
        BasketValue::factory()->forBasket($this->basket->code)->create([
            'value' => 1.0250,
            'calculated_at' => now(),
        ]);
        
        $response = $this->getJson("/api/v2/baskets/{$this->basket->code}/performance?period=30d");
        
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
            ])
            ->assertJsonPath('basket_code', $this->basket->code)
            ->assertJsonPath('period', '30d')
            ->assertJsonPath('performance.percentage_change', 2.5);
    }

    public function test_api_can_get_performance_history()
    {
        Sanctum::actingAs($this->user);
        
        // Create multiple performance records with different dates
        $performances = collect();
        for ($i = 0; $i < 5; $i++) {
            $performances->push(BasketPerformance::factory()->create([
                'basket_asset_code' => $this->basket->code,
                'period_type' => 'day',
                'period_start' => now()->subDays($i + 1),
                'period_end' => now()->subDays($i),
            ]));
        }
        
        $response = $this->getJson("/api/v2/baskets/{$this->basket->code}/performance/history?period_type=day&limit=3");
        
        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'basket_code',
                        'period_type',
                        'return_percentage',
                    ],
                ],
            ]);
    }

    public function test_api_can_calculate_performance()
    {
        Sanctum::actingAs($this->user);
        
        // Create values
        BasketValue::factory()->forBasket($this->basket->code)->create([
            'value' => 1.0000,
            'calculated_at' => now()->subDays(2),
        ]);
        
        BasketValue::factory()->forBasket($this->basket->code)->create([
            'value' => 1.0100,
            'calculated_at' => now()->subDay(),
        ]);
        
        BasketValue::factory()->forBasket($this->basket->code)->create([
            'value' => 1.0200,
            'calculated_at' => now(),
        ]);
        
        $response = $this->postJson("/api/v2/baskets/{$this->basket->code}/performance/calculate", [
            'period' => 'day',
        ]);
        
        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'calculated_periods',
            ])
            ->assertJsonPath('calculated_periods', ['day']);
        
        // Check that performance was created
        $this->assertDatabaseHas('basket_performances', [
            'basket_asset_code' => $this->basket->code,
            'period_type' => 'day',
        ]);
    }

    public function test_can_get_top_and_worst_performers()
    {
        // Create performance with components
        $performance = BasketPerformance::factory()->create([
            'basket_asset_code' => $this->basket->code,
            'period_type' => 'month',
        ]);
        
        // Create component performances with all required fields
        $performance->componentPerformances()->createMany([
            [
                'asset_code' => 'USD',
                'contribution_percentage' => 2.5,
                'start_weight' => 50.0,
                'end_weight' => 50.0,
                'average_weight' => 50.0,
                'contribution_value' => 0.025,
                'return_value' => 0.05,
                'return_percentage' => 5.0,
            ],
            [
                'asset_code' => 'EUR',
                'contribution_percentage' => -1.2,
                'start_weight' => 30.0,
                'end_weight' => 30.0,
                'average_weight' => 30.0,
                'contribution_value' => -0.012,
                'return_value' => -0.04,
                'return_percentage' => -4.0,
            ],
            [
                'asset_code' => 'GBP',
                'contribution_percentage' => 0.8,
                'start_weight' => 20.0,
                'end_weight' => 20.0,
                'average_weight' => 20.0,
                'contribution_value' => 0.008,
                'return_value' => 0.04,
                'return_percentage' => 4.0,
            ],
        ]);
        
        $topPerformers = $this->service->getTopPerformers($this->basket, 'month', 2);
        $worstPerformers = $this->service->getWorstPerformers($this->basket, 'month', 2);
        
        $this->assertEquals(2, $topPerformers->count());
        $this->assertEquals('USD', $topPerformers->first()->asset_code);
        $this->assertEquals(2.5, $topPerformers->first()->contribution_percentage);
        
        $this->assertEquals(2, $worstPerformers->count());
        $this->assertEquals('EUR', $worstPerformers->first()->asset_code);
        $this->assertEquals(-1.2, $worstPerformers->first()->contribution_percentage);
    }

    public function test_calculates_volatility_correctly()
    {
        // Create values with known volatility
        $values = collect([
            ['value' => 1.00, 'calculated_at' => now()->subDays(4)],
            ['value' => 1.02, 'calculated_at' => now()->subDays(3)], // +2%
            ['value' => 0.98, 'calculated_at' => now()->subDays(2)], // -3.92%
            ['value' => 1.01, 'calculated_at' => now()->subDays(1)], // +3.06%
            ['value' => 1.00, 'calculated_at' => now()],            // -0.99%
        ]);
        
        foreach ($values as $data) {
            BasketValue::factory()->create([
                'basket_asset_code' => $this->basket->code,
                'value' => $data['value'],
                'calculated_at' => $data['calculated_at'],
            ]);
        }
        
        $performance = $this->service->calculatePerformance(
            $this->basket,
            'week',
            now()->subDays(4),
            now()
        );
        
        // Expected returns: [2, -3.92, 3.06, -0.99]
        // Mean: 0.0375
        // Variance: 8.716
        // StdDev: 2.95
        
        $this->assertNotNull($performance);
        $this->assertGreaterThan(2.5, $performance->volatility);
        $this->assertLessThan(3.5, $performance->volatility);
    }

    public function test_performance_command()
    {
        // Create values
        BasketValue::factory()->count(10)->create([
            'basket_asset_code' => $this->basket->code,
            'calculated_at' => now(),
        ]);
        
        $this->artisan('basket:calculate-performance', [
            '--basket' => $this->basket->code,
            '--period' => 'all',
        ])
        ->expectsOutput('Processing performance for 1 basket(s)...')
        ->expectsOutputToContain($this->basket->code)
        ->assertSuccessful();
    }
}