<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Mobile;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_app_status_returns_version_info(): void
    {
        $response = $this->getJson('/api/v1/app/status');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'min_version',
                    'latest_version',
                    'force_update',
                    'maintenance',
                    'maintenance_message',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintenance', false);
    }

    public function test_app_status_does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/status');

        $response->assertOk();
    }

    public function test_app_status_returns_boolean_for_force_update(): void
    {
        $response = $this->getJson('/api/v1/app/status');

        $response->assertOk();
        $this->assertIsBool($response->json('data.force_update'));
    }
}
