<?php

namespace Database\Seeders;

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assets = [
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => '$']),
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => '€']),
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => '£']),
            ],
            [
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'type' => 'fiat',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => 'CHF']),
            ],
            [
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'type' => 'fiat',
                'precision' => 0,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => '¥']),
            ],
            [
                'code' => 'XAU',
                'name' => 'Gold (Troy Ounce)',
                'type' => 'commodity',
                'precision' => 3,
                'is_active' => true,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => 'XAU']),
            ],
            [
                'code' => 'GCU',
                'name' => 'Global Currency Unit',
                'type' => 'basket',
                'precision' => 2,
                'is_active' => true,
                'is_basket' => true,
                'metadata' => json_encode(['symbol' => 'Ǥ']),
            ],
            [
                'code' => 'BTC',
                'name' => 'Bitcoin',
                'type' => 'crypto',
                'precision' => 8,
                'is_active' => false,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => '₿']),
            ],
            [
                'code' => 'ETH',
                'name' => 'Ethereum',
                'type' => 'crypto',
                'precision' => 18,
                'is_active' => false,
                'is_basket' => false,
                'metadata' => json_encode(['symbol' => 'Ξ']),
            ],
        ];

        foreach ($assets as $asset) {
            Asset::updateOrCreate(['code' => $asset['code']], $asset);
        }
        
        $this->command->info('Asset seed data created successfully.');
    }
}
