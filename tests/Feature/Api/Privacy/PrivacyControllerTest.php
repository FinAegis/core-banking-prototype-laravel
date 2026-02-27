<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrivacyControllerTest extends TestCase
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

    public function test_get_networks_returns_supported_networks(): void
    {
        $response = $this->getJson('/api/v1/privacy/networks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'networks',
                    'tree_depth',
                    'provider',
                ],
            ]);

        $data = $response->json('data');
        $this->assertContains('polygon', $data['networks']);
        $this->assertContains('ethereum', $data['networks']);
        $this->assertContains('arbitrum', $data['networks']);
        $this->assertContains('bsc', $data['networks']);
        $this->assertEquals(32, $data['tree_depth']);
        $this->assertEquals('demo', $data['provider']);
    }

    public function test_get_merkle_root_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/privacy/merkle-root?network=polygon');

        $response->assertUnauthorized();
    }

    public function test_get_merkle_root_requires_network_parameter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/merkle-root');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_306');
    }

    public function test_get_merkle_root_returns_valid_root(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/merkle-root?network=polygon');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'root',
                    'network',
                    'leaf_count',
                    'tree_depth',
                    'block_number',
                    'synced_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('polygon', $data['network']);
        $this->assertEquals(32, $data['tree_depth']);
        $this->assertStringStartsWith('0x', $data['root']);
    }

    public function test_get_merkle_root_rejects_invalid_network(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/merkle-root?network=invalid');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_307')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'supported_networks',
                ],
            ]);
    }

    public function test_get_merkle_path_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/privacy/merkle-path', [
            'commitment' => '0x' . str_repeat('1', 64),
            'network'    => 'polygon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_get_merkle_path_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/merkle-path', []);

        $response->assertUnprocessable();
    }

    public function test_get_merkle_path_validates_commitment_format(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/merkle-path', [
                'commitment' => 'invalid',
                'network'    => 'polygon',
            ]);

        $response->assertUnprocessable();
    }

    public function test_get_merkle_path_for_known_commitment(): void
    {
        // Use a pre-seeded demo commitment
        $commitment = '0x' . str_repeat('1', 64);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/merkle-path', [
                'commitment' => $commitment,
                'network'    => 'polygon',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'commitment',
                    'root',
                    'network',
                    'leaf_index',
                    'siblings',
                    'path_indices',
                    'proof_depth',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('polygon', $data['network']);
        $this->assertCount(32, $data['siblings']);
        $this->assertCount(32, $data['path_indices']);
    }

    public function test_get_merkle_path_returns_404_for_unknown_commitment(): void
    {
        $unknownCommitment = '0x' . str_repeat('9', 64);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/merkle-path', [
                'commitment' => $unknownCommitment,
                'network'    => 'polygon',
            ]);

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_PRIVACY_306');
    }

    public function test_verify_commitment_with_valid_proof(): void
    {
        // First get a valid path
        $commitment = '0x' . str_repeat('1', 64);
        $pathResponse = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/merkle-path', [
                'commitment' => $commitment,
                'network'    => 'polygon',
            ]);

        $pathData = $pathResponse->json('data');

        // Now verify it
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/verify-commitment', [
                'commitment'   => $commitment,
                'network'      => 'polygon',
                'root'         => $pathData['root'],
                'leaf_index'   => $pathData['leaf_index'],
                'siblings'     => $pathData['siblings'],
                'path_indices' => $pathData['path_indices'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.commitment', $commitment);
    }

    public function test_sync_tree_triggers_merkle_sync(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/sync', [
                'network' => 'polygon',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'root',
                    'network',
                    'synced_at',
                ],
                'message',
            ])
            ->assertJsonPath('data.network', 'polygon');
    }

    public function test_sync_tree_rejects_invalid_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/sync', [
                'network' => 'invalid',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_PRIVACY_307');
    }
}
