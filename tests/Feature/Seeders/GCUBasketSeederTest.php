<?php

use Database\Seeders\GCUBasketSeeder;
use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Set GCU environment variables
    config([
        'baskets.primary_code' => 'GCU',
        'baskets.primary_name' => 'Global Currency Unit',
        'baskets.primary_symbol' => 'Ǥ',
        'baskets.primary_description' => 'Global Currency Unit - A stable, diversified currency basket',
    ]);
    
    // Ensure all required assets exist
    $assets = ['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'XAU', 'CAD'];
    foreach ($assets as $code) {
        Asset::firstOrCreate(
            ['code' => $code],
            [
                'name' => $code . ' Currency',
                'type' => in_array($code, ['XAU']) ? 'commodity' : 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );
    }
});

it('creates GCU basket with correct configuration', function () {
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    $basket = BasketAsset::where('code', 'GCU')->first();
    
    expect($basket)->not->toBeNull();
    expect($basket->name)->toBe('Global Currency Unit');
    expect($basket->type)->toBe('dynamic');
    expect($basket->rebalance_frequency)->toBe('monthly');
    expect($basket->is_active)->toBeTrue();
    expect($basket->metadata['implementation'])->toBe('GCU');
    expect($basket->metadata['voting_enabled'])->toBeTrue();
});

it('creates six basket components with correct weights', function () {
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    $basket = BasketAsset::where('code', 'GCU')->first();
    $components = $basket->components;
    
    expect($components)->toHaveCount(6);
    
    $expectedComponents = [
        'USD' => ['weight' => 40.0, 'min' => 30.0, 'max' => 50.0],
        'EUR' => ['weight' => 30.0, 'min' => 20.0, 'max' => 40.0],
        'GBP' => ['weight' => 15.0, 'min' => 10.0, 'max' => 20.0],
        'CHF' => ['weight' => 10.0, 'min' => 5.0, 'max' => 15.0],
        'JPY' => ['weight' => 3.0, 'min' => 0.0, 'max' => 10.0],
        'XAU' => ['weight' => 2.0, 'min' => 0.0, 'max' => 5.0],
    ];
    
    foreach ($expectedComponents as $code => $expected) {
        $component = $components->where('asset_code', $code)->first();
        expect($component)->not->toBeNull();
        expect($component->weight)->toBe($expected['weight']);
        expect($component->min_weight)->toBe($expected['min']);
        expect($component->max_weight)->toBe($expected['max']);
        expect($component->is_active)->toBeTrue();
    }
    
    // Verify weights sum to 100
    $totalWeight = $components->sum('weight');
    expect($totalWeight)->toBe(100.0);
});

it('creates GCU asset entry', function () {
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    $asset = Asset::where('code', 'GCU')->first();
    
    expect($asset)->not->toBeNull();
    expect($asset->name)->toBe('Global Currency Unit');
    expect($asset->type)->toBe('custom');
    expect($asset->precision)->toBe(2);
    expect($asset->is_active)->toBeTrue();
    expect($asset->is_basket)->toBeTrue();
    expect($asset->metadata['symbol'])->toBe('Ǥ');
    expect($asset->metadata['implementation'])->toBe('GCU');
});

it('updates existing basket if run multiple times', function () {
    // Run seeder first time
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    // Modify the basket
    $basket = BasketAsset::where('code', 'GCU')->first();
    $basket->update(['name' => 'Modified Name']);
    
    // Run seeder again
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    // Check basket was updated
    $basket->refresh();
    expect($basket->name)->toBe('Global Currency Unit');
    
    // Ensure only one basket exists
    expect(BasketAsset::where('code', 'GCU')->count())->toBe(1);
});

it('removes old components when updating', function () {
    // Create basket with extra component
    $basket = BasketAsset::create([
        'code' => 'GCU',
        'name' => 'Old GCU',
        'type' => 'fixed',
        'rebalance_frequency' => 'daily',
    ]);
    
    $basket->components()->create([
        'asset_code' => 'CAD',
        'weight' => 5.0,
    ]);
    
    expect($basket->components)->toHaveCount(1);
    
    // Run seeder
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    // Check old component was removed
    $basket->refresh();
    expect($basket->components)->toHaveCount(6);
    expect($basket->components()->where('asset_code', 'CAD')->exists())->toBeFalse();
});

it('uses environment configuration', function () {
    // Override config
    config([
        'baskets.primary_code' => 'TEST',
        'baskets.primary_name' => 'Test Basket',
        'baskets.primary_symbol' => 'T',
        'baskets.primary_description' => 'Test Description',
    ]);
    
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    $basket = BasketAsset::where('code', 'TEST')->first();
    expect($basket)->not->toBeNull();
    expect($basket->name)->toBe('Test Basket');
    
    $asset = Asset::where('code', 'TEST')->first();
    expect($asset)->not->toBeNull();
    expect($asset->metadata['symbol'])->toBe('T');
    expect($asset->metadata['description'])->toBe('Test Description');
});

it('sets next rebalance date', function () {
    Artisan::call('db:seed', ['--class' => GCUBasketSeeder::class]);
    
    $basket = BasketAsset::where('code', 'GCU')->first();
    $nextRebalance = $basket->metadata['next_rebalance'];
    
    expect($nextRebalance)->not->toBeNull();
    expect(Carbon\Carbon::parse($nextRebalance)->isAfter(now()))->toBeTrue();
    expect(Carbon\Carbon::parse($nextRebalance)->day)->toBe(1); // First day of month
});

it('outputs success messages', function () {
    $this->artisan('db:seed', ['--class' => GCUBasketSeeder::class])
        ->expectsOutput('GCU basket created successfully with 6 currency components.')
        ->expectsOutput('Basket code: GCU')
        ->expectsOutput('Basket name: Global Currency Unit')
        ->assertSuccessful();
});