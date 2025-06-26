<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Domain');
uses(TestCase::class, RefreshDatabase::class)->in('Console');

// Use InteractsWithFilament trait for Filament tests
uses(\Tests\Traits\InteractsWithFilament::class)->in('Feature/Filament');

/*
|--------------------------------------------------------------------------
| Parallel Testing Configuration
|--------------------------------------------------------------------------
|
| Configure parallel testing to ensure proper isolation between test processes.
| This is especially important for event sourcing and Redis-based features.
|
*/

// Ensure database transactions are properly isolated in parallel tests
beforeEach(function () {
    // Additional setup for parallel testing can be added here if needed
})->in('Feature', 'Domain', 'Console');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
