<?php

declare(strict_types=1);

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;

// basket_values keys a value by basket_asset_code. The GCU endpoints looked
// it up by a "basket_code" column that does not exist: MySQL rejects that,
// and SQLite reads it as a string literal, so no value was ever found.

it('returns the latest stored GCU value', function (): void {
    BasketAsset::factory()->create(['code' => 'GCU', 'name' => 'Global Currency Unit', 'type' => 'fixed']);
    BasketValue::create([
        'basket_asset_code' => 'GCU',
        'value'             => 1.2345,
        'calculated_at'     => now(),
        'component_values'  => [],
    ]);

    $this->getJson('/api/v2/gcu')
        ->assertOk()
        ->assertJsonPath('data.current_value', 1.2345);
});
