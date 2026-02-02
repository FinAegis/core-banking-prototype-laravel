<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for SRS Manifest API endpoints.
 */
class SrsManifestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ========================================================================
    // GET /api/v1/privacy/srs-manifest tests
    // ========================================================================

    public function test_get_srs_manifest_returns_manifest_without_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-manifest');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'version',
                    'cdn_base_url',
                    'total_size',
                    'required_size',
                    'required_count',
                    'total_count',
                    'circuits',
                ],
            ]);
    }

    public function test_get_srs_manifest_returns_correct_version(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-manifest');

        $response->assertOk()
            ->assertJsonPath('data.version', '1.0.0');
    }

    public function test_get_srs_manifest_contains_circuit_details(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-manifest');

        $response->assertOk();

        $data = $response->json('data');

        // Should have circuits array
        $this->assertIsArray($data['circuits']);
        $this->assertNotEmpty($data['circuits']);

        // Each circuit should have required fields
        foreach ($data['circuits'] as $circuit) {
            $this->assertArrayHasKey('name', $circuit);
            $this->assertArrayHasKey('version', $circuit);
            $this->assertArrayHasKey('size', $circuit);
            $this->assertArrayHasKey('size_human', $circuit);
            $this->assertArrayHasKey('required', $circuit);
            $this->assertArrayHasKey('download_url', $circuit);
            $this->assertArrayHasKey('checksum', $circuit);
            $this->assertArrayHasKey('checksum_algorithm', $circuit);
        }
    }

    public function test_get_srs_manifest_size_calculations_are_correct(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-manifest');

        $response->assertOk();

        $data = $response->json('data');

        // Calculate totals from circuits
        $totalSize = 0;
        $requiredSize = 0;
        $requiredCount = 0;

        foreach ($data['circuits'] as $circuit) {
            $totalSize += $circuit['size'];
            if ($circuit['required']) {
                $requiredSize += $circuit['size'];
                $requiredCount++;
            }
        }

        $this->assertEquals($totalSize, $data['total_size']);
        $this->assertEquals($requiredSize, $data['required_size']);
        $this->assertEquals($requiredCount, $data['required_count']);
        $this->assertEquals(count($data['circuits']), $data['total_count']);
    }

    public function test_get_srs_manifest_download_urls_contain_version(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-manifest');

        $response->assertOk();

        $data = $response->json('data');

        foreach ($data['circuits'] as $circuit) {
            $this->assertStringContainsString($data['version'], $circuit['download_url']);
            $this->assertStringContainsString($circuit['name'], $circuit['download_url']);
            $this->assertStringEndsWith('.srs', $circuit['download_url']);
        }
    }

    // ========================================================================
    // POST /api/v1/privacy/srs-downloaded tests
    // ========================================================================

    public function test_track_srs_download_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits' => ['shield_1_1'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_track_srs_download_with_valid_circuit(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits' => ['shield_1_1'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tracked', true)
            ->assertJsonPath('data.circuits', ['shield_1_1'])
            ->assertJsonPath('data.srs_version', '1.0.0');
    }

    public function test_track_srs_download_with_multiple_circuits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits'    => ['shield_1_1', 'unshield_2_1'],
            'device_info' => 'iPhone 14 Pro / iOS 17.2',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tracked', true)
            ->assertJsonPath('data.circuits', ['shield_1_1', 'unshield_2_1']);
    }

    public function test_track_srs_download_with_device_info(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits'    => ['shield_1_1'],
            'device_info' => 'Samsung Galaxy S24 / Android 14',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tracked', true);
    }

    public function test_track_srs_download_fails_with_unknown_circuit(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits' => ['nonexistent_circuit'],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_309')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'available_circuits',
                ],
            ]);
    }

    public function test_track_srs_download_fails_with_empty_circuits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits' => [],
        ]);

        $response->assertUnprocessable();
    }

    public function test_track_srs_download_fails_without_circuits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $response = $this->postJson('/api/v1/privacy/srs-downloaded', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['circuits']);
    }

    public function test_track_srs_download_with_all_available_circuits(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        // First get the manifest to know available circuits
        $manifestResponse = $this->getJson('/api/v1/privacy/srs-manifest');
        $circuits = collect($manifestResponse->json('data.circuits'))
            ->pluck('name')
            ->toArray();

        // Track all circuits
        $response = $this->postJson('/api/v1/privacy/srs-downloaded', [
            'circuits' => $circuits,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tracked', true)
            ->assertJsonPath('data.circuits', $circuits);
    }
}
