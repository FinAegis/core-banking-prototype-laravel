<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Privacy;

use App\Domain\Privacy\Models\RailgunWallet;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Domain\Privacy\Services\RailgunBridgeClient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Feature tests for RAILGUN privacy integration endpoints.
 * Tests run with ZK_PROVIDER=railgun and faked bridge responses.
 */
class RailgunIntegrationTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;

        // Set RAILGUN mode
        config(['privacy.zk.provider' => 'railgun']);
        config(['privacy.merkle.provider' => 'railgun']);

        // Fake all bridge HTTP calls
        Http::fake([
            '127.0.0.1:3100/*' => Http::response([
                'success' => true,
                'data'    => [],
            ]),
        ]);
    }

    public function test_get_networks_returns_railgun_supported_chains(): void
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

        $networks = $response->json('data.networks');
        $this->assertContains('ethereum', $networks);
        $this->assertContains('polygon', $networks);
        $this->assertContains('arbitrum', $networks);
        $this->assertContains('bsc', $networks);
        $this->assertNotContains('base', $networks);
        $this->assertEquals('railgun', $response->json('data.provider'));
    }

    public function test_get_shielded_balances_in_railgun_mode(): void
    {
        // Create a wallet for the user
        RailgunWallet::create([
            'user_id'            => $this->user->id,
            'railgun_address'    => '0zk_integration_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        // Mock the bridge to return balances
        $this->mockBridgeBalances();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/balances');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['token', 'balance', 'network'],
                ],
            ]);
    }

    public function test_get_total_shielded_balance_in_railgun_mode(): void
    {
        ShieldedBalance::create([
            'user_id'         => $this->user->id,
            'railgun_address' => '0zk_test',
            'token'           => 'USDC',
            'network'         => 'polygon',
            'balance'         => '150.00',
            'last_synced_at'  => now(),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/total-balance');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_shield_in_railgun_mode(): void
    {
        $this->mockBridgeForShield();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/shield', [
                'amount'  => '100.00',
                'token'   => 'USDC',
                'network' => 'polygon',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation', 'shield')
            ->assertJsonPath('data.status', 'transaction_ready');
    }

    public function test_unshield_in_railgun_mode(): void
    {
        RailgunWallet::create([
            'user_id'            => $this->user->id,
            'railgun_address'    => '0zk_unshield_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->mockBridgeForUnshield();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/unshield', [
                'amount'    => '50.00',
                'token'     => 'USDC',
                'network'   => 'polygon',
                'recipient' => '0x1234567890abcdef1234567890abcdef12345678',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation', 'unshield');
    }

    public function test_private_transfer_in_railgun_mode(): void
    {
        RailgunWallet::create([
            'user_id'            => $this->user->id,
            'railgun_address'    => '0zk_transfer_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->mockBridgeForTransfer();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/transfer', [
                'amount'               => '25.00',
                'token'                => 'USDC',
                'network'              => 'polygon',
                'recipient_commitment' => '0zk_recipient_address',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operation', 'transfer');
    }

    public function test_get_viewing_key_in_railgun_mode(): void
    {
        RailgunWallet::create([
            'user_id'            => $this->user->id,
            'railgun_address'    => '0zk_viewing_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/privacy/viewing-key');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['viewing_key', 'created_at'],
            ]);

        $viewingKey = $response->json('data.viewing_key');
        $this->assertStringStartsWith('0x', $viewingKey);
    }

    public function test_shield_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/privacy/shield', [
            'amount'  => '100.00',
            'token'   => 'USDC',
            'network' => 'polygon',
        ]);

        $response->assertUnauthorized();
    }

    public function test_shield_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/privacy/shield', []);

        $response->assertUnprocessable();
    }

    private function mockBridgeBalances(): void
    {
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $bridge->shouldReceive('getBalances')
            ->andReturn([
                'wallet_id' => 'w1',
                'network'   => 'polygon',
                'balances'  => [
                    '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359' => '100000000',
                ],
            ]);

        $this->app->instance(RailgunBridgeClient::class, $bridge);
    }

    private function mockBridgeForShield(): void
    {
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $bridge->shouldReceive('createWallet')
            ->andReturn([
                'wallet_id'       => 'w1',
                'railgun_address' => '0zk_shield_integration',
            ]);
        $bridge->shouldReceive('shield')
            ->andReturn([
                'transaction'  => ['to' => '0xabc', 'data' => '0x123', 'value' => '0'],
                'gas_estimate' => '150000',
                'network'      => 'polygon',
            ]);

        $this->app->instance(RailgunBridgeClient::class, $bridge);
    }

    private function mockBridgeForUnshield(): void
    {
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $bridge->shouldReceive('unshield')
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x456', 'value' => '0'],
                'network'     => 'polygon',
            ]);

        $this->app->instance(RailgunBridgeClient::class, $bridge);
    }

    private function mockBridgeForTransfer(): void
    {
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $bridge->shouldReceive('privateTransfer')
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x789', 'value' => '0'],
                'network'     => 'polygon',
            ]);

        $this->app->instance(RailgunBridgeClient::class, $bridge);
    }
}
