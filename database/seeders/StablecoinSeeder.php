<?php

namespace Database\Seeders;

use App\Models\Stablecoin;
use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Seeder;

class StablecoinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stablecoins = [
            [
                'code' => 'FUSD',
                'name' => 'FinAegis USD',
                'symbol' => 'FUSD',
                'peg_asset_code' => 'USD',
                'peg_ratio' => 1.0,
                'target_price' => 1.0,
                'stability_mechanism' => 'collateralized',
                'collateral_ratio' => 1.5,
                'min_collateral_ratio' => 1.2,
                'liquidation_penalty' => 0.1,
                'total_supply' => 0,
                'max_supply' => 10000000000, // 100M FUSD
                'total_collateral_value' => 0,
                'mint_fee' => 0.005, // 0.5%
                'burn_fee' => 0.003, // 0.3%
                'precision' => 2,
                'is_active' => true,
                'minting_enabled' => true,
                'burning_enabled' => true,
                'metadata' => [
                    'description' => 'USD-pegged stablecoin with collateralized stability mechanism',
                    'use_cases' => ['payments', 'trading', 'savings'],
                    'supported_collateral' => ['USD', 'EUR', 'BTC', 'ETH'],
                ],
            ],
            [
                'code' => 'FEUR',
                'name' => 'FinAegis EUR',
                'symbol' => 'FEUR',
                'peg_asset_code' => 'EUR',
                'peg_ratio' => 1.0,
                'target_price' => 1.0,
                'stability_mechanism' => 'collateralized',
                'collateral_ratio' => 1.6,
                'min_collateral_ratio' => 1.3,
                'liquidation_penalty' => 0.12,
                'total_supply' => 0,
                'max_supply' => 5000000000, // 50M FEUR
                'total_collateral_value' => 0,
                'mint_fee' => 0.006, // 0.6%
                'burn_fee' => 0.004, // 0.4%
                'precision' => 2,
                'is_active' => true,
                'minting_enabled' => true,
                'burning_enabled' => true,
                'metadata' => [
                    'description' => 'EUR-pegged stablecoin with collateralized stability mechanism',
                    'use_cases' => ['european_payments', 'forex_trading', 'euro_savings'],
                    'supported_collateral' => ['EUR', 'USD', 'BTC', 'ETH'],
                ],
            ],
            [
                'code' => 'FCRYPTO',
                'name' => 'FinAegis Crypto Index',
                'symbol' => 'FCRYPTO',
                'peg_asset_code' => 'USD',
                'peg_ratio' => 1.0,
                'target_price' => 1.0,
                'stability_mechanism' => 'algorithmic',
                'collateral_ratio' => 1.0, // No collateral required for algorithmic
                'min_collateral_ratio' => 1.0,
                'liquidation_penalty' => 0.0,
                'total_supply' => 0,
                'max_supply' => 1000000000, // 10M FCRYPTO
                'total_collateral_value' => 0,
                'mint_fee' => 0.01, // 1%
                'burn_fee' => 0.01, // 1%
                'precision' => 8, // Higher precision for crypto
                'is_active' => false, // Start disabled for testing
                'minting_enabled' => false,
                'burning_enabled' => false,
                'metadata' => [
                    'description' => 'Algorithmic stablecoin pegged to crypto market index',
                    'use_cases' => ['defi', 'crypto_trading', 'yield_farming'],
                    'mechanism_details' => 'Price stability through algorithmic supply adjustments',
                ],
            ],
            [
                'code' => 'FGOLD',
                'name' => 'FinAegis Gold',
                'symbol' => 'FGOLD',
                'peg_asset_code' => 'XAU',
                'peg_ratio' => 0.001, // 1 FGOLD = 0.001 ounces of gold
                'target_price' => 2.4, // Approximate USD value for 0.001 oz gold
                'stability_mechanism' => 'hybrid',
                'collateral_ratio' => 1.2,
                'min_collateral_ratio' => 1.1,
                'liquidation_penalty' => 0.08,
                'total_supply' => 0,
                'max_supply' => 100000000, // 1M FGOLD (1000 ounces worth)
                'total_collateral_value' => 0,
                'mint_fee' => 0.008, // 0.8%
                'burn_fee' => 0.005, // 0.5%
                'precision' => 3,
                'is_active' => true,
                'minting_enabled' => true,
                'burning_enabled' => true,
                'metadata' => [
                    'description' => 'Gold-pegged stablecoin with hybrid stability mechanism',
                    'use_cases' => ['store_of_value', 'inflation_hedge', 'commodity_trading'],
                    'supported_collateral' => ['XAU', 'USD', 'EUR'],
                ],
            ],
        ];

        foreach ($stablecoins as $stablecoinData) {
            // Create the stablecoin as an asset first
            Asset::firstOrCreate(
                ['code' => $stablecoinData['code']],
                [
                    'name' => $stablecoinData['name'],
                    'type' => 'custom', // Stablecoins are custom assets
                    'precision' => $stablecoinData['precision'],
                    'is_active' => $stablecoinData['is_active'],
                    'metadata' => array_merge(
                        ['asset_type' => 'stablecoin'],
                        $stablecoinData['metadata'] ?? []
                    ),
                ]
            );
            
            // Then create the stablecoin
            Stablecoin::firstOrCreate(
                ['code' => $stablecoinData['code']],
                $stablecoinData
            );
        }

        $this->command->info('Stablecoin seed data created successfully.');
    }
}