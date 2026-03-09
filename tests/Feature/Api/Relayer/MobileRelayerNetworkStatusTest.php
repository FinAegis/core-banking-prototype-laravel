<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Relayer;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MobileRelayerNetworkStatusTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    public function test_network_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/relayer/networks/polygon/status');

        $response->assertUnauthorized();
    }

    public function test_network_status_returns_data_for_polygon(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/networks/polygon/status');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'chainId',
                    'network',
                    'status',
                    'gasPrice' => ['gwei', 'usdEstimate'],
                    'blockNumber',
                    'relayer' => ['status', 'queueDepth', 'avgConfirmationMs'],
                    'updatedAt',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.chainId', 137)
            ->assertJsonPath('data.network', 'polygon')
            ->assertJsonPath('data.status', 'operational');
    }

    public function test_network_status_returns_data_for_arbitrum(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/networks/arbitrum/status');

        $response->assertOk()
            ->assertJsonPath('data.chainId', 42161)
            ->assertJsonPath('data.network', 'arbitrum');
    }

    public function test_network_status_returns_data_for_base(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/networks/base/status');

        $response->assertOk()
            ->assertJsonPath('data.chainId', 8453)
            ->assertJsonPath('data.network', 'base');
    }

    public function test_network_status_rejects_unsupported_network(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/relayer/networks/solana/status');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNSUPPORTED_NETWORK');
    }
}
