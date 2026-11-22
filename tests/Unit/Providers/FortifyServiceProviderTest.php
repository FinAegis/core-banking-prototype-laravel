<?php

use App\Providers\FortifyServiceProvider;
use Illuminate\Foundation\Application;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function () {
    // A separate mock, so the test case keeps its real application for the
    // framework's own teardown.
    $this->mockApp = Mockery::mock(Application::class);
    $this->provider = new FortifyServiceProvider($this->mockApp);
});

it('can instantiate fortify service provider', function () {
    expect($this->provider)->toBeInstanceOf(FortifyServiceProvider::class);
});

it('has register method that can be called', function () {
    // Test that register method exists and can be called without errors
    expect(function () {
        $this->provider->register();
    })->not->toThrow(Exception::class);
});

it('has boot method', function () {
    // Just test that the boot method exists
    expect((new ReflectionClass($this->provider))->hasMethod('boot'))->toBeTrue();
});
