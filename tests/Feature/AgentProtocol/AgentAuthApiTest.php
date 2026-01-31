<?php

declare(strict_types=1);

namespace Tests\Feature\AgentProtocol;

use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for Agent Authentication API.
 */
class AgentAuthApiTest extends TestCase
{
    private Agent $agent;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        $this->user = User::factory()->create([
            'kyc_status' => 'approved',
        ]);

        $this->agent = Agent::factory()->create([
            'did'    => 'did:agent:test:api_' . Str::random(16),
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_returns_list_of_available_scopes(): void
    {
        $response = $this->getJson('/api/agent-protocol/auth/scopes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'scopes' => [
                        '*' => ['scope', 'description'],
                    ],
                ],
            ])
            ->assertJson(['success' => true]);

        $data = $response->json('data.scopes');
        $this->assertNotEmpty($data);

        // Verify some expected scopes exist
        $scopeNames = array_column($data, 'scope');
        $this->assertContains('payments:read', $scopeNames);
        $this->assertContains('wallet:read', $scopeNames);
    }

    #[Test]
    public function it_generates_challenge_for_did_authentication(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/challenge', [
            'did' => $this->agent->did,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'challenge',
                    'nonce',
                    'expires_at',
                ],
            ])
            ->assertJson(['success' => true]);

        // Verify challenge format
        $challenge = $response->json('data.challenge');
        $decoded = json_decode(base64_decode($challenge), true);
        $this->assertEquals($this->agent->did, $decoded['did']);
        $this->assertEquals('authenticate', $decoded['action']);
    }

    #[Test]
    public function it_rejects_challenge_without_did(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/challenge', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['did']);
    }

    #[Test]
    public function it_rejects_api_key_auth_with_invalid_key(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/api-key', [
            'api_key' => 'invalid-api-key-that-does-not-exist',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid or expired API key',
            ]);
    }

    #[Test]
    public function it_validates_invalid_session_token(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/validate', [
            'session_token' => 'invalid-session-token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid or expired session',
            ]);
    }

    #[Test]
    public function it_requires_authentication_for_api_key_generation(): void
    {
        $response = $this->postJson("/api/agent-protocol/agents/{$this->agent->did}/api-keys", [
            'name'   => 'Test Key',
            'scopes' => ['payments:read'],
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_generates_api_key_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->postJson("/api/agent-protocol/agents/{$this->agent->did}/api-keys", [
            'name'   => 'Test Key',
            'scopes' => ['payments:read', 'wallet:read'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'api_key',
                    'key_id',
                    'created_at',
                ],
            ])
            ->assertJson(['success' => true]);

        // Verify API key was stored
        $keyId = $response->json('data.key_id');
        $this->assertDatabaseHas('agent_api_keys', [
            'key_id'   => $keyId,
            'agent_id' => $this->agent->agent_id,
            'name'     => 'Test Key',
        ]);
    }

    #[Test]
    public function it_lists_api_keys_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        // Create some API keys
        AgentApiKey::create([
            'key_id'     => 'ak_' . Str::random(16),
            'agent_id'   => $this->agent->agent_id,
            'name'       => 'Key 1',
            'key_hash'   => hash('sha256', 'test-key-1'),
            'key_prefix' => 'testkey1',
            'scopes'     => ['payments:read'],
            'is_active'  => true,
        ]);

        AgentApiKey::create([
            'key_id'     => 'ak_' . Str::random(16),
            'agent_id'   => $this->agent->agent_id,
            'name'       => 'Key 2',
            'key_hash'   => hash('sha256', 'test-key-2'),
            'key_prefix' => 'testkey2',
            'scopes'     => ['wallet:read'],
            'is_active'  => true,
        ]);

        $response = $this->getJson("/api/agent-protocol/agents/{$this->agent->did}/api-keys");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'keys' => [
                        '*' => ['key_id', 'name', 'scopes', 'created_at'],
                    ],
                ],
            ]);

        $keys = $response->json('data.keys');
        $this->assertCount(2, $keys);
    }

    #[Test]
    public function it_revokes_api_key_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $apiKey = AgentApiKey::create([
            'key_id'     => 'ak_' . Str::random(16),
            'agent_id'   => $this->agent->agent_id,
            'name'       => 'Key to Revoke',
            'key_hash'   => hash('sha256', 'test-key'),
            'key_prefix' => 'testkeyx',
            'scopes'     => ['payments:read'],
            'is_active'  => true,
        ]);

        $response = $this->deleteJson(
            "/api/agent-protocol/agents/{$this->agent->did}/api-keys/{$apiKey->key_id}"
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API key revoked successfully',
            ]);

        // Verify key was deactivated
        $apiKey->refresh();
        $this->assertFalse($apiKey->is_active);
        $this->assertNotNull($apiKey->revoked_at);
    }

    #[Test]
    public function it_lists_active_sessions_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->getJson("/api/agent-protocol/agents/{$this->agent->did}/sessions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['sessions'],
            ])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_revokes_all_sessions_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);

        $response = $this->deleteJson("/api/agent-protocol/agents/{$this->agent->did}/sessions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['revoked_count'],
            ])
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function it_rejects_did_auth_without_required_fields(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/did', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['did', 'signature', 'challenge']);
    }

    #[Test]
    public function it_rejects_api_key_auth_without_api_key(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/api-key', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['api_key']);
    }

    #[Test]
    public function it_rejects_session_validation_without_token(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/validate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_token']);
    }

    #[Test]
    public function it_rejects_session_revocation_without_token(): void
    {
        $response = $this->postJson('/api/agent-protocol/auth/revoke', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_token']);
    }
}
