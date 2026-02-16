<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BulkDeviceRemovalTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;
    }

    public function test_bulk_remove_devices_requires_auth(): void
    {
        $response = $this->deleteJson('/api/mobile/devices/all');

        $response->assertUnauthorized();
    }

    public function test_bulk_remove_devices_returns_count(): void
    {
        $response = $this->withToken($this->token)
            ->deleteJson('/api/mobile/devices/all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['removed_count'],
            ]);

        $this->assertIsInt($response->json('data.removed_count'));
    }
}
