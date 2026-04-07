<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\PimlicoBundlerService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PimlicoBundlerServiceTest extends TestCase
{
    private PimlicoBundlerService $service;

    /** @var EthRpcClient&MockInterface */
    private EthRpcClient $rpcClient;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EthRpcClient&MockInterface $rpcClient */
        $rpcClient = Mockery::mock(EthRpcClient::class);
        $this->rpcClient = $rpcClient;
        $this->service = new PimlicoBundlerService($this->rpcClient);
    }

    public function test_submits_user_operation_successfully(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef1234567890',
        );

        $expectedHash = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->with(
                SupportedNetwork::POLYGON,
                'eth_sendUserOperation',
                Mockery::type('array')
            )
            ->andReturn($expectedHash);

        $result = $this->service->submitUserOperation($userOp, SupportedNetwork::POLYGON);

        $this->assertEquals($expectedHash, $result);
    }

    public function test_throws_on_bundler_error(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef',
        );

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->andThrow(new RpcException('AA21 didn\'t pay prefund', 'eth_sendUserOperation', -32500));

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('AA21');

        $this->service->submitUserOperation($userOp, SupportedNetwork::POLYGON);
    }

    public function test_estimates_gas_from_bundler(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 1,
            callData: '0xabcdef',
        );

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->with(
                SupportedNetwork::POLYGON,
                'eth_estimateUserOperationGas',
                Mockery::type('array')
            )
            ->andReturn([
                'preVerificationGas'   => '0xc350',   // 50000
                'verificationGasLimit' => '0x186a0',  // 100000
                'callGasLimit'         => '0x30d40',  // 200000
            ]);

        $result = $this->service->estimateUserOperationGas($userOp, SupportedNetwork::POLYGON);

        $this->assertEquals(50000, $result['preVerificationGas']);
        $this->assertEquals(100000, $result['verificationGasLimit']);
        $this->assertEquals(200000, $result['callGasLimit']);
    }

    public function test_falls_back_to_defaults_on_missing_gas_fields(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef',
        );

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->andReturn([]);

        $result = $this->service->estimateUserOperationGas($userOp, SupportedNetwork::POLYGON);

        $this->assertEquals(50000, $result['preVerificationGas']);
        $this->assertEquals(100000, $result['verificationGasLimit']);
        $this->assertEquals(100000, $result['callGasLimit']);
    }

    public function test_gets_user_operation_status_confirmed(): void
    {
        $hash = '0xuseroperationhash123';

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->with(
                Mockery::type(SupportedNetwork::class),
                'eth_getUserOperationReceipt',
                [$hash]
            )
            ->andReturn([
                'success'       => true,
                'actualGasUsed' => '0x249f0', // 150000
                'receipt'       => [
                    'transactionHash' => '0xtxhash456',
                    'blockNumber'     => '0x3456789',
                ],
            ]);

        $result = $this->service->getUserOperationStatus($hash);

        $this->assertEquals('confirmed', $result['status']);
        $this->assertEquals('0xtxhash456', $result['tx_hash']);
        $this->assertNotNull($result['receipt']);
        $this->assertTrue($result['receipt']['success']);
        $this->assertEquals(150000, $result['receipt']['gasUsed']);
    }

    public function test_gets_user_operation_status_pending(): void
    {
        $hash = '0xuseroperationhash123';

        // All networks return null (not found)
        $this->rpcClient->shouldReceive('bundlerCall')
            ->times(5) // one per SupportedNetwork case
            ->andReturn(null);

        $result = $this->service->getUserOperationStatus($hash);

        $this->assertEquals('pending', $result['status']);
        $this->assertNull($result['tx_hash']);
        $this->assertNull($result['receipt']);
    }

    public function test_gets_entry_point_address(): void
    {
        $address = $this->service->getEntryPointAddress(SupportedNetwork::POLYGON);

        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals('0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789', $address);
    }
}
