<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create a demo user
        $demoUser = User::firstOrCreate(
            ['email' => 'api-demo@finaegis.org'],
            [
                'name' => 'API Demo User',
                'password' => bcrypt('demo-password-' . Str::random(16)),
            ]
        );
        
        // Create demo API keys with different permission levels
        $demoKeys = [
            [
                'name' => 'Demo Read-Only Key',
                'description' => 'Read-only access for testing API endpoints',
                'permissions' => ['read'],
                'prefix' => 'demo_read',
            ],
            [
                'name' => 'Demo Read-Write Key',
                'description' => 'Read and write access for testing API endpoints',
                'permissions' => ['read', 'write'],
                'prefix' => 'demo_write',
            ],
            [
                'name' => 'Demo Full Access Key',
                'description' => 'Full access for testing all API endpoints',
                'permissions' => ['read', 'write', 'delete'],
                'prefix' => 'demo_full',
            ],
        ];
        
        foreach ($demoKeys as $keyData) {
            // Check if key already exists
            $existingKey = ApiKey::where('user_uuid', $demoUser->uuid)
                ->where('name', $keyData['name'])
                ->first();
                
            if (!$existingKey) {
                // Generate the key
                $plainKey = ApiKey::generateKey();
                
                // Create the API key record
                $apiKey = ApiKey::create([
                    'user_uuid' => $demoUser->uuid,
                    'name' => $keyData['name'],
                    'description' => $keyData['description'],
                    'key_prefix' => substr($plainKey, 0, 8),
                    'key_hash' => bcrypt($plainKey),
                    'permissions' => $keyData['permissions'],
                    'is_active' => true,
                ]);
                
                // Output the key (only shown during seeding)
                $this->command->info("Created API Key: {$keyData['name']}");
                $this->command->line("Key: {$plainKey}");
                $this->command->line("Permissions: " . implode(', ', $keyData['permissions']));
                $this->command->line("---");
            }
        }
        
        // Create a sandbox environment key
        $sandboxUser = User::firstOrCreate(
            ['email' => 'sandbox@finaegis.org'],
            [
                'name' => 'Sandbox Environment',
                'password' => bcrypt('sandbox-' . Str::random(16)),
            ]
        );
        
        $sandboxKey = ApiKey::where('user_uuid', $sandboxUser->uuid)
            ->where('name', 'Sandbox API Key')
            ->first();
            
        if (!$sandboxKey) {
            $plainKey = ApiKey::generateKey();
            
            ApiKey::create([
                'user_uuid' => $sandboxUser->uuid,
                'name' => 'Sandbox API Key',
                'description' => 'Public sandbox key for testing the API',
                'key_prefix' => substr($plainKey, 0, 8),
                'key_hash' => bcrypt($plainKey),
                'permissions' => ['read', 'write'],
                'is_active' => true,
                'expires_at' => now()->addYear(),
            ]);
            
            $this->command->info("Created Sandbox API Key");
            $this->command->line("Key: {$plainKey}");
            $this->command->line("Valid until: " . now()->addYear()->format('Y-m-d'));
        }
    }
}