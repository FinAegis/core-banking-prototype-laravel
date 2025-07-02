<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GCUControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_displays_the_gcu_page()
    {
        // Mock the HTTP response
        Http::fake([
            '*/api/v2/gcu/composition' => Http::response([
                'composition' => [
                    ['currency' => 'USD', 'weight' => 40.00, 'flag' => '🇺🇸'],
                    ['currency' => 'EUR', 'weight' => 30.00, 'flag' => '🇪🇺'],
                    ['currency' => 'GBP', 'weight' => 15.00, 'flag' => '🇬🇧'],
                    ['currency' => 'JPY', 'weight' => 10.00, 'flag' => '🇯🇵'],
                    ['currency' => 'CNY', 'weight' => 5.00, 'flag' => '🇨🇳'],
                ],
                'performance' => [
                    'value' => 1.0234,
                    'change_24h' => 0.15,
                    'change_7d' => 0.89,
                    'change_30d' => 2.34,
                ],
                'last_updated' => now()->toIso8601String(),
            ], 200)
        ]);

        $response = $this->get(route('gcu'));

        $response->assertStatus(200);
        $response->assertViewIs('gcu.index');
        $response->assertViewHas('compositionData');
        
        $compositionData = $response->viewData('compositionData');
        $this->assertArrayHasKey('composition', $compositionData);
        $this->assertArrayHasKey('performance', $compositionData);
        $this->assertCount(5, $compositionData['composition']);
    }

    /** @test */
    public function it_caches_composition_data()
    {
        // First request should call the API
        Http::fake([
            '*/api/v2/gcu/composition' => Http::response([
                'composition' => config('platform.gcu.composition'),
                'performance' => ['value' => 1.0, 'change_24h' => 0],
                'last_updated' => now()->toIso8601String(),
            ], 200)
        ]);

        $this->get(route('gcu'));
        
        // Assert data is cached
        $this->assertTrue(Cache::has('gcu_composition'));
        
        // Clear HTTP fake to ensure no more requests are made
        Http::clearResolvedInstances();
        Http::fake([
            '*' => Http::response('Should not be called', 500)
        ]);
        
        // Second request should use cache
        $response = $this->get(route('gcu'));
        $response->assertStatus(200);
    }

    /** @test */
    public function it_falls_back_to_config_when_api_fails()
    {
        // Mock API failure
        Http::fake([
            '*/api/v2/gcu/composition' => Http::response(null, 500)
        ]);

        $response = $this->get(route('gcu'));

        $response->assertStatus(200);
        $response->assertViewHas('compositionData');
        
        $compositionData = $response->viewData('compositionData');
        $this->assertEquals(config('platform.gcu.composition'), $compositionData['composition']);
        $this->assertEquals(1.0, $compositionData['performance']['value']);
    }

    /** @test */
    public function it_handles_api_timeout_gracefully()
    {
        // Mock API timeout
        Http::fake([
            '*/api/v2/gcu/composition' => function () {
                throw new \Exception('Connection timeout');
            }
        ]);

        $response = $this->get(route('gcu'));

        $response->assertStatus(200);
        $response->assertViewHas('compositionData');
        
        // Should fall back to config data
        $compositionData = $response->viewData('compositionData');
        $this->assertArrayHasKey('composition', $compositionData);
        $this->assertArrayHasKey('performance', $compositionData);
    }

    /** @test */
    public function it_includes_correct_data_structure()
    {
        Http::fake([
            '*/api/v2/gcu/composition' => Http::response([
                'composition' => [
                    ['currency' => 'USD', 'weight' => 40.00, 'flag' => '🇺🇸'],
                ],
                'performance' => [
                    'value' => 1.0234,
                    'change_24h' => 0.15,
                    'change_7d' => 0.89,
                    'change_30d' => 2.34,
                ],
                'last_updated' => '2024-01-15T10:30:00Z',
            ], 200)
        ]);

        $response = $this->get(route('gcu'));
        $compositionData = $response->viewData('compositionData');
        
        // Check composition structure
        $this->assertArrayHasKey('currency', $compositionData['composition'][0]);
        $this->assertArrayHasKey('weight', $compositionData['composition'][0]);
        $this->assertArrayHasKey('flag', $compositionData['composition'][0]);
        
        // Check performance structure
        $this->assertArrayHasKey('value', $compositionData['performance']);
        $this->assertArrayHasKey('change_24h', $compositionData['performance']);
        $this->assertArrayHasKey('change_7d', $compositionData['performance']);
        $this->assertArrayHasKey('change_30d', $compositionData['performance']);
        
        // Check timestamp
        $this->assertArrayHasKey('last_updated', $compositionData);
    }
}