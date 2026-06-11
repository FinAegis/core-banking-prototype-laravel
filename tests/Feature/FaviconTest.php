<?php

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Models\Team;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FaviconTest extends TestCase
{
/**
 * Test that favicon files exist in public directory.
 */ #[Test]
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
 * Test that manifest.json contains correct data.
 */ #[Test]
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

        // Production brand is Zelta (FinAegis is the demo identity) — the
        // manifest was truth-passed in the v7.15 landing refresh.
        $this->assertEquals('Zelta — Agentic Payments', $manifest['name']);
        $this->assertEquals('Zelta', $manifest['short_name']);
        $this->assertEquals('#0a0a0a', $manifest['theme_color']);

        $this->assertIsArray($manifest['icons']);
        $this->assertNotEmpty($manifest['icons']);
    }

/**
 * Test that pages include favicon meta tags.
 */ #[Test]
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
        $user = User::factory()->create();

        // Create a team for the user (required by Jetstream)
        $team = Team::factory()->create([
            'user_id'       => $user->id,
            'personal_team' => true,
        ]);

        $user->current_team_id = $team->id;
        $user->save();

        // Create an account for the user
        Account::factory()->create([
            'user_uuid' => $user->uuid,
            'name'      => $user->name . "'s Account",
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('favicon.ico');
        $response->assertSee('apple-touch-icon');
        $response->assertSee('manifest.json');
    }
}
