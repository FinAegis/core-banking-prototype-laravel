<?php

use App\Providers\AppServiceProvider;
use App\Providers\WaterlineServiceProvider;
use Illuminate\Foundation\Application;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function () {
    $this->app = Mockery::mock(Application::class);
    $this->provider = new AppServiceProvider($this->app);
    
    // Add flush method expectation for tearDown
    $this->app->shouldReceive('flush')->andReturnNull();
});

it('can instantiate app service provider', function () {
    expect($this->provider)->toBeInstanceOf(AppServiceProvider::class);
});

it('registers WaterlineServiceProvider in non-testing environment', function () {
    // Mock non-testing environment
    $this->app->shouldReceive('environment')->once()->andReturn('production');
    
    // Expect the WaterlineServiceProvider to be registered
    $this->app->shouldReceive('register')->once()->with(WaterlineServiceProvider::class);
    
    // Expect strategy bindings
    $this->app->shouldReceive('bind')->times(3);
    
    $this->provider->register();
});

it('does not register WaterlineServiceProvider in testing environment', function () {
    // Mock testing environment
    $this->app->shouldReceive('environment')->once()->andReturn('testing');
    
    // Should not call register for WaterlineServiceProvider
    $this->app->shouldNotReceive('register');
    
    // Expect strategy bindings in testing environment too
    $this->app->shouldReceive('bind')->times(3);
    
    $this->provider->register();
});

it('has boot method that can be called', function () {
    // Mock environment check
    $this->app->shouldReceive('environment')->with('demo')->andReturn(false);
    
    // Test that boot method exists and can be called without errors
    expect(function () {
        $this->provider->boot();
    })->not->toThrow(Exception::class);
});