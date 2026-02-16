<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SrsEndpointsTest extends TestCase
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

    public function test_srs_url_is_public(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-url');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['url', 'version', 'circuits'],
            ]);
    }

    public function test_srs_url_accepts_chain_id_parameter(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-url?chain_id=137');

        $response->assertOk()
            ->assertJsonPath('data.chain_id', '137');
    }

    public function test_srs_status_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/privacy/srs-status');

        $response->assertUnauthorized();
    }

    public function test_srs_status_returns_download_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/srs-status');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['has_required', 'version', 'required_circuits'],
            ]);
    }
}
