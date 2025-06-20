<?php

namespace Database\Seeders;

use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Seeder;

class GCUBasketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates the GCU (Global Currency Unit) basket as the primary basket
     * for this specific FinAegis implementation.
     */
    public function run(): void
    {
        // Use environment configuration to get GCU-specific values
        $basketCode = config('baskets.primary_code', env('PRIMARY_BASKET_CODE', 'GCU'));
        $basketName = config('baskets.primary_name', env('PRIMARY_BASKET_NAME', 'Global Currency Unit'));
        $basketSymbol = config('baskets.primary_symbol', env('PRIMARY_BASKET_SYMBOL', 'Ǥ'));
        $basketDescription = config('baskets.primary_description', env('PRIMARY_BASKET_DESCRIPTION', 'Global Currency Unit - A stable, diversified currency basket'));

        // Create the GCU basket asset
        $basket = BasketAsset::updateOrCreate(
            ['code' => $basketCode],
            [
                'name' => $basketName,
                'type' => 'dynamic', // GCU uses dynamic rebalancing
                'rebalance_frequency' => 'monthly',
                'is_active' => true,
                'created_by' => 'system',
                'metadata' => [
                    'description' => $basketDescription,
                    'voting_enabled' => true,
                    'next_rebalance' => now()->addMonth()->startOfMonth()->toDateString(),
                    'implementation' => 'GCU',
                    'version' => '1.0',
                ]
            ]
        );

        // Remove existing components if updating
        $basket->components()->delete();

        // Add GCU basket components with initial weights
        $components = [
            ['asset_code' => 'USD', 'weight' => 40.0, 'min_weight' => 30.0, 'max_weight' => 50.0],
            ['asset_code' => 'EUR', 'weight' => 30.0, 'min_weight' => 20.0, 'max_weight' => 40.0],
            ['asset_code' => 'GBP', 'weight' => 15.0, 'min_weight' => 10.0, 'max_weight' => 20.0],
            ['asset_code' => 'CHF', 'weight' => 10.0, 'min_weight' => 5.0, 'max_weight' => 15.0],
            ['asset_code' => 'JPY', 'weight' => 3.0, 'min_weight' => 0.0, 'max_weight' => 10.0],
            ['asset_code' => 'XAU', 'weight' => 2.0, 'min_weight' => 0.0, 'max_weight' => 5.0],
        ];

        foreach ($components as $component) {
            $basket->components()->create([
                'asset_code' => $component['asset_code'],
                'weight' => $component['weight'],
                'min_weight' => $component['min_weight'],
                'max_weight' => $component['max_weight'],
                'is_active' => true,
            ]);
        }

        // Create or update the GCU asset entry
        Asset::updateOrCreate(
            ['code' => $basketCode],
            [
                'name' => $basketName,
                'type' => 'custom',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => true,
                'metadata' => [
                    'symbol' => $basketSymbol,
                    'basket_code' => $basketCode,
                    'description' => $basketDescription,
                    'implementation' => 'GCU',
                ]
            ]
        );

        $this->command->info("GCU basket created successfully with 6 currency components.");
        $this->command->info("Basket code: {$basketCode}");
        $this->command->info("Basket name: {$basketName}");
    }
}