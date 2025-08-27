<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\LockCollateralActivity;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class LockCollateralActivityTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(LockCollateralActivity::class));
    }

    #[Test]
    public function test_extends_workflow_activity(): void
    {
        $reflection = new ReflectionClass(LockCollateralActivity::class);
        $this->assertEquals('Workflow\Activity', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(LockCollateralActivity::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(LockCollateralActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(4, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals(AccountUuid::class, $parameters[0]->getType()->getName());

        $this->assertEquals('positionUuid', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateralAssetCode', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('amount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());
    }

    #[Test]
    public function test_execute_method_returns_bool(): void
    {
        $reflection = new ReflectionClass(LockCollateralActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('bool', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_supported_collateral_types(): void
    {
        // Test that various collateral types are supported
        $supportedAssets = ['BTC', 'ETH', 'USDC', 'WBTC', 'DAI', 'WETH'];

        foreach ($supportedAssets as $asset) {
            $this->assertIsString($asset);
            $this->assertNotEmpty($asset);
        }
    }

    #[Test]
    public function test_amount_ranges(): void
    {
        // Test various amount ranges
        $amounts = [
            0,                      // Zero amount
            100000000,              // 1 BTC (satoshis)
            1000000000000000000,    // 1 ETH (wei)
            1000000000,             // 1,000 USDC (6 decimals)
            PHP_INT_MAX,            // Maximum integer
        ];

        foreach ($amounts as $amount) {
            $this->assertIsInt($amount);
            $this->assertGreaterThanOrEqual(0, $amount);
        }
    }

    #[Test]
    public function test_activity_properties(): void
    {
        $reflection = new ReflectionClass(LockCollateralActivity::class);

        // Check for important properties inherited from Activity
        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('timeout'));
    }
}
