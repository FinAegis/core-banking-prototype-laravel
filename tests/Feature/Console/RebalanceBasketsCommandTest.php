<?php

declare(strict_types=1);

use App\Models\BasketAsset;
use App\Domain\Basket\Services\BasketRebalancingService;

beforeEach(function () {
    // Create a dynamic basket for testing using direct creation to avoid factory callbacks
    $this->basket = BasketAsset::create([
        'code' => 'TEST_' . substr(uniqid(), 0, 8),
        'name' => 'Test Basket',
        'type' => 'dynamic',
        'rebalance_frequency' => 'monthly',
        'is_active' => true,
        'last_rebalanced_at' => now()->subMonth()->subDay(), // Make it need rebalancing
    ]);
    
    // Add components manually
    $this->basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 50.0, 'min_weight' => 40.0, 'max_weight' => 60.0, 'is_active' => true],
        ['asset_code' => 'EUR', 'weight' => 30.0, 'min_weight' => 20.0, 'max_weight' => 40.0, 'is_active' => true],
        ['asset_code' => 'GBP', 'weight' => 20.0, 'min_weight' => 10.0, 'max_weight' => 30.0, 'is_active' => true],
    ]);
});

it('can run basket rebalancing command', function () {
    $this->artisan('baskets:rebalance')
        ->expectsOutput('Checking all dynamic baskets for rebalancing...')
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->expectsOutputToContain('Completed.')
        ->assertSuccessful();
});

it('can rebalance specific basket', function () {
    $this->artisan('baskets:rebalance', ['--basket' => $this->basket->code])
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->assertSuccessful();
});

it('handles non-existent basket', function () {
    $this->artisan('baskets:rebalance', ['--basket' => 'NON_EXISTENT'])
        ->expectsOutput("Basket with code 'NON_EXISTENT' not found.")
        ->assertFailed();
});

it('handles fixed basket type', function () {
    $fixedBasket = BasketAsset::factory()->create([
        'code' => 'FIXED_BASKET',
        'name' => 'Fixed Basket',
        'type' => 'fixed',
        'is_active' => true,
    ]);
    
    $this->artisan('baskets:rebalance', ['--basket' => 'FIXED_BASKET'])
        ->expectsOutput("Basket 'FIXED_BASKET' is not a dynamic basket.")
        ->assertFailed();
});

it('can force rebalancing', function () {
    // Update basket to not need rebalancing
    $this->basket->update(['last_rebalanced_at' => now()]);
    
    $this->artisan('baskets:rebalance', ['--basket' => $this->basket->code, '--force' => true])
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->assertSuccessful();
});

it('skips baskets that do not need rebalancing', function () {
    // Update basket to not need rebalancing
    $this->basket->update(['last_rebalanced_at' => now()]);
    
    $this->artisan('baskets:rebalance', ['--basket' => $this->basket->code])
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->expectsOutput('Basket does not need rebalancing yet. Use --force to override.')
        ->assertSuccessful();
});

it('can run in dry-run mode', function () {
    $this->artisan('baskets:rebalance', ['--basket' => $this->basket->code, '--dry-run' => true])
        ->expectsOutput('Running in dry-run mode - no changes will be made')
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->expectsOutput('Simulation results for Test Basket:')
        ->assertSuccessful();
});

it('handles no dynamic baskets scenario', function () {
    // Delete all dynamic baskets
    BasketAsset::where('type', 'dynamic')->delete();
    
    $this->artisan('baskets:rebalance')
        ->expectsOutput('Checking all dynamic baskets for rebalancing...')
        ->expectsOutput('No dynamic baskets found.')
        ->assertSuccessful();
});

it('processes multiple baskets', function () {
    // Create another dynamic basket
    $anotherBasket = BasketAsset::create([
        'code' => 'OTHER_' . substr(uniqid(), 0, 8),
        'name' => 'Another Basket',
        'type' => 'dynamic',
        'rebalance_frequency' => 'monthly',
        'is_active' => true,
        'last_rebalanced_at' => now()->subMonth()->subDay(),
    ]);
    
    $anotherBasket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 60.0, 'is_active' => true],
        ['asset_code' => 'EUR', 'weight' => 40.0, 'is_active' => true],
    ]);
    
    $this->artisan('baskets:rebalance')
        ->expectsOutput('Checking all dynamic baskets for rebalancing...')
        ->expectsOutputToContain('Processing basket: Test Basket')
        ->expectsOutputToContain('Processing basket: Another Basket')
        ->expectsOutputToContain('Completed.')
        ->assertSuccessful();
});

it('ignores inactive baskets', function () {
    // Make basket inactive
    $this->basket->update(['is_active' => false]);
    
    $this->artisan('baskets:rebalance')
        ->expectsOutput('Checking all dynamic baskets for rebalancing...')
        ->expectsOutput('No dynamic baskets found.')
        ->assertSuccessful();
});