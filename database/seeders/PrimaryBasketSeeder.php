<?php

namespace Database\Seeders;

use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Seeder;

class PrimaryBasketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the primary basket asset
        $basket = BasketAsset::create([
            'code' => config('baskets.primary_code', 'PRIMARY'),
            'name' => config('baskets.primary_name', 'Primary Currency Basket'),
            'type' => 'fixed',
            'rebalance_frequency' => 'monthly',
            'is_active' => true,
            'created_by' => 'system',
            'metadata' => [
                'description' => config('baskets.primary_description', 'Platform primary currency basket'),
                'voting_enabled' => true,
                'next_rebalance' => now()->addMonth()->startOfMonth()->toDateString(),
            ]
        ]);

        // Add basket components with proposed weights
        $components = [
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 30.0],
            ['asset_code' => 'GBP', 'weight' => 15.0],
            ['asset_code' => 'CHF', 'weight' => 10.0],
            ['asset_code' => 'JPY', 'weight' => 3.0],
            ['asset_code' => 'XAU', 'weight' => 2.0],
        ];

        foreach ($components as $component) {
            $basket->components()->create([
                'asset_code' => $component['asset_code'],
                'weight' => $component['weight'],
                'is_active' => true,
            ]);
        }

        // Create the primary basket asset entry
        $asset = Asset::firstOrCreate(
            ['code' => config('baskets.primary_code', 'PRIMARY')],
            [
                'name' => config('baskets.primary_name', 'Primary Currency Basket'),
                'type' => 'custom',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => true,
                'metadata' => [
                    'symbol' => config('baskets.primary_symbol', '$'),
                    'basket_code' => config('baskets.primary_code', 'PRIMARY'),
                    'description' => config('baskets.primary_description', 'Platform primary currency basket'),
                ]
            ]
        );

        $this->command->info('Primary basket created successfully with 6 currency components.');
    }
}