<?php

declare(strict_types=1);

use App\Domain\Basket\Models\BasketAsset;

it('returns success when there are no active baskets to process', function () {
    // Regression: a scheduled bulk run with nothing to do returned exit code 1,
    // which the scheduler logged as "command failed". An empty active-basket set
    // is a no-op, not a failure.
    $this->artisan('basket:calculate-performance')
        ->expectsOutputToContain('No active baskets')
        ->assertSuccessful();
});

it('fails when a specific basket is requested but not found', function () {
    // An explicit --basket=X that does not exist IS a real error (typo / bad
    // config) and must keep returning a non-zero exit code.
    $this->artisan('basket:calculate-performance', ['--basket' => 'DOES_NOT_EXIST'])
        ->assertFailed();
});

it('processes active baskets and succeeds', function () {
    BasketAsset::create([
        'code'      => 'TESTGCU',
        'name'      => 'Test Basket',
        'type'      => 'fixed',
        'is_active' => true,
    ]);

    $this->artisan('basket:calculate-performance')
        ->assertSuccessful();
});
