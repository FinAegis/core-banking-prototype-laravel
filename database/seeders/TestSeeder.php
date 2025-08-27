<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Schema;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds for test environment.
     */
    public function run(): void
    {
        // Only seed essential data needed for tests
        $seeders = [];

        // Only seed roles if the permission tables exist
        if (Schema::hasTable('roles')) {
            $seeders[] = RolesSeeder::class;
        }

        // Only seed assets if the assets table exists
        if (Schema::hasTable('assets')) {
            $seeders[] = AssetSeeder::class;
        }

        // Only seed settings if the settings table exists
        if (Schema::hasTable('settings')) {
            $seeders[] = SettingSeeder::class;
        }

        if (empty($seeders)) {
            $this->command->warn('No tables available for seeding. Migrations may not have run properly.');

            return;
        }

        $this->call($seeders);
    }
}
