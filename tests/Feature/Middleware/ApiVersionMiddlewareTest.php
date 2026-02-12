<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiVersionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('api-versioning.current_version', 'v1');
        Config::set('api-versioning.versions', [
            'v1' => [
                'supported'     => true,
                'deprecated'    => false,
                'deprecated_at' => null,
                'sunset'        => null,
            ],
            'v2' => [
                'supported'     => true,
                'deprecated'    => false,
                'deprecated_at' => null,
                'sunset'        => null,
            ],
        ]);

        Route::middleware(['api'])->group(function () {
            Route::get('/api/v1/test-version', fn () => response()->json(['ok' => true]));
            Route::get('/api/v2/test-version', fn () => response()->json(['ok' => true]));
            Route::get('/api/test-no-version', fn () => response()->json(['ok' => true]));
        });
    }

    public function test_adds_api_version_header_for_v1(): void
    {
        $response = $this->getJson('/api/v1/test-version');

        $response->assertStatus(200);
        $response->assertHeader('X-API-Version', 'v1');
    }

    public function test_adds_api_version_header_for_v2(): void
    {
        $response = $this->getJson('/api/v2/test-version');

        $response->assertStatus(200);
        $response->assertHeader('X-API-Version', 'v2');
    }

    public function test_defaults_to_current_version_when_no_version_in_path(): void
    {
        $response = $this->getJson('/api/test-no-version');

        $response->assertStatus(200);
        $response->assertHeader('X-API-Version', 'v1');
    }

    public function test_adds_deprecation_header_when_version_is_deprecated(): void
    {
        Config::set('api-versioning.versions.v1.deprecated', true);
        Config::set('api-versioning.versions.v1.deprecated_at', '2026-06-01');

        $response = $this->getJson('/api/v1/test-version');

        $response->assertStatus(200);
        $response->assertHeader('Deprecation', '2026-06-01');
    }

    public function test_adds_sunset_header_when_version_has_sunset_date(): void
    {
        Config::set('api-versioning.versions.v2.sunset', '2027-01-01');

        $response = $this->getJson('/api/v2/test-version');

        $response->assertStatus(200);
        $response->assertHeader('Sunset', '2027-01-01');
    }

    public function test_adds_both_deprecation_and_sunset_headers(): void
    {
        Config::set('api-versioning.versions.v1.deprecated', true);
        Config::set('api-versioning.versions.v1.deprecated_at', '2026-06-01');
        Config::set('api-versioning.versions.v1.sunset', '2027-01-01');

        $response = $this->getJson('/api/v1/test-version');

        $response->assertStatus(200);
        $response->assertHeader('Deprecation', '2026-06-01');
        $response->assertHeader('Sunset', '2027-01-01');
    }

    public function test_no_deprecation_header_when_version_is_not_deprecated(): void
    {
        $response = $this->getJson('/api/v1/test-version');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('Sunset');
    }
}
