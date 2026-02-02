<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Exceptions\SmartAccountException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SmartAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmartAccountService $service;

    private SmartAccountFactoryInterface&MockInterface $factory;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Mockery::mock(SmartAccountFactoryInterface::class);
        $this->service = new SmartAccountService($this->factory);
        $this->user = User::factory()->create();
    }

    public function test_creates_new_account_when_none_exists(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $accountAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $network = 'polygon';

        $this->factory->shouldReceive('supportsNetwork')
            ->with($network)
            ->andReturn(true);

        $this->factory->shouldReceive('computeAddress')
            ->once()
            ->with($ownerAddress, $network)
            ->andReturn($accountAddress);

        $account = $this->service->getOrCreateAccount($this->user, $ownerAddress, $network);

        $this->assertInstanceOf(SmartAccount::class, $account);
        $this->assertEquals(strtolower($ownerAddress), $account->owner_address);
        $this->assertEquals(strtolower($accountAddress), $account->account_address);
        $this->assertEquals($network, $account->network);
        $this->assertFalse($account->deployed);
        $this->assertEquals(0, $account->nonce);
        $this->assertEquals(0, $account->pending_ops);
    }

    public function test_returns_existing_account(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $network = 'polygon';

        // Pre-create an account
        $existingAccount = SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => strtolower($ownerAddress),
            'account_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 0,
        ]);

        // Factory should check network support but NOT compute address for existing account
        $this->factory->shouldReceive('supportsNetwork')
            ->with($network)
            ->andReturn(true);
        $this->factory->shouldNotReceive('computeAddress');

        $account = $this->service->getOrCreateAccount($this->user, $ownerAddress, $network);

        $this->assertEquals($existingAccount->id, $account->id);
    }

    public function test_normalizes_owner_address_to_lowercase(): void
    {
        $ownerAddress = '0x742D35CC6634C0532925A3B844BC454E4438F44E'; // Mixed case
        $network = 'polygon';

        $this->factory->shouldReceive('supportsNetwork')
            ->with($network)
            ->andReturn(true);

        $this->factory->shouldReceive('computeAddress')
            ->once()
            ->andReturn('0x1234567890abcdef1234567890abcdef12345678');

        $account = $this->service->getOrCreateAccount($this->user, $ownerAddress, $network);

        $this->assertEquals(strtolower($ownerAddress), $account->owner_address);
    }

    public function test_increments_pending_ops(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';
        $network = 'polygon';

        $account = SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => $ownerAddress,
            'account_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 0,
        ]);

        $this->service->incrementPendingOps($ownerAddress, $network);

        $account->refresh();
        $this->assertEquals(1, $account->pending_ops);
    }

    public function test_processes_completed_op(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';
        $network = 'polygon';

        $account = SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => $ownerAddress,
            'account_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 2,
        ]);

        $this->service->processCompletedOp($ownerAddress, $network);

        $account->refresh();
        $this->assertEquals(1, $account->nonce);
        $this->assertEquals(1, $account->pending_ops);
    }

    public function test_marks_account_as_deployed(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';
        $accountAddress = '0x1234567890abcdef1234567890abcdef12345678';
        $network = 'polygon';
        $txHash = '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab';

        $account = SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => $ownerAddress,
            'account_address' => $accountAddress,
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 0,
        ]);

        // markDeployed uses owner address, not account address
        $this->service->markDeployed($ownerAddress, $network, $txHash);

        $account->refresh();
        $this->assertTrue($account->deployed);
        $this->assertEquals($txHash, $account->deploy_tx_hash);
    }

    public function test_get_nonce_info_returns_account_state(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';
        $network = 'polygon';

        SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => $ownerAddress,
            'account_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network'         => $network,
            'deployed'        => true,
            'nonce'           => 5,
            'pending_ops'     => 2,
        ]);

        $info = $this->service->getNonceInfo($ownerAddress, $network);

        $this->assertEquals(5, $info['nonce']);
        $this->assertEquals(2, $info['pending_ops']);
        $this->assertTrue($info['deployed']);
    }

    public function test_throws_exception_when_account_not_found_for_nonce(): void
    {
        $this->expectException(SmartAccountException::class);

        $this->service->getNonceInfo(
            '0x0000000000000000000000000000000000000000',
            'polygon'
        );
    }

    public function test_creates_accounts_for_different_networks(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';

        $this->factory->shouldReceive('supportsNetwork')
            ->andReturn(true);

        $this->factory->shouldReceive('computeAddress')
            ->twice()
            ->andReturn(
                '0x1111111111111111111111111111111111111111',
                '0x2222222222222222222222222222222222222222'
            );

        $polygonAccount = $this->service->getOrCreateAccount($this->user, $ownerAddress, 'polygon');
        $baseAccount = $this->service->getOrCreateAccount($this->user, $ownerAddress, 'base');

        $this->assertNotEquals($polygonAccount->id, $baseAccount->id);
        $this->assertEquals('polygon', $polygonAccount->network);
        $this->assertEquals('base', $baseAccount->network);
    }

    public function test_pending_ops_does_not_go_below_zero(): void
    {
        $ownerAddress = '0x742d35cc6634c0532925a3b844bc454e4438f44e';
        $network = 'polygon';

        $account = SmartAccount::create([
            'user_id'         => $this->user->id,
            'owner_address'   => $ownerAddress,
            'account_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 0,
        ]);

        // Try to decrement when already at 0
        $account->decrementPendingOps();
        $account->refresh();

        $this->assertEquals(0, $account->pending_ops);
    }
}
