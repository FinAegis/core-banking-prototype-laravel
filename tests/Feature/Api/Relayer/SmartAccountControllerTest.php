<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Relayer;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartAccountControllerTest extends TestCase
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

    public function test_create_account_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/relayer/account', [
            'owner_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'network'       => 'polygon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_create_account_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', []);

        $response->assertUnprocessable();
    }

    public function test_create_account_validates_owner_address_format(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => 'invalid',
                'network'       => 'polygon',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_account_validates_network(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
                'network'       => 'invalid',
            ]);

        $response->assertUnprocessable();
    }

    public function test_create_account_returns_smart_account(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
                'network'       => 'polygon',
            ]);

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

        $data = $response->json('data');
        $this->assertEquals('polygon', $data['network']);
        $this->assertFalse($data['deployed']);
        $this->assertEquals(0, $data['nonce']);
        $this->assertStringStartsWith('0x', $data['account_address']);
    }

    public function test_create_account_returns_existing_account(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        // Create first account
        $response1 = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        $accountId1 = $response1->json('data.id');

        // Create again should return same account
        $response2 = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        $accountId2 = $response2->json('data.id');

        $this->assertEquals($accountId1, $accountId2);
    }

    public function test_create_account_creates_different_accounts_per_network(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $polygonResponse = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        $baseResponse = $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'base',
            ]);

        $this->assertNotEquals(
            $polygonResponse->json('data.id'),
            $baseResponse->json('data.id')
        );
    }

    public function test_get_nonce_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/relayer/nonce/0x742d35Cc6634C0532925a3b844Bc454e4438f44e?network=polygon');

        $response->assertUnauthorized();
    }

    public function test_get_nonce_requires_network_parameter(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/nonce/0x742d35Cc6634C0532925a3b844Bc454e4438f44e');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ERR_RELAYER_105');
    }

    public function test_get_nonce_returns_404_for_unknown_account(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/nonce/0x742d35Cc6634C0532925a3b844Bc454e4438f44e?network=polygon');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'ERR_RELAYER_102');
    }

    public function test_get_nonce_returns_nonce_info(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        // Create account first
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        // Get nonce
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/relayer/nonce/{$ownerAddress}?network=polygon");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'nonce',
                    'pending_ops',
                    'deployed',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(0, $data['nonce']);
        $this->assertEquals(0, $data['pending_ops']);
        $this->assertFalse($data['deployed']);
    }

    public function test_get_init_code_returns_init_code_for_undeployed(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        // Create account first
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        // Get init code
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/relayer/init-code/{$ownerAddress}?network=polygon");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.needs_deployment', true);

        $initCode = $response->json('data.init_code');
        $this->assertNotEmpty($initCode);
        $this->assertStringStartsWith('0x', $initCode);
    }

    public function test_list_accounts_returns_user_accounts(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        // Create accounts on multiple networks
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'polygon',
            ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => $ownerAddress,
                'network'       => 'base',
            ]);

        // List accounts
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/accounts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_accounts_does_not_return_other_user_accounts(): void
    {
        // Create account for first user
        $this->withToken($this->token)
            ->postJson('/api/v1/relayer/account', [
                'owner_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
                'network'       => 'polygon',
            ]);

        // Create second user and switch auth context using Sanctum::actingAs
        // (withToken doesn't properly switch auth context mid-test)
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser, ['read', 'write']);

        // List accounts as other user (should be empty)
        $response = $this->getJson('/api/v1/relayer/accounts');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
