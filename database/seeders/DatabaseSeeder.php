<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(AssetSeeder::class);
        $this->call(StablecoinSeeder::class);
        $this->call(GCUBasketSeeder::class);
        $this->call(SettingSeeder::class);
        $this->call(RewardsSeeder::class);
    }
}
