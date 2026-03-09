<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Compliance\Services\GdprService;
use App\Jobs\ProcessGdprDataExport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GdprExportDownloadTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    // --- GET /api/v1/user/data-export/{exportId} status polling ---

    public function test_get_export_status_via_mobile_route(): void
    {
        $exportId = 'test-export-001';
        Cache::put("gdpr_export:{$exportId}", [
            'status'     => 'processing',
            'started_at' => now()->toIso8601String(),
        ], now()->addHours(24));

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/user/data-export/{$exportId}");

        $response->assertOk()
            ->assertJsonPath('export_id', $exportId)
            ->assertJsonPath('status', 'processing');
    }

    public function test_get_export_status_returns_404_when_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/user/data-export/nonexistent');

        $response->assertNotFound()
            ->assertJsonPath('error', 'Export request not found or expired');
    }

    public function test_get_export_status_includes_download_url_when_completed(): void
    {
        $exportId = 'test-export-dl';
        $filePath = "gdpr-exports/{$exportId}.json.enc";

        Storage::disk('local')->put($filePath, encrypt(json_encode(['profile' => ['name' => 'Test']])));

        Cache::put("gdpr_export:{$exportId}", [
            'status'       => 'completed',
            'sections'     => ['profile'],
            'completed_at' => now()->toIso8601String(),
            'file_path'    => $filePath,
        ], now()->addHours(24));

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/user/data-export/{$exportId}");

        $response->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['download_url'])
            ->assertJsonMissing(['file_path']);
    }

    public function test_get_export_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/user/data-export/some-id');

        $response->assertUnauthorized();
    }

    // --- Download endpoint ---

    public function test_download_export_returns_json_file(): void
    {
        $exportId = 'test-download';
        $exportData = ['profile' => ['name' => 'John', 'email' => 'john@example.com']];
        $filePath = "gdpr-exports/{$exportId}.json.enc";

        Storage::disk('local')->put($filePath, encrypt(json_encode($exportData)));

        Cache::put("gdpr_export:{$exportId}", [
            'status'    => 'completed',
            'sections'  => ['profile'],
            'file_path' => $filePath,
        ], now()->addHours(24));

        $signedUrl = URL::signedRoute(
            'api.user.data-export.download',
            ['exportId' => $exportId],
            now()->addHour(),
        );

        $response = $this->withToken($this->token)->get($signedUrl);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Content-Disposition', "attachment; filename=\"gdpr-export-{$exportId}.json\"");

        $this->assertEquals($exportData, json_decode((string) $response->getContent(), true));
    }

    public function test_download_with_invalid_signature_returns_403(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/user/data-export/some-id/download?signature=invalid');

        $response->assertStatus(403);
    }

    public function test_download_returns_404_when_export_not_completed(): void
    {
        $exportId = 'test-pending';
        Cache::put("gdpr_export:{$exportId}", [
            'status' => 'processing',
        ], now()->addHours(24));

        $signedUrl = URL::signedRoute(
            'api.user.data-export.download',
            ['exportId' => $exportId],
            now()->addHour(),
        );

        $response = $this->withToken($this->token)->get($signedUrl);

        $response->assertNotFound();
    }

    public function test_download_returns_404_when_file_missing(): void
    {
        $exportId = 'test-missing-file';
        Cache::put("gdpr_export:{$exportId}", [
            'status'    => 'completed',
            'file_path' => "gdpr-exports/{$exportId}.json.enc",
        ], now()->addHours(24));

        $signedUrl = URL::signedRoute(
            'api.user.data-export.download',
            ['exportId' => $exportId],
            now()->addHour(),
        );

        $response = $this->withToken($this->token)->get($signedUrl);

        $response->assertNotFound();
    }

    // --- Export file is encrypted at rest ---

    public function test_export_file_is_encrypted_at_rest(): void
    {
        $exportId = 'test-encrypted';
        $exportData = ['profile' => ['name' => 'Sensitive User']];
        $filePath = "gdpr-exports/{$exportId}.json.enc";

        $encrypted = encrypt(json_encode($exportData));
        Storage::disk('local')->put($filePath, $encrypted);

        $rawContent = (string) Storage::disk('local')->get($filePath);

        // Raw file should not contain the plaintext
        $this->assertStringNotContainsString('Sensitive User', $rawContent);

        // But decrypting should yield the original data
        $decrypted = json_decode(decrypt($rawContent), true);
        $this->assertEquals($exportData, $decrypted);
    }

    // --- Job integration ---

    public function test_job_writes_file_and_sets_cache_with_download_url(): void
    {
        $this->mock(GdprService::class, function ($mock) {
            $mock->shouldReceive('exportUserData')
                ->once()
                ->andReturn([
                    'profile'      => ['name' => 'Test User'],
                    'transactions' => [],
                ]);
        });

        $exportId = 'job-test-export';
        $job = new ProcessGdprDataExport($this->user->id, $exportId);
        $job->handle(app(GdprService::class));

        // File should exist
        $this->assertTrue(Storage::disk('local')->exists("gdpr-exports/{$exportId}.json.enc"));

        // Cache should have completed status with download_url
        $cached = Cache::get("gdpr_export:{$exportId}");
        $this->assertEquals('completed', $cached['status']);
        $this->assertArrayHasKey('download_url', $cached);
        $this->assertArrayHasKey('file_path', $cached);
        $this->assertEquals(['profile', 'transactions'], $cached['sections']);
    }

    // --- Route existence ---

    public function test_mobile_data_export_routes_exist(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertNotNull($routes->getByName('api.user.data-export'));
        $this->assertNotNull($routes->getByName('api.user.data-export.status'));
        $this->assertNotNull($routes->getByName('api.user.data-export.download'));
    }
}
