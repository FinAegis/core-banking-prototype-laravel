<?php

namespace Tests\Feature;

use Tests\TestCase;

class FaviconTest extends TestCase
{
    /**
     * Test that favicon files exist in public directory
     */
    public function test_favicon_files_exist(): void
    {
        $faviconFiles = [
            'favicon.ico',
            'favicon.svg',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'apple-touch-icon.png',
            'android-chrome-192x192.png',
            'android-chrome-512x512.png',
            'manifest.json',
            'browserconfig.xml',
        ];
        
        foreach ($faviconFiles as $file) {
            $filePath = public_path($file);
            $this->assertFileExists($filePath, "Favicon file missing: $file");
        }
    }
    
    /**
     * Test that manifest.json contains correct data
     */
    public function test_manifest_json_has_correct_structure(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('theme_color', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        
        $this->assertEquals('FinAegis Core Banking', $manifest['name']);
        $this->assertEquals('FinAegis', $manifest['short_name']);
        $this->assertEquals('#6366F1', $manifest['theme_color']);
        
        $this->assertIsArray($manifest['icons']);
        $this->assertNotEmpty($manifest['icons']);
    }
    
    /**
     * Test that pages include favicon meta tags
     */
    public function test_pages_include_favicon_meta_tags(): void
    {
        // Test guest page (login)
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('favicon.ico');
        $response->assertSee('apple-touch-icon');
        $response->assertSee('manifest.json');
        $response->assertSee('theme-color');
        
        // Test authenticated page with proper setup
        $user = \App\Models\User::factory()->create();
        
        // Create a team for the user (required by Jetstream)
        $team = \App\Models\Team::factory()->create([
            'user_id' => $user->id,
            'personal_team' => true,
        ]);
        
        $user->current_team_id = $team->id;
        $user->save();
        
        // Create an account for the user
        \App\Models\Account::factory()->create([
            'user_uuid' => $user->uuid,
            'name' => $user->name . "'s Account",
        ]);
        
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('favicon.ico');
        $response->assertSee('apple-touch-icon');
        $response->assertSee('manifest.json');
    }
}