<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Stablecoin;
use App\Domain\Asset\Models\Asset;

trait CreatesStablecoins
{
    /**
     * Create a stablecoin with its corresponding asset.
     */
    protected function createStablecoinWithAsset(array $attributes = []): Stablecoin
    {
        $defaults = [
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
            'max_supply' => 10000000,
            'total_collateral_value' => 0,
            'mint_fee' => 0.005,
            'burn_fee' => 0.003,
            'precision' => 2,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ];
        
        $data = array_merge($defaults, $attributes);
        
        // Create the stablecoin as an asset first
        Asset::firstOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'type' => 'custom', // Stablecoins are custom assets
                'precision' => $data['precision'],
                'is_active' => $data['is_active'],
                'metadata' => ['asset_type' => 'stablecoin'],
            ]
        );
        
        // Create and return the stablecoin
        return Stablecoin::create($data);
    }
    
    /**
     * Create multiple stablecoins with different mechanisms.
     */
    protected function createTestStablecoins(): array
    {
        $fusd = $this->createStablecoinWithAsset([
            'code' => 'FUSD',
            'name' => 'FinAegis USD',
            'peg_asset_code' => 'USD',
            'stability_mechanism' => 'collateralized',
        ]);
        
        $feur = $this->createStablecoinWithAsset([
            'code' => 'FEUR',
            'name' => 'FinAegis EUR',
            'peg_asset_code' => 'EUR',
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.6,
            'min_collateral_ratio' => 1.3,
        ]);
        
        $falgo = $this->createStablecoinWithAsset([
            'code' => 'FALGO',
            'name' => 'FinAegis Algorithmic',
            'peg_asset_code' => 'USD',
            'stability_mechanism' => 'algorithmic',
            'collateral_ratio' => 0,
            'min_collateral_ratio' => 0,
            'liquidation_penalty' => 0,
            'algo_mint_reward' => 0.02,
            'algo_burn_penalty' => 0.02,
        ]);
        
        $fhybrid = $this->createStablecoinWithAsset([
            'code' => 'FHYBRID',
            'name' => 'FinAegis Hybrid',
            'peg_asset_code' => 'USD',
            'stability_mechanism' => 'hybrid',
            'collateral_ratio' => 0.8,
            'min_collateral_ratio' => 0.5,
            'algo_mint_reward' => 0.01,
            'algo_burn_penalty' => 0.01,
        ]);
        
        return [
            'collateralized' => $fusd,
            'collateralized_eur' => $feur,
            'algorithmic' => $falgo,
            'hybrid' => $fhybrid,
        ];
    }
    
    /**
     * Ensure required assets exist.
     */
    protected function ensureAssetsExist(): void
    {
        $assets = [
            ['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat'],
            ['code' => 'EUR', 'name' => 'Euro', 'type' => 'fiat'],
            ['code' => 'GBP', 'name' => 'British Pound', 'type' => 'fiat'],
            ['code' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8],
            ['code' => 'ETH', 'name' => 'Ethereum', 'type' => 'crypto', 'precision' => 8],
            ['code' => 'XAU', 'name' => 'Gold', 'type' => 'commodity', 'precision' => 3],
        ];
        
        foreach ($assets as $assetData) {
            Asset::firstOrCreate(
                ['code' => $assetData['code']],
                array_merge([
                    'precision' => 2,
                    'is_active' => true,
                ], $assetData)
            );
        }
    }
}