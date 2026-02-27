<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Models\RailgunWallet;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Domain\Privacy\Services\RailgunBridgeClient;
use App\Domain\Privacy\Services\RailgunPrivacyService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RailgunPrivacyServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var RailgunBridgeClient&MockInterface */
    private RailgunBridgeClient $bridge;

    /** @var MerkleTreeServiceInterface&MockInterface */
    private MerkleTreeServiceInterface $merkleService;

    private RailgunPrivacyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $this->bridge = $bridge;
        /** @var MerkleTreeServiceInterface&MockInterface $merkleService */
        $merkleService = Mockery::mock(MerkleTreeServiceInterface::class);
        $this->merkleService = $merkleService;
        $this->service = new RailgunPrivacyService($this->bridge, $this->merkleService);
    }

    public function test_create_wallet_for_user(): void
    {
        $user = User::factory()->create();

        $this->bridge
            ->shouldReceive('createWallet')
            ->once()
            ->andReturn([
                'wallet_id'       => 'w1',
                'railgun_address' => '0zk1234567890abcdef',
            ]);

        $wallet = $this->service->createWalletForUser($user, 'polygon');

        $this->assertInstanceOf(RailgunWallet::class, $wallet);
        $this->assertEquals('0zk1234567890abcdef', $wallet->railgun_address);
        $this->assertEquals('polygon', $wallet->network);
        $this->assertEquals($user->id, $wallet->user_id);
        $this->assertTrue($wallet->isActive());
    }

    public function test_create_wallet_returns_existing(): void
    {
        $user = User::factory()->create();

        $existing = RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_existing',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $wallet = $this->service->createWalletForUser($user, 'polygon');

        $this->assertEquals($existing->id, $wallet->id);
        $this->assertEquals('0zk_existing', $wallet->railgun_address);
    }

    public function test_get_shielded_balances_from_bridge(): void
    {
        $user = User::factory()->create();
        $wallet = RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->bridge
            ->shouldReceive('getBalances')
            ->with($wallet->id, 'polygon')
            ->once()
            ->andReturn([
                'wallet_id' => $wallet->id,
                'network'   => 'polygon',
                'balances'  => [
                    '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359' => '100000000', // 100 USDC
                ],
            ]);

        $balances = $this->service->getShieldedBalances($user, 'polygon');

        $this->assertNotEmpty($balances);
        $this->assertEquals('USDC', $balances[0]['token']);
        $this->assertEquals('polygon', $balances[0]['network']);
    }

    public function test_get_shielded_balances_empty_for_no_wallets(): void
    {
        $user = User::factory()->create();

        $this->merkleService
            ->shouldReceive('getSupportedNetworks')
            ->once()
            ->andReturn(['ethereum', 'polygon', 'arbitrum', 'bsc']);

        $balances = $this->service->getShieldedBalances($user);

        // Should return zero balances for all networks/tokens
        $this->assertNotEmpty($balances);
        foreach ($balances as $balance) {
            $this->assertEquals('0.00', $balance['balance']);
        }
    }

    public function test_get_shielded_balances_falls_back_to_cache(): void
    {
        $user = User::factory()->create();
        $wallet = RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_cached',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        // Create a cached balance
        ShieldedBalance::create([
            'user_id'         => $user->id,
            'railgun_address' => '0zk_cached',
            'token'           => 'USDC',
            'network'         => 'polygon',
            'balance'         => '50.00',
            'last_synced_at'  => now()->subMinutes(2),
        ]);

        $this->bridge
            ->shouldReceive('getBalances')
            ->andThrow(new RuntimeException('Bridge down'));

        $balances = $this->service->getShieldedBalances($user, 'polygon');

        $this->assertCount(1, $balances);
        $this->assertEquals('USDC', $balances[0]['token']);
        $this->assertEquals('50.00', $balances[0]['balance']);
    }

    public function test_get_total_shielded_balance(): void
    {
        $user = User::factory()->create();

        ShieldedBalance::create([
            'user_id'         => $user->id,
            'railgun_address' => '0zk_test',
            'token'           => 'USDC',
            'network'         => 'polygon',
            'balance'         => '100.50',
            'last_synced_at'  => now(),
        ]);

        ShieldedBalance::create([
            'user_id'         => $user->id,
            'railgun_address' => '0zk_test',
            'token'           => 'USDT',
            'network'         => 'polygon',
            'balance'         => '25.00',
            'last_synced_at'  => now(),
        ]);

        $result = $this->service->getTotalShieldedBalance($user);

        $this->assertEquals('125.50', $result['total_balance']);
        $this->assertEquals('USD', $result['currency']);
    }

    public function test_shield(): void
    {
        $user = User::factory()->create();

        $this->bridge
            ->shouldReceive('createWallet')
            ->once()
            ->andReturn([
                'wallet_id'       => 'w1',
                'railgun_address' => '0zk_shield_test',
            ]);

        $this->bridge
            ->shouldReceive('shield')
            ->once()
            ->andReturn([
                'transaction'  => ['to' => '0xabc', 'data' => '0x123', 'value' => '0'],
                'gas_estimate' => '150000',
                'network'      => 'polygon',
            ]);

        $result = $this->service->shield($user, 'USDC', '100.00', 'polygon');

        $this->assertEquals('shield', $result['operation']);
        $this->assertEquals('transaction_ready', $result['status']);
        $this->assertArrayHasKey('transaction', $result);
    }

    public function test_unshield(): void
    {
        $user = User::factory()->create();
        RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_unshield',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->bridge
            ->shouldReceive('unshield')
            ->once()
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x456', 'value' => '0'],
                'network'     => 'polygon',
            ]);

        $result = $this->service->unshield($user, '0xrecipient', 'USDC', '50.00', 'polygon');

        $this->assertEquals('unshield', $result['operation']);
        $this->assertEquals('0xrecipient', $result['recipient']);
    }

    public function test_private_transfer(): void
    {
        $user = User::factory()->create();
        RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_transfer',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->bridge
            ->shouldReceive('privateTransfer')
            ->once()
            ->andReturn([
                'transaction' => ['to' => '0xabc', 'data' => '0x789', 'value' => '0'],
                'network'     => 'polygon',
            ]);

        $result = $this->service->privateTransfer($user, '0zk_recipient', 'USDC', '25.00', 'polygon');

        $this->assertEquals('transfer', $result['operation']);
    }

    public function test_get_viewing_key_with_wallet(): void
    {
        $user = User::factory()->create();
        RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_viewing_key_test',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $viewingKey = $this->service->getViewingKey($user);

        $this->assertStringStartsWith('0x', $viewingKey);
        $this->assertEquals(66, strlen($viewingKey)); // 0x + 64 hex chars
    }

    public function test_get_viewing_key_without_wallet(): void
    {
        $user = User::factory()->create();

        $viewingKey = $this->service->getViewingKey($user);

        $this->assertStringStartsWith('0x', $viewingKey);
    }

    public function test_shield_throws_for_unsupported_token(): void
    {
        $user = User::factory()->create();
        RailgunWallet::create([
            'user_id'            => $user->id,
            'railgun_address'    => '0zk_unsupported',
            'encrypted_mnemonic' => 'encrypted-data',
            'network'            => 'polygon',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not supported on');

        $this->service->shield($user, 'DOGE', '100.00', 'polygon');
    }
}
