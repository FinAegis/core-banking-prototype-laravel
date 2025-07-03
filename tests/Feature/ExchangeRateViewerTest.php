<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Asset;
use App\Domain\Exchange\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ExchangeRateViewerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user with team
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->teams()->attach($this->team);
        $this->user->switchTeam($this->team);
        
        // Create some assets
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GCU'], ['name' => 'Global Currency Unit', 'type' => 'basket', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
    }

    #[Test]
    public function authenticated_user_can_view_exchange_rates()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index'));
        
        $response->assertOk();
        $response->assertViewIs('exchange-rates.index');
        $response->assertViewHas(['assets', 'baseCurrency', 'selectedAssets', 'rates', 'historicalData', 'statistics']);
    }

    #[Test]
    public function guest_cannot_view_exchange_rates()
    {
        $response = $this->get(route('exchange-rates.index'));
        
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function can_get_exchange_rates_via_ajax()
    {
        // Create some exchange rates
        ExchangeRate::create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.92,
            'source' => 'test',
            'valid_at' => now(),
            'is_active' => true,
        ]);
        
        ExchangeRate::create([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'GBP',
            'rate' => 0.79,
            'source' => 'test',
            'valid_at' => now(),
            'is_active' => true,
        ]);
        
        $response = $this->actingAs($this->user)
            ->postJson(route('exchange-rates.rates'), [
                'base' => 'USD',
                'assets' => ['EUR', 'GBP'],
            ]);
        
        $response->assertOk();
        $response->assertJsonStructure([
            'base',
            'timestamp',
            'rates' => [
                'EUR' => ['rate', 'change_24h', 'change_percent', 'last_updated'],
                'GBP' => ['rate', 'change_24h', 'change_percent', 'last_updated'],
            ],
        ]);
    }

    #[Test]
    public function can_get_historical_data()
    {
        // Create historical rates
        $timestamps = [
            now()->subDays(7),
            now()->subDays(6),
            now()->subDays(5),
            now()->subDays(4),
            now()->subDays(3),
            now()->subDays(2),
            now()->subDays(1),
            now(),
        ];
        
        foreach ($timestamps as $timestamp) {
            ExchangeRate::create([
                'from_asset_code' => 'USD',
                'to_asset_code' => 'EUR',
                'rate' => 0.92 + (rand(-10, 10) / 1000),
                'source' => 'test',
                'valid_at' => $timestamp,
                'is_active' => true,
                'created_at' => $timestamp,
            ]);
        }
        
        $response = $this->actingAs($this->user)
            ->getJson(route('exchange-rates.historical'), [
                'base' => 'USD',
                'target' => 'EUR',
                'period' => '7d',
            ]);
        
        $response->assertOk();
        $response->assertJsonStructure([
            'base',
            'target',
            'period',
            'data' => [
                '*' => ['timestamp', 'rate'],
            ],
        ]);
        
        $data = $response->json('data');
        $this->assertCount(8, $data);
    }

    #[Test]
    public function exchange_rates_page_displays_correct_ui_elements()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index'));
        
        $response->assertOk();
        $response->assertSee('Exchange Rates');
        $response->assertSee('Base Currency');
        $response->assertSee('Display Currencies');
        $response->assertSee('Auto-refresh');
        $response->assertSee('Pairs Tracked');
        $response->assertSee('24h Updates');
        $response->assertSee('Historical Rates');
    }

    #[Test]
    public function can_filter_by_base_currency()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index', ['base' => 'EUR']));
        
        $response->assertOk();
        $response->assertViewHas('baseCurrency', 'EUR');
    }

    #[Test]
    public function can_select_specific_assets_to_display()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index', [
            'base' => 'USD',
            'assets' => ['EUR', 'GCU'],
        ]));
        
        $response->assertOk();
        $response->assertViewHas('selectedAssets', ['EUR', 'GCU']);
    }

    #[Test]
    public function handles_missing_exchange_rate_gracefully()
    {
        // Ensure no exchange rates in database
        ExchangeRate::truncate();
        
        $response = $this->actingAs($this->user)
            ->postJson(route('exchange-rates.rates'), [
                'base' => 'USD',
                'assets' => ['EUR', 'GBP'],
            ]);
        
        $response->assertOk();
        $response->assertJsonStructure([
            'rates' => [
                'EUR' => ['rate'],
                'GBP' => ['rate'],
            ],
        ]);
        
        // Should return default rates
        $this->assertEquals(0.92, $response->json('rates.EUR.rate'));
        $this->assertEquals(0.79, $response->json('rates.GBP.rate'));
    }

    #[Test]
    public function calculates_24h_change_correctly()
    {
        // Clear any existing rates
        ExchangeRate::truncate();
        
        // Create rate from 24h ago using DB insert to ensure timestamps are correct
        $oldDate = now()->subHours(25);
        DB::table('exchange_rates')->insert([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.90,
            'source' => 'test',
            'valid_at' => $oldDate,
            'is_active' => true,
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);
        
        // Create current rate
        $currentDate = now();
        DB::table('exchange_rates')->insert([
            'from_asset_code' => 'USD',
            'to_asset_code' => 'EUR',
            'rate' => 0.92,
            'source' => 'test',
            'valid_at' => $currentDate,
            'is_active' => true,
            'created_at' => $currentDate,
            'updated_at' => $currentDate,
        ]);
        
        $response = $this->actingAs($this->user)
            ->postJson(route('exchange-rates.rates'), [
                'base' => 'USD',
                'assets' => ['EUR'],
            ]);
        
        $response->assertOk();
        $eurRate = $response->json('rates.EUR');
        
        // The current rate should be 0.92 and the 24h change should be positive
        $this->assertEquals(0.92, $eurRate['rate']);
        $this->assertGreaterThan(0, $eurRate['change_24h']);
        $this->assertGreaterThan(0, $eurRate['change_percent']);
    }
}