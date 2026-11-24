<?php

declare(strict_types=1);

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;

/*
 * Exchange rates are stored as decimals and come back as strings, and the
 * controller rounded them with strict types on, so /api/v2/gcu/composition
 * answered 500 as soon as the demo had a GCU basket and rates.
 */

it('lists the GCU composition with prices from stored decimal rates', function (): void {
    foreach (['USD' => 'US Dollar', 'EUR' => 'Euro'] as $code => $name) {
        Asset::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    }
    ExchangeRate::query()->create(['from_asset_code' => 'EUR', 'to_asset_code' => 'USD', 'rate' => '1.0800000000', 'source' => 'manual', 'valid_at' => now()->subMinute(), 'is_active' => true]);

    $gcu = BasketAsset::query()->where('code', 'GCU')->first() ?? BasketAsset::factory()->create(['code' => 'GCU', 'name' => 'Global Currency Unit']);
    $gcu->components()->delete();
    $gcu->components()->create(['asset_code' => 'USD', 'weight' => 60.0, 'min_weight' => 50.0, 'max_weight' => 70.0, 'is_active' => true]);
    $gcu->components()->create(['asset_code' => 'EUR', 'weight' => 40.0, 'min_weight' => 30.0, 'max_weight' => 50.0, 'is_active' => true]);
    BasketValue::factory()->create(['basket_asset_code' => 'GCU', 'value' => 1.032, 'calculated_at' => now(), 'component_values' => [
        'USD' => ['weight' => 60, 'weighted_value' => 0.6],
        'EUR' => ['weight' => 40, 'weighted_value' => 0.432],
    ]]);

    $this->getJson('/api/v2/gcu/composition')
        ->assertOk()
        ->assertJsonFragment(['asset_code' => 'EUR', 'current_price_usd' => 1.08]);
});
