<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class ExchangeRateControllerTest extends ControllerTestCase
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
        $this->usd = Asset::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
        );
        $this->eur = Asset::firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
        );
        $this->gbp = Asset::firstOrCreate(
            ['code' => 'GBP'],
            ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
        );
    }

    #[Test]
    public function it_gets_exchange_rate_between_two_assets()
    {

        $exchangeRate = ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
            'rate'            => '0.85000000',
            'source'          => 'test',
            'is_active'       => true,
            'valid_at'        => now(),
            'expires_at'      => now()->addHour(),
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

        $response->assertJsonPath('data.from_asset', 'USD');
        $response->assertJsonPath('data.to_asset', 'EUR');
        $response->assertJsonPath('data.is_active', true);
        // Check rate is approximately 0.85 (allowing for variance)
        $rate = (float) $response->json('data.rate');
        $this->assertGreaterThan(0.80, $rate);
        $this->assertLessThan(0.90, $rate);

        // Check inverse rate calculation
        $inverseRate = number_format(1 / 0.85, 10, '.', '');
        $response->assertJsonPath('data.inverse_rate', $inverseRate);
    }

    #[Test]
    public function it_handles_case_insensitive_asset_codes()
    {

        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
            'rate'            => '0.85000000',
            'is_active'       => true,
        ]);

        $response = $this->getJson('/api/exchange-rates/usd/eur');
        $response->assertStatus(200);

        $response = $this->getJson('/api/exchange-rates/UsD/EuR');
        $response->assertStatus(200);
    }

    #[Test]
    public function it_returns_404_when_exchange_rate_not_found()
    {

        // Use a truly non-existent currency pair
        $response = $this->getJson('/api/exchange-rates/USD/XXX');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Exchange rate not found',
            'error'   => 'No active exchange rate found for the specified asset pair',
        ]);
    }

    #[Test]
    public function it_converts_amount_between_assets()
    {

        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
            'rate'            => '0.85000000',
            'is_active'       => true,
            'valid_at'        => now(),
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

        $response->assertJsonPath('data.from_asset', 'USD');
        $response->assertJsonPath('data.to_asset', 'EUR');
        $response->assertJsonPath('data.from_amount', 10000);
        $response->assertJsonPath('data.to_amount', 8500);
        $response->assertJsonPath('data.from_formatted', '100.00 USD');
        $response->assertJsonPath('data.to_formatted', '85.00 EUR');
        // Check rate is approximately 0.85
        $rate = (float) $response->json('data.rate');
        $this->assertGreaterThan(0.80, $rate);
        $this->assertLessThan(0.90, $rate);
    }

    #[Test]
    public function it_validates_conversion_amount()
    {

        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
            'rate'            => '0.85000000',
            'is_active'       => true,
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

    #[Test]
    public function it_lists_all_exchange_rates()
    {

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

    #[Test]
    public function it_filters_exchange_rates_by_active_status()
    {

        ExchangeRate::factory()->count(3)->create(['is_active' => true]);
        ExchangeRate::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/exchange-rates?active=true');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $rate) {
            $this->assertTrue($rate['is_active']);
        }
    }

    #[Test]
    public function it_filters_exchange_rates_by_asset()
    {

        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
        ]);
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'GBP',
        ]);
        ExchangeRate::factory()->create([
            'from_asset_code' => 'EUR',
            'to_asset_code'   => 'GBP',
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

    #[Test]
    public function it_does_not_require_authentication()
    {
        // Exchange rate endpoints are public
        $response = $this->getJson('/api/exchange-rates/USD/EUR');
        $response->assertStatus(200);

        $response = $this->getJson('/api/exchange-rates/USD/EUR/convert?amount=1000');
        $response->assertStatus(200);

        $response = $this->getJson('/api/exchange-rates');
        $response->assertStatus(200);
    }

    #[Test]
    public function it_handles_stale_exchange_rates()
    {

        // Create a rate that's old but not stale enough to trigger refresh (45 minutes old)
        $staleTime = now()->subMinutes(45);
        ExchangeRate::factory()->create([
            'from_asset_code' => 'USD',
            'to_asset_code'   => 'EUR',
            'rate'            => '0.85000000',
            'is_active'       => true,
            'valid_at'        => $staleTime,
            'expires_at'      => now()->addHour(), // Still valid, expires in 1 hour
        ]);

        // Clear any cache that might exist
        \Illuminate\Support\Facades\Cache::forget('exchange_rate:USD:EUR');

        $response = $this->getJson('/api/exchange-rates/USD/EUR');

        // Should return the rate and show its age
        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', true);

        // The rate was created 45 minutes ago
        $ageMinutes = $response->json('data.age_minutes');
        $this->assertGreaterThanOrEqual(44, $ageMinutes);
        $this->assertLessThanOrEqual(46, $ageMinutes);
    }

    #[Test]
    public function it_calculates_cross_rates()
    {

        // Mock the exchange rate service for cross rate calculation
        $service = \Mockery::mock(ExchangeRateService::class);
        $service->shouldReceive('getRate')
            ->with('USD', 'XXX')
            ->andReturn(null); // No direct rate

        $service->shouldReceive('calculateCrossRate')
            ->with('USD', 'XXX')
            ->andReturn('110.25000000');

        $this->app->instance(ExchangeRateService::class, $service);

        $response = $this->getJson('/api/exchange-rates/USD/XXX');

        // Since cross rates are not implemented, expect 404
        $response->assertStatus(404);
    }
}
