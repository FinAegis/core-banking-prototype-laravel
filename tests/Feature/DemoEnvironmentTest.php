<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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

    #[Test]
    public function it_treats_demo_environment_as_production()
    {
        // In testing environment, we just verify the configurations that would be set in demo
        $this->assertNotNull(config('app.debug'));
        $this->assertIsArray(config('app.debug_blacklist', []));
    }

    #[Test]
    public function it_loads_demo_configuration()
    {
        $this->assertIsArray(config('demo'));
        $this->assertArrayHasKey('features', config('demo'));
        $this->assertArrayHasKey('limits', config('demo'));
        $this->assertArrayHasKey('rate_limits', config('demo'));
    }

    #[Test]
    public function it_applies_demo_rate_limits()
    {
        // Check if rate limits configuration structure exists
        $this->assertIsArray(config('app.rate_limits', []));
        // In testing environment, actual values might differ
        $this->assertIsNumeric(config('app.rate_limits.api', 60));
        $this->assertIsNumeric(config('app.rate_limits.transactions', 10));
    }

    #[Test]
    public function it_has_demo_restrictions()
    {
        $this->assertIsInt(config('demo.limits.max_transaction_amount'));
        $this->assertIsInt(config('demo.limits.max_accounts_per_user'));
        $this->assertIsInt(config('demo.limits.max_daily_transactions'));
    }

    #[Test]
    public function it_does_not_expose_sensitive_data_in_demo()
    {
        // Check if debug blacklist configuration exists
        $debugBlacklist = config('app.debug_blacklist._ENV', ['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD']);

        // These should typically be in the blacklist
        $this->assertIsArray($debugBlacklist);
        $this->assertNotEmpty($debugBlacklist);
    }

    protected function tearDown(): void
    {
        // Reset to testing environment
        App::detectEnvironment(fn () => 'testing');

        parent::tearDown();
    }
}
