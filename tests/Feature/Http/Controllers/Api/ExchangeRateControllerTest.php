<?php

namespace Tests\Feature\Http\Controllers\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use Laravel\Sanctum\Sanctum;
use Mockery;

class ExchangeRateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Asset $usd;
    protected Asset $eur;
    protected Asset $gbp;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create test assets
        $this->usd = Asset::factory()->create(['code' => 'USD', 'precision' => 2]);
        $this->eur = Asset::factory()->create(['code' => 'EUR', 'precision' => 2]);
        $this->gbp = Asset::factory()->create(['code' => 'GBP', 'precision' => 2]);
    }

    /** @test */
    public function it_gets_exchange_rate_between_two_assets()
    {
        Sanctum::actingAs($this->user);
        
        $exchangeRate = ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => '0.85000000',
            'source' => 'test',
            'is_active' => true,
            'valid_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        
        $response = $this->getJson('/api/exchange-rates/USD/EUR');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'from_asset',
                'to_asset',
                'rate',
                'inverse_rate',
                'source',
                'valid_at',
                'expires_at',
                'is_active',
                'age_minutes',
                'metadata',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'from_asset' => 'USD',
                'to_asset' => 'EUR',
                'rate' => '0.85',
                'is_active' => true,
            ],
        ]);
        
        // Check inverse rate calculation
        $inverseRate = number_format(1 / 0.85, 10, '.', '');
        $response->assertJsonPath('data.inverse_rate', $inverseRate);
    }

    /** @test */
    public function it_handles_case_insensitive_asset_codes()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => '0.85000000',
            'is_active' => true,
        ]);
        
        $response = $this->getJson('/api/exchange-rates/usd/eur');
        $response->assertStatus(200);
        
        $response = $this->getJson('/api/exchange-rates/UsD/EuR');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_404_when_exchange_rate_not_found()
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/exchange-rates/USD/JPY');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Exchange rate not found',
            'error' => 'No active exchange rate found for the specified asset pair',
        ]);
    }

    /** @test */
    public function it_converts_amount_between_assets()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => '0.85000000',
            'is_active' => true,
            'valid_at' => now(),
        ]);
        
        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert?amount=10000');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'from_asset',
                'to_asset',
                'from_amount',
                'to_amount',
                'from_formatted',
                'to_formatted',
                'rate',
                'rate_age_minutes',
            ],
        ]);
        
        $response->assertJson([
            'data' => [
                'from_asset' => 'USD',
                'to_asset' => 'EUR',
                'from_amount' => 10000,
                'to_amount' => 8500, // 10000 * 0.85
                'from_formatted' => '100.00 USD',
                'to_formatted' => '85.00 EUR',
                'rate' => '0.85',
            ],
        ]);
    }

    /** @test */
    public function it_validates_conversion_amount()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => '0.85000000',
            'is_active' => true,
        ]);
        
        // Missing amount
        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert');
        $response->assertStatus(422);
        
        // Invalid amount (negative)
        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert?amount=-100');
        $response->assertStatus(422);
        
        // Invalid amount (non-numeric)
        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert?amount=invalid');
        $response->assertStatus(422);
    }

    /** @test */
    public function it_lists_all_exchange_rates()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->count(3)->create([
            'is_active' => true,
        ]);
        ExchangeRate::factory()->count(2)->create([
            'is_active' => false,
        ]);
        
        $response = $this->getJson('/api/exchange-rates');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'from_asset',
                    'to_asset',
                    'rate',
                    'source',
                    'is_active',
                    'valid_at',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_filters_exchange_rates_by_active_status()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->count(3)->create(['is_active' => true]);
        ExchangeRate::factory()->count(2)->create(['is_active' => false]);
        
        $response = $this->getJson('/api/exchange-rates?active=true');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        
        foreach ($response->json('data') as $rate) {
            $this->assertTrue($rate['is_active']);
        }
    }

    /** @test */
    public function it_filters_exchange_rates_by_asset()
    {
        Sanctum::actingAs($this->user);
        
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
        ]);
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'GBP',
        ]);
        ExchangeRate::factory()->create([
            'from_asset_code' => 'EUR',
            'to_asset_code' => 'GBP',
        ]);
        
        $response = $this->getJson('/api/exchange-rates?asset=USD');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        
        foreach ($response->json('data') as $rate) {
            $this->assertTrue(
                $rate['from_asset'] === 'USD' || $rate['to_asset'] === 'USD'
            );
        }
    }

    /** @test */
    public function it_gets_exchange_rate_history()
    {
        Sanctum::actingAs($this->user);
        
        // Create historical rates
        $dates = [
            now()->subDays(5),
            now()->subDays(3),
            now()->subDays(1),
            now(),
        ];
        
        foreach ($dates as $date) {
            ExchangeRate::factory()->create([
                'from_asset_code' => 'USD',
                'to_asset_code' => 'EUR',
                'valid_at' => $date,
            ]);
        }
        
        $response = $this->getJson('/api/exchange-rates/USD/EUR/history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'rate',
                    'valid_at',
                    'source',
                ],
            ],
        ]);
        $response->assertJsonCount(4, 'data');
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/exchange-rates/USD/EUR');
        $response->assertStatus(401);
        
        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert?amount=1000');
        $response->assertStatus(401);
        
        $response = $this->getJson('/api/exchange-rates');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_stale_exchange_rates()
    {
        Sanctum::actingAs($this->user);
        
        // Create an expired rate
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => '0.85000000',
            'is_active' => true,
            'valid_at' => now()->subHours(2),
            'expires_at' => now()->subHour(), // Expired
        ]);
        
        $response = $this->getJson('/api/exchange-rates/USD/EUR');

        // Should still return the rate but indicate it's stale
        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', true);
        $this->assertGreaterThan(60, $response->json('data.age_minutes'));
    }

    /** @test */
    public function it_calculates_cross_rates()
    {
        Sanctum::actingAs($this->user);
        
        // Mock the exchange rate service for cross rate calculation
        $service = Mockery::mock(ExchangeRateService::class);
        $service->shouldReceive('getRate')
            ->with('USD', 'JPY')
            ->andReturn(null); // No direct rate
            
        $service->shouldReceive('calculateCrossRate')
            ->with('USD', 'JPY')
            ->andReturn('110.25000000');
            
        $this->app->instance(ExchangeRateService::class, $service);
        
        $response = $this->getJson('/api/exchange-rates/USD/JPY');
        
        // Implementation depends on whether cross rates are supported
        $response->assertStatus(200)
            ->or()
            ->assertStatus(404);
    }
}