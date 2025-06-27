<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settingsService = app(SettingsService::class);
        $settingsService->initializeSettings();
        
        $this->command->info('Settings initialized successfully.');
    }
}
