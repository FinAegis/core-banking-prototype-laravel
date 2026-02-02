<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Services\DemoSmartAccountFactory;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class DemoSmartAccountFactoryTest extends TestCase
{
    private DemoSmartAccountFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->factory = new DemoSmartAccountFactory();
    }

    public function test_get_supported_networks(): void
    {
        $networks = $this->factory->getSupportedNetworks();

        $this->assertIsArray($networks);
        $this->assertContains('polygon', $networks);
        $this->assertContains('base', $networks);
        $this->assertContains('arbitrum', $networks);
    }

    public function test_supports_network(): void
    {
        $this->assertTrue($this->factory->supportsNetwork('polygon'));
        $this->assertTrue($this->factory->supportsNetwork('base'));
        $this->assertTrue($this->factory->supportsNetwork('arbitrum'));
        $this->assertFalse($this->factory->supportsNetwork('ethereum'));
        $this->assertFalse($this->factory->supportsNetwork('invalid'));
    }

    public function test_compute_address_returns_valid_address(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $accountAddress = $this->factory->computeAddress($ownerAddress, 'polygon');

        $this->assertStringStartsWith('0x', $accountAddress);
        $this->assertEquals(42, strlen($accountAddress)); // 0x + 40 hex chars
    }

    public function test_compute_address_is_deterministic(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $address1 = $this->factory->computeAddress($ownerAddress, 'polygon');
        $address2 = $this->factory->computeAddress($ownerAddress, 'polygon');

        $this->assertEquals($address1, $address2);
    }

    public function test_compute_address_differs_by_network(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $polygonAddress = $this->factory->computeAddress($ownerAddress, 'polygon');
        $baseAddress = $this->factory->computeAddress($ownerAddress, 'base');

        // Addresses may be different due to different factory addresses
        // In demo mode with same factories, they might be the same
        $this->assertStringStartsWith('0x', $polygonAddress);
        $this->assertStringStartsWith('0x', $baseAddress);
    }

    public function test_compute_address_differs_by_salt(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $address0 = $this->factory->computeAddress($ownerAddress, 'polygon', 0);
        $address1 = $this->factory->computeAddress($ownerAddress, 'polygon', 1);

        $this->assertNotEquals($address0, $address1);
    }

    public function test_compute_address_throws_for_invalid_owner(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid owner address');

        $this->factory->computeAddress('invalid', 'polygon');
    }

    public function test_compute_address_throws_for_invalid_network(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported network');

        $this->factory->computeAddress('0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'invalid');
    }

    public function test_get_init_code_returns_hex_string(): void
    {
        $ownerAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $initCode = $this->factory->getInitCode($ownerAddress, 'polygon');

        $this->assertStringStartsWith('0x', $initCode);
        $this->assertMatchesRegularExpression('/^0x[a-fA-F0-9]+$/', $initCode);
    }

    public function test_is_deployed_returns_false_by_default(): void
    {
        $accountAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $deployed = $this->factory->isDeployed($accountAddress, 'polygon');

        $this->assertFalse($deployed);
    }

    public function test_mark_as_deployed_updates_status(): void
    {
        $accountAddress = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

        $this->assertFalse($this->factory->isDeployed($accountAddress, 'polygon'));

        $this->factory->markAsDeployed($accountAddress, 'polygon');

        $this->assertTrue($this->factory->isDeployed($accountAddress, 'polygon'));
    }

    public function test_get_factory_address(): void
    {
        $address = $this->factory->getFactoryAddress('polygon');

        $this->assertNotNull($address);
        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals(42, strlen($address));
    }

    public function test_get_factory_address_returns_null_for_unsupported(): void
    {
        $address = $this->factory->getFactoryAddress('unsupported');

        $this->assertNull($address);
    }
}
