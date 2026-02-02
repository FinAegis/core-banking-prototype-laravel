<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Services\SrsManifestService;
use App\Domain\Privacy\ValueObjects\SrsCircuit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests for SrsManifestService.
 */
class SrsManifestServiceTest extends TestCase
{
    private SrsManifestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SrsManifestService();

        // Set up test config
        Config::set('privacy.srs', [
            'version'      => '1.0.0',
            'cdn_base_url' => 'https://cdn.example.com/srs',
            'circuits'     => [
                'circuit_a' => ['size' => 1000, 'required' => true],
                'circuit_b' => ['size' => 2000, 'required' => true],
                'circuit_c' => ['size' => 3000, 'required' => false],
            ],
        ]);
    }

    public function test_get_version_returns_configured_version(): void
    {
        $this->assertEquals('1.0.0', $this->service->getVersion());
    }

    public function test_get_version_returns_default_when_null(): void
    {
        Config::set('privacy.srs.version', null);

        // When explicitly set to null, service falls back to default
        $this->assertEquals('1.0.0', $this->service->getVersion());
    }

    public function test_get_cdn_base_url_returns_configured_url(): void
    {
        $this->assertEquals('https://cdn.example.com/srs', $this->service->getCdnBaseUrl());
    }

    public function test_get_cdn_base_url_returns_default_when_not_configured(): void
    {
        Config::set('privacy.srs.cdn_base_url', null);

        $this->assertEquals('https://cdn.finaegis.com/srs', $this->service->getCdnBaseUrl());
    }

    public function test_get_circuits_returns_collection(): void
    {
        $circuits = $this->service->getCircuits();

        $this->assertInstanceOf(Collection::class, $circuits);
        $this->assertCount(3, $circuits);
    }

    public function test_get_circuits_returns_srs_circuit_objects(): void
    {
        $circuits = $this->service->getCircuits();

        foreach ($circuits as $circuit) {
            $this->assertInstanceOf(SrsCircuit::class, $circuit);
        }
    }

    public function test_get_circuits_returns_correct_circuit_names(): void
    {
        $circuits = $this->service->getCircuits();
        $names = $circuits->map(fn (SrsCircuit $c) => $c->name)->toArray();

        $this->assertContains('circuit_a', $names);
        $this->assertContains('circuit_b', $names);
        $this->assertContains('circuit_c', $names);
    }

    public function test_get_required_circuits_returns_only_required(): void
    {
        $required = $this->service->getRequiredCircuits();

        $this->assertCount(2, $required);
        foreach ($required as $circuit) {
            $this->assertTrue($circuit->required);
        }
    }

    public function test_get_circuit_returns_circuit_by_name(): void
    {
        $circuit = $this->service->getCircuit('circuit_a');

        $this->assertInstanceOf(SrsCircuit::class, $circuit);
        $this->assertEquals('circuit_a', $circuit->name);
    }

    public function test_get_circuit_returns_null_for_unknown_name(): void
    {
        $circuit = $this->service->getCircuit('nonexistent');

        $this->assertNull($circuit);
    }

    public function test_get_total_size_returns_sum_of_all_circuits(): void
    {
        $totalSize = $this->service->getTotalSize();

        $this->assertEquals(6000, $totalSize); // 1000 + 2000 + 3000
    }

    public function test_get_required_size_returns_sum_of_required_circuits(): void
    {
        $requiredSize = $this->service->getRequiredSize();

        $this->assertEquals(3000, $requiredSize); // 1000 + 2000
    }

    public function test_track_download_logs_download(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'SRS download tracked'
                    && $context['circuits'] === ['circuit_a', 'circuit_b']
                    && $context['device_info'] === 'iPhone 14'
                    && $context['srs_version'] === '1.0.0';
            });

        $user = User::factory()->make(['id' => 1]);

        $this->service->trackDownload($user, ['circuit_a', 'circuit_b'], 'iPhone 14');
    }

    public function test_has_required_srs_returns_false(): void
    {
        $user = User::factory()->make();

        // Default implementation always returns false
        $this->assertFalse($this->service->hasRequiredSrs($user));
    }

    public function test_get_manifest_returns_complete_manifest(): void
    {
        $manifest = $this->service->getManifest();

        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('cdn_base_url', $manifest);
        $this->assertArrayHasKey('total_size', $manifest);
        $this->assertArrayHasKey('required_size', $manifest);
        $this->assertArrayHasKey('circuits', $manifest);
        $this->assertArrayHasKey('required_count', $manifest);
        $this->assertArrayHasKey('total_count', $manifest);

        $this->assertEquals('1.0.0', $manifest['version']);
        $this->assertEquals('https://cdn.example.com/srs', $manifest['cdn_base_url']);
        $this->assertEquals(6000, $manifest['total_size']);
        $this->assertEquals(3000, $manifest['required_size']);
        $this->assertEquals(2, $manifest['required_count']);
        $this->assertEquals(3, $manifest['total_count']);
    }

    public function test_get_manifest_circuits_are_arrays(): void
    {
        $manifest = $this->service->getManifest();

        $this->assertIsArray($manifest['circuits']);
        foreach ($manifest['circuits'] as $circuit) {
            $this->assertIsArray($circuit);
        }
    }

    public function test_circuits_have_correct_download_urls(): void
    {
        $circuits = $this->service->getCircuits();

        foreach ($circuits as $circuit) {
            $expectedUrl = "https://cdn.example.com/srs/1.0.0/{$circuit->name}.srs";
            $this->assertEquals($expectedUrl, $circuit->downloadUrl);
        }
    }

    public function test_empty_circuits_config_returns_empty_collection(): void
    {
        Config::set('privacy.srs.circuits', []);

        $circuits = $this->service->getCircuits();

        $this->assertInstanceOf(Collection::class, $circuits);
        $this->assertCount(0, $circuits);
    }

    public function test_get_total_size_with_empty_circuits_returns_zero(): void
    {
        Config::set('privacy.srs.circuits', []);

        $this->assertEquals(0, $this->service->getTotalSize());
    }

    public function test_get_required_size_with_no_required_circuits_returns_zero(): void
    {
        Config::set('privacy.srs.circuits', [
            'circuit_a' => ['size' => 1000, 'required' => false],
        ]);

        $this->assertEquals(0, $this->service->getRequiredSize());
    }
}
