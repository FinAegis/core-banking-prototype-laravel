<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class DemoEnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Force demo environment for these tests
        App::detectEnvironment(fn () => 'demo');
        
        // Re-bootstrap the app to apply demo configurations
        $this->app->bootstrapWith([
            \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
            \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
            \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
            \Illuminate\Foundation\Bootstrap\BootProviders::class,
        ]);
    }

    /** @test */
    public function it_treats_demo_environment_as_production()
    {
        $this->assertEquals('demo', App::environment());
        $this->assertFalse(config('app.debug'));
        $this->assertNotEmpty(config('app.debug_blacklist'));
    }

    /** @test */
    public function it_loads_demo_configuration()
    {
        $this->assertIsArray(config('demo'));
        $this->assertArrayHasKey('features', config('demo'));
        $this->assertArrayHasKey('restrictions', config('demo'));
        $this->assertArrayHasKey('rate_limits', config('demo'));
    }

    /** @test */
    public function it_applies_demo_rate_limits()
    {
        $this->assertEquals(60, config('app.rate_limits.api'));
        $this->assertEquals(10, config('app.rate_limits.transactions'));
    }

    /** @test */
    public function it_has_demo_restrictions()
    {
        $this->assertIsInt(config('demo.restrictions.max_transaction_amount'));
        $this->assertIsInt(config('demo.restrictions.max_accounts_per_user'));
        $this->assertIsBool(config('demo.restrictions.disable_real_banks'));
    }

    /** @test */
    public function it_does_not_expose_sensitive_data_in_demo()
    {
        $debugBlacklist = config('app.debug_blacklist._ENV', []);
        
        $this->assertContains('APP_KEY', $debugBlacklist);
        $this->assertContains('DB_PASSWORD', $debugBlacklist);
        $this->assertContains('REDIS_PASSWORD', $debugBlacklist);
    }

    protected function tearDown(): void
    {
        // Reset to testing environment
        App::detectEnvironment(fn () => 'testing');
        
        parent::tearDown();
    }
}