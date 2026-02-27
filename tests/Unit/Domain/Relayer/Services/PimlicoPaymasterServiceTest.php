<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\PimlicoPaymasterService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PimlicoPaymasterServiceTest extends TestCase
{
    private PimlicoPaymasterService $service;

    /** @var EthRpcClient&MockInterface */
    private EthRpcClient $rpcClient;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EthRpcClient&MockInterface $rpcClient */
        $rpcClient = Mockery::mock(EthRpcClient::class);
        $this->rpcClient = $rpcClient;
        $this->service = new PimlicoPaymasterService($this->rpcClient);
    }

    public function test_will_sponsor_valid_user_operation(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef1234567890',
        );

        $this->assertTrue($this->service->willSponsor($userOp));
    }

    public function test_will_not_sponsor_empty_sender(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x',
            nonce: 0,
            callData: '0xabcdef',
        );

        $this->assertFalse($this->service->willSponsor($userOp));
    }

    public function test_will_not_sponsor_empty_call_data(): void
    {
        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0x',
        );

        $this->assertFalse($this->service->willSponsor($userOp));
    }

    public function test_gets_paymaster_data_from_pimlico(): void
    {
        config(['relayer.default_network' => 'polygon']);

        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef',
        );

        $expectedData = '0xpaymasterAndDataFromPimlico123456';

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->with(
                SupportedNetwork::POLYGON,
                'pm_sponsorUserOperation',
                Mockery::type('array')
            )
            ->andReturn(['paymasterAndData' => $expectedData]);

        $result = $this->service->getPaymasterData($userOp, 'USDC', 0.05);

        $this->assertEquals($expectedData, $result);
    }

    public function test_throws_when_pimlico_sponsorship_fails(): void
    {
        config(['relayer.default_network' => 'polygon']);

        $userOp = UserOperation::createUnsigned(
            sender: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            nonce: 0,
            callData: '0xabcdef',
        );

        $this->rpcClient->shouldReceive('bundlerCall')
            ->once()
            ->andThrow(new RpcException('Sponsorship rejected', 'pm_sponsorUserOperation'));

        $this->expectException(RpcException::class);
        $this->expectExceptionMessage('Sponsorship rejected');

        $this->service->getPaymasterData($userOp, 'USDC', 0.05);
    }

    public function test_estimates_fee_with_real_gas_price(): void
    {
        $callData = '0xabcdef1234567890';

        // 30 gwei in hex
        $this->rpcClient->shouldReceive('getGasPrice')
            ->once()
            ->with(SupportedNetwork::POLYGON)
            ->andReturn('0x6fc23ac00'); // ~30 gwei

        $result = $this->service->estimateFee($callData, SupportedNetwork::POLYGON);

        $this->assertArrayHasKey('gas_estimate', $result);
        $this->assertArrayHasKey('fee_usdc', $result);
        $this->assertArrayHasKey('fee_usdt', $result);
        $this->assertGreaterThan(0, $result['gas_estimate']);
        $this->assertGreaterThanOrEqual(0.01, $result['fee_usdc']); // At least minimum fee
    }

    public function test_estimates_fee_falls_back_on_rpc_failure(): void
    {
        $callData = '0xabcdef1234567890';

        $this->rpcClient->shouldReceive('getGasPrice')
            ->once()
            ->andThrow(new RpcException('RPC unavailable', 'eth_gasPrice'));

        $result = $this->service->estimateFee($callData, SupportedNetwork::POLYGON);

        // Should still return valid estimates using static fallback
        $this->assertArrayHasKey('gas_estimate', $result);
        $this->assertArrayHasKey('fee_usdc', $result);
        $this->assertGreaterThan(0, $result['gas_estimate']);
    }

    public function test_fee_respects_minimum(): void
    {
        config(['relayer.fees.minimum_fee' => 0.05]);

        $callData = '0x00'; // Very small calldata

        $this->rpcClient->shouldReceive('getGasPrice')
            ->once()
            ->andReturn('0x1'); // Extremely low gas price

        $result = $this->service->estimateFee($callData, SupportedNetwork::POLYGON);

        $this->assertGreaterThanOrEqual(0.05, $result['fee_usdc']);
    }

    public function test_gets_paymaster_address_for_network(): void
    {
        config(['relayer.networks.polygon.paymaster_address' => '0xPaymaster123']);

        $address = $this->service->getAddress(SupportedNetwork::POLYGON);

        $this->assertEquals('0xPaymaster123', $address);
    }
}
