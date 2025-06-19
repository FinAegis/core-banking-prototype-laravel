<?php

use App\Filament\Admin\Widgets\PrimaryBasketWidget;
use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('primary basket widget displays basket data when configured', function () {
    // Create or get assets
    $assetCodes = ['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'XAU'];
    $assetNames = [
        'USD' => 'US Dollar',
        'EUR' => 'Euro', 
        'GBP' => 'British Pound',
        'CHF' => 'Swiss Franc',
        'JPY' => 'Japanese Yen',
        'XAU' => 'Gold'
    ];
    
    foreach ($assetCodes as $code) {
        Asset::firstOrCreate(
            ['code' => $code],
            [
                'name' => $assetNames[$code],
                'type' => in_array($code, ['XAU']) ? 'commodity' : 'fiat',
                'precision' => $code === 'JPY' ? 0 : 2,
                'is_active' => true,
            ]
        );
    }
    
    // Create primary basket
    $basket = BasketAsset::create([
        'code' => 'PRIMARY',
        'name' => 'Primary Currency Basket',
        'type' => 'fixed',
        'rebalance_frequency' => 'monthly',
        'is_active' => true,
    ]);
    
    // Add components
    $basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 40.0, 'is_active' => true],
        ['asset_code' => 'EUR', 'weight' => 30.0, 'is_active' => true],
        ['asset_code' => 'GBP', 'weight' => 15.0, 'is_active' => true],
        ['asset_code' => 'CHF', 'weight' => 10.0, 'is_active' => true],
        ['asset_code' => 'JPY', 'weight' => 3.0, 'is_active' => true],
        ['asset_code' => 'XAU', 'weight' => 2.0, 'is_active' => true],
    ]);
    
    $widget = new PrimaryBasketWidget();
    $data = $widget->getBasketData();
    
    expect($data['exists'])->toBeTrue();
    expect($data['basket']->code)->toBe('PRIMARY');
    expect($data['currencies'])->toHaveCount(6);
    
    // Check first currency
    $usd = collect($data['currencies'])->firstWhere('code', 'USD');
    expect($usd['name'])->toBe('US Dollar');
    expect($usd['weight'])->toBe(40.0);
});

test('primary basket widget shows default composition when not configured', function () {
    $widget = new PrimaryBasketWidget();
    $data = $widget->getBasketData();
    
    expect($data['exists'])->toBeFalse();
    expect($data['currencies'])->toHaveCount(6);
    
    // Check default weights
    $currencies = collect($data['currencies']);
    expect($currencies->firstWhere('code', 'USD')['weight'])->toBe(40);
    expect($currencies->firstWhere('code', 'EUR')['weight'])->toBe(30);
    expect($currencies->firstWhere('code', 'GBP')['weight'])->toBe(15);
    expect($currencies->firstWhere('code', 'CHF')['weight'])->toBe(10);
    expect($currencies->firstWhere('code', 'JPY')['weight'])->toBe(3);
    expect($currencies->firstWhere('code', 'XAU')['weight'])->toBe(2);
});

test('primary basket widget has correct column span', function () {
    $widget = new PrimaryBasketWidget();
    
    expect($widget->getColumnSpan())->toBe('full');
});

test('primary basket widget has correct sort order', function () {
    $widget = new PrimaryBasketWidget();
    
    expect($widget->getSort())->toBe(1);
});

test('primary basket widget currencies sum to 100 percent', function () {
    $widget = new PrimaryBasketWidget();
    $data = $widget->getBasketData();
    
    $totalWeight = collect($data['currencies'])->sum('weight');
    
    expect($totalWeight)->toBe(100);
});