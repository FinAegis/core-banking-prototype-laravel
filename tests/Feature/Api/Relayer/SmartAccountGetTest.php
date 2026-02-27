<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Relayer;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SmartAccountGetTest extends TestCase
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

    public function test_get_account_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/relayer/account');

        $response->assertUnauthorized();
    }

    public function test_get_account_returns_404_when_no_accounts(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/account');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERR_RELAYER_102');
    }

    public function test_get_account_returns_primary_account(): void
    {
        // Create an account for the user first
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'network' => 'polygon',
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/account');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'owner_address',
                    'account_address',
                    'network',
                    'deployed',
                    'nonce',
                    'pending_ops',
                ],
            ]);
    }

    public function test_get_account_filters_by_network(): void
    {
        // Create accounts on two networks
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', ['network' => 'polygon']);
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', ['network' => 'base']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/account?network=base');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network', 'base');
    }

    public function test_get_account_returns_404_for_unmatched_network(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', ['network' => 'polygon']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/account?network=arbitrum');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_get_account_response_structure(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', ['network' => 'polygon']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/account');

        $data = $response->json('data');

        $this->assertIsString($data['id']);
        $this->assertStringStartsWith('0x', $data['owner_address']);
        $this->assertStringStartsWith('0x', $data['account_address']);
        $this->assertEquals('polygon', $data['network']);
        $this->assertIsBool($data['deployed']);
        $this->assertIsInt($data['nonce']);
        $this->assertIsInt($data['pending_ops']);
    }
}
