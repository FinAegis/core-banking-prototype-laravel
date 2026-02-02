<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class GasStationServiceTest extends TestCase
{
    use RefreshDatabase;

    private GasStationService $service;

    private PaymasterInterface&MockInterface $paymaster;

    private BundlerInterface&MockInterface $bundler;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->paymaster = Mockery::mock(PaymasterInterface::class);
        $this->bundler = Mockery::mock(BundlerInterface::class);
        $this->service = new GasStationService($this->paymaster, $this->bundler);
    }

    public function test_sponsors_transaction_successfully(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';
        $network = SupportedNetwork::POLYGON;

        $this->bundler->shouldReceive('estimateUserOperationGas')
            ->once()
            ->andReturn([
                'callGasLimit'         => 100000,
                'verificationGasLimit' => 50000,
                'preVerificationGas'   => 21000,
            ]);

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->andReturn([
                'gas_estimate' => 150000,
                'fee_usdc'     => 0.05,
                'fee_usdt'     => 0.05,
            ]);

        $this->paymaster->shouldReceive('getPaymasterData')
            ->once()
            ->andReturn('0xpaymasterdata');

        $this->bundler->shouldReceive('submitUserOperation')
            ->once()
            ->andReturn('0xuseroperationhash123456789');

        $result = $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            network: $network,
            feeToken: 'USDC'
        );

        $this->assertArrayHasKey('user_op_hash', $result);
        $this->assertArrayHasKey('fee_charged', $result);
        $this->assertArrayHasKey('fee_currency', $result);
        $this->assertArrayHasKey('is_deployment', $result);
        $this->assertFalse($result['is_deployment']);
        $this->assertEquals('USDC', $result['fee_currency']);
    }

    public function test_sponsors_deployment_transaction(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';
        $initCode = '0xabcdef1234567890abcdef12';
        $network = SupportedNetwork::POLYGON;

        $this->bundler->shouldReceive('estimateUserOperationGas')
            ->once()
            ->andReturn([
                'callGasLimit'         => 200000,
                'verificationGasLimit' => 100000,
                'preVerificationGas'   => 50000,
            ]);

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->andReturn([
                'gas_estimate' => 350000,
                'fee_usdc'     => 0.15,
                'fee_usdt'     => 0.15,
            ]);

        $this->paymaster->shouldReceive('getPaymasterData')
            ->once()
            ->andReturn('0xpaymasterdata');

        $this->bundler->shouldReceive('submitUserOperation')
            ->once()
            ->andReturn('0xuseroperationhash123456789');

        $result = $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            network: $network,
            feeToken: 'USDC',
            initCode: $initCode
        );

        $this->assertTrue($result['is_deployment']);
    }

    public function test_rejects_invalid_init_code_format(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';
        $invalidInitCode = 'not_valid_hex'; // Missing 0x prefix and not valid hex

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid initCode format');

        $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            initCode: $invalidInitCode
        );
    }

    public function test_empty_init_code_is_not_treated_as_deployment(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';

        $this->bundler->shouldReceive('estimateUserOperationGas')
            ->once()
            ->andReturn([
                'callGasLimit'         => 100000,
                'verificationGasLimit' => 50000,
                'preVerificationGas'   => 21000,
            ]);

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->andReturn([
                'gas_estimate' => 150000,
                'fee_usdc'     => 0.05,
                'fee_usdt'     => 0.05,
            ]);

        $this->paymaster->shouldReceive('getPaymasterData')
            ->once()
            ->andReturn('0xpaymasterdata');

        $this->bundler->shouldReceive('submitUserOperation')
            ->once()
            ->andReturn('0xuseroperationhash');

        // Empty string should NOT be treated as deployment
        $result = $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            initCode: ''
        );

        $this->assertFalse($result['is_deployment']);
    }

    public function test_estimates_fee_for_transaction(): void
    {
        $callData = '0xabcdef1234567890';
        $network = SupportedNetwork::POLYGON;

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->with($callData, $network)
            ->andReturn([
                'gas_estimate' => 150000,
                'fee_usdc'     => 0.05,
                'fee_usdt'     => 0.05,
            ]);

        $result = $this->service->estimateFee($callData, $network);

        $this->assertArrayHasKey('estimated_gas', $result);
        $this->assertArrayHasKey('fee_usdc', $result);
        $this->assertArrayHasKey('fee_usdt', $result);
        $this->assertArrayHasKey('network', $result);
        $this->assertEquals(150000, $result['estimated_gas']);
        $this->assertEquals('polygon', $result['network']);
    }

    public function test_returns_supported_networks(): void
    {
        $networks = $this->service->getSupportedNetworks();

        $this->assertIsArray($networks);
        $this->assertNotEmpty($networks);

        foreach ($networks as $network) {
            $this->assertArrayHasKey('chain_id', $network);
            $this->assertArrayHasKey('name', $network);
            $this->assertArrayHasKey('entrypoint_address', $network);
            $this->assertArrayHasKey('factory_address', $network);
            $this->assertArrayHasKey('paymaster_address', $network);
            $this->assertArrayHasKey('current_gas_price', $network);
            $this->assertArrayHasKey('average_fee_usdc', $network);
            $this->assertArrayHasKey('congestion_level', $network);
            $this->assertArrayHasKey('fee_token', $network);

            // Validate chain IDs
            $this->assertIsInt($network['chain_id']);
            $this->assertGreaterThan(0, $network['chain_id']);

            // Validate addresses are hex format
            $this->assertStringStartsWith('0x', $network['entrypoint_address']);
        }
    }

    public function test_supports_usdt_fee_token(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';

        $this->bundler->shouldReceive('estimateUserOperationGas')
            ->once()
            ->andReturn([
                'callGasLimit'         => 100000,
                'verificationGasLimit' => 50000,
                'preVerificationGas'   => 21000,
            ]);

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->andReturn([
                'gas_estimate' => 150000,
                'fee_usdc'     => 0.05,
                'fee_usdt'     => 0.06,
            ]);

        $this->paymaster->shouldReceive('getPaymasterData')
            ->once()
            ->andReturn('0xpaymasterdata');

        $this->bundler->shouldReceive('submitUserOperation')
            ->once()
            ->andReturn('0xuseroperationhash');

        $result = $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            feeToken: 'USDT'
        );

        $this->assertEquals('USDT', $result['fee_currency']);
        $this->assertEquals('0.060000', $result['fee_charged']);
    }

    public function test_works_with_different_networks(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';

        foreach ([SupportedNetwork::POLYGON, SupportedNetwork::ARBITRUM, SupportedNetwork::BASE] as $network) {
            $this->bundler->shouldReceive('estimateUserOperationGas')
                ->once()
                ->andReturn([
                    'callGasLimit'         => 100000,
                    'verificationGasLimit' => 50000,
                    'preVerificationGas'   => 21000,
                ]);

            $this->paymaster->shouldReceive('estimateFee')
                ->once()
                ->andReturn([
                    'gas_estimate' => 150000,
                    'fee_usdc'     => 0.05,
                    'fee_usdt'     => 0.05,
                ]);

            $this->paymaster->shouldReceive('getPaymasterData')
                ->once()
                ->andReturn('0xpaymasterdata');

            $this->bundler->shouldReceive('submitUserOperation')
                ->once()
                ->andReturn('0xuseroperationhash');

            $result = $this->service->sponsorTransaction(
                userAddress: $userAddress,
                callData: $callData,
                signature: $signature,
                network: $network
            );

            $this->assertArrayHasKey('user_op_hash', $result);
        }
    }

    public function test_only_0x_init_code_is_not_treated_as_deployment(): void
    {
        $userAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';
        $callData = '0xabcdef1234567890';
        $signature = '0x1234567890abcdef';

        $this->bundler->shouldReceive('estimateUserOperationGas')
            ->once()
            ->andReturn([
                'callGasLimit'         => 100000,
                'verificationGasLimit' => 50000,
                'preVerificationGas'   => 21000,
            ]);

        $this->paymaster->shouldReceive('estimateFee')
            ->once()
            ->andReturn([
                'gas_estimate' => 150000,
                'fee_usdc'     => 0.05,
                'fee_usdt'     => 0.05,
            ]);

        $this->paymaster->shouldReceive('getPaymasterData')
            ->once()
            ->andReturn('0xpaymasterdata');

        $this->bundler->shouldReceive('submitUserOperation')
            ->once()
            ->andReturn('0xuseroperationhash');

        // "0x" alone should NOT be treated as deployment
        $result = $this->service->sponsorTransaction(
            userAddress: $userAddress,
            callData: $callData,
            signature: $signature,
            initCode: '0x'
        );

        $this->assertFalse($result['is_deployment']);
    }
}
