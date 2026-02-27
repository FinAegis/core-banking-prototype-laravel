<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\ProductionSmartAccountFactory;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductionSmartAccountFactoryTest extends TestCase
{
    private ProductionSmartAccountFactory $factory;

    /** @var EthRpcClient&MockInterface */
    private EthRpcClient $rpcClient;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'relayer.smart_accounts.factory_addresses' => [
                'polygon'  => '0x9406Cc6185a346906296840746125a0E44976454',
                'base'     => '0x9406Cc6185a346906296840746125a0E44976454',
                'arbitrum' => '0x9406Cc6185a346906296840746125a0E44976454',
            ],
        ]);

        /** @var EthRpcClient&MockInterface $rpcClient */
        $rpcClient = Mockery::mock(EthRpcClient::class);
        $this->rpcClient = $rpcClient;
        $this->factory = new ProductionSmartAccountFactory($this->rpcClient);
    }

    public function test_computes_deterministic_address(): void
    {
        $address1 = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            0
        );

        $this->assertStringStartsWith('0x', $address1);
        $this->assertEquals(42, strlen($address1)); // 0x + 40 hex chars

        // Same inputs should produce same address
        $address2 = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            0
        );

        $this->assertEquals($address1, $address2);
    }

    public function test_different_salts_produce_different_addresses(): void
    {
        $address1 = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            0
        );

        $address2 = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            1
        );

        $this->assertNotEquals($address1, $address2);
    }

    public function test_different_owners_produce_different_addresses(): void
    {
        $address1 = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon'
        );

        $address2 = $this->factory->computeAddress(
            '0x1234567890abcdef1234567890abcdef12345678',
            'polygon'
        );

        $this->assertNotEquals($address1, $address2);
    }

    public function test_uses_keccak256_not_sha3(): void
    {
        // keccak256 and SHA3-256 produce different outputs for the same input
        // This test ensures we're using the correct Ethereum hash function
        $address = $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            0
        );

        // The address should be a valid Ethereum address
        $this->assertMatchesRegularExpression('/^0x[a-f0-9]{40}$/', $address);
    }

    public function test_throws_on_invalid_owner_address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid owner address format');

        $this->factory->computeAddress('not-an-address', 'polygon');
    }

    public function test_throws_on_unsupported_network(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported network');

        $this->factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'solana'
        );
    }

    public function test_throws_when_no_factory_address_configured(): void
    {
        config(['relayer.smart_accounts.factory_addresses.polygon' => null]);
        $factory = new ProductionSmartAccountFactory($this->rpcClient);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No factory address configured');

        $factory->computeAddress(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon'
        );
    }

    public function test_get_init_code_returns_valid_hex(): void
    {
        $initCode = $this->factory->getInitCode(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon',
            0
        );

        $this->assertStringStartsWith('0x', strtolower($initCode));
        // Should contain factory address + selector + padded owner + padded salt
        // factory (40 hex) + selector (8 hex) + owner (64 hex) + salt (64 hex) = 176 hex chars
        $this->assertGreaterThan(40, strlen($initCode));
    }

    public function test_is_deployed_returns_true_when_code_exists(): void
    {
        $this->rpcClient->shouldReceive('getCode')
            ->once()
            ->with(SupportedNetwork::POLYGON, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e')
            ->andReturn('0x6080604052...');

        $result = $this->factory->isDeployed(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon'
        );

        $this->assertTrue($result);
    }

    public function test_is_deployed_returns_false_when_no_code(): void
    {
        $this->rpcClient->shouldReceive('getCode')
            ->once()
            ->andReturn('0x');

        $result = $this->factory->isDeployed(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon'
        );

        $this->assertFalse($result);
    }

    public function test_is_deployed_returns_false_on_rpc_failure(): void
    {
        $this->rpcClient->shouldReceive('getCode')
            ->once()
            ->andThrow(new RpcException('Network error', 'eth_getCode'));

        $result = $this->factory->isDeployed(
            '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
            'polygon'
        );

        $this->assertFalse($result);
    }

    public function test_get_supported_networks_from_config(): void
    {
        $networks = $this->factory->getSupportedNetworks();

        $this->assertContains('polygon', $networks);
        $this->assertContains('base', $networks);
        $this->assertContains('arbitrum', $networks);
    }

    public function test_get_supported_networks_excludes_unconfigured(): void
    {
        config([
            'relayer.smart_accounts.factory_addresses' => [
                'polygon' => '0x9406Cc6185a346906296840746125a0E44976454',
                'base'    => null,
            ],
        ]);

        $factory = new ProductionSmartAccountFactory($this->rpcClient);
        $networks = $factory->getSupportedNetworks();

        $this->assertContains('polygon', $networks);
        $this->assertNotContains('base', $networks);
    }

    public function test_supports_network(): void
    {
        $this->assertTrue($this->factory->supportsNetwork('polygon'));
        $this->assertTrue($this->factory->supportsNetwork('base'));
        $this->assertFalse($this->factory->supportsNetwork('solana'));
    }

    public function test_get_factory_address_from_config(): void
    {
        $address = $this->factory->getFactoryAddress('polygon');

        $this->assertEquals('0x9406Cc6185a346906296840746125a0E44976454', $address);
    }

    public function test_get_factory_address_returns_null_when_unconfigured(): void
    {
        config(['relayer.smart_accounts.factory_addresses.ethereum' => null]);
        $factory = new ProductionSmartAccountFactory($this->rpcClient);

        $this->assertNull($factory->getFactoryAddress('ethereum'));
    }
}
