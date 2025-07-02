<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Asset;
use App\Domain\Exchange\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        Asset::create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::create(['code' => 'EUR', 'name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::create(['code' => 'GBP', 'name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::create(['code' => 'GCU', 'name' => 'Global Currency Unit', 'type' => 'basket', 'precision' => 2, 'is_active' => true]);
        Asset::create(['code' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);
    }

    /** @test */
    public function authenticated_user_can_view_exchange_rates()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index'));
        
        $response->assertOk();
        $response->assertViewIs('exchange-rates.index');
        $response->assertViewHas(['assets', 'baseCurrency', 'selectedAssets', 'rates', 'historicalData', 'statistics']);
    }

    /** @test */
    public function guest_cannot_view_exchange_rates()
    {
        $response = $this->get(route('exchange-rates.index'));
        
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function can_get_exchange_rates_via_ajax()
    {
        // Create some exchange rates
        ExchangeRate::create([
            'base_asset_code' => 'USD',
            'target_asset_code' => 'EUR',
            'rate' => 0.92,
            'provider' => 'test',
        ]);
        
        ExchangeRate::create([
            'base_asset_code' => 'USD',
            'target_asset_code' => 'GBP',
            'rate' => 0.79,
            'provider' => 'test',
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

    /** @test */
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
                'base_asset_code' => 'USD',
                'target_asset_code' => 'EUR',
                'rate' => 0.92 + (rand(-10, 10) / 1000),
                'provider' => 'test',
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

    /** @test */
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

    /** @test */
    public function can_filter_by_base_currency()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index', ['base' => 'EUR']));
        
        $response->assertOk();
        $response->assertViewHas('baseCurrency', 'EUR');
    }

    /** @test */
    public function can_select_specific_assets_to_display()
    {
        $response = $this->actingAs($this->user)->get(route('exchange-rates.index', [
            'base' => 'USD',
            'assets' => ['EUR', 'GCU'],
        ]));
        
        $response->assertOk();
        $response->assertViewHas('selectedAssets', ['EUR', 'GCU']);
    }

    /** @test */
    public function handles_missing_exchange_rate_gracefully()
    {
        // No exchange rates in database
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

    /** @test */
    public function calculates_24h_change_correctly()
    {
        // Create rate from 24h ago
        ExchangeRate::create([
            'base_asset_code' => 'USD',
            'target_asset_code' => 'EUR',
            'rate' => 0.90,
            'provider' => 'test',
            'created_at' => now()->subDay(),
        ]);
        
        // Create current rate
        ExchangeRate::create([
            'base_asset_code' => 'USD',
            'target_asset_code' => 'EUR',
            'rate' => 0.92,
            'provider' => 'test',
        ]);
        
        $response = $this->actingAs($this->user)
            ->postJson(route('exchange-rates.rates'), [
                'base' => 'USD',
                'assets' => ['EUR'],
            ]);
        
        $response->assertOk();
        $eurRate = $response->json('rates.EUR');
        
        // Should show +0.02 change (2.22% increase)
        $this->assertEquals(0.02, $eurRate['change_24h']);
        $this->assertGreaterThan(2, $eurRate['change_percent']);
    }
}