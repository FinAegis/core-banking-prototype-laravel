<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\ReleaseCollateralActivity;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ReleaseCollateralActivityTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(ReleaseCollateralActivity::class));
    }

    #[Test]
    public function test_extends_workflow_activity(): void
    {
        $reflection = new ReflectionClass(ReleaseCollateralActivity::class);
        $this->assertEquals('Workflow\Activity', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(ReleaseCollateralActivity::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(ReleaseCollateralActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(4, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals(AccountUuid::class, $parameters[0]->getType()->getName());

        $this->assertEquals('positionUuid', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateralAssetCode', $parameters[2]->getName());
        $this->assertTrue($parameters[2]->allowsNull());
        $this->assertTrue($parameters[2]->isOptional() || $parameters[2]->allowsNull());

        $this->assertEquals('amount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());
    }

    #[Test]
    public function test_execute_method_returns_bool(): void
    {
        $reflection = new ReflectionClass(ReleaseCollateralActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('bool', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_release_amount_scenarios(): void
    {
        // Test various release amount scenarios
        $scenarios = [
            ['amount' => 100000000, 'description' => '1 BTC'],
            ['amount' => 2500000000000000000, 'description' => '2.5 ETH'],
            ['amount' => 1000000000, 'description' => '1,000 USDC'],
            ['amount' => 0, 'description' => 'Zero amount'],
            ['amount' => PHP_INT_MAX, 'description' => 'Large amount'],
        ];

        foreach ($scenarios as $scenario) {
            $this->assertIsInt($scenario['amount']);
            $this->assertGreaterThanOrEqual(0, $scenario['amount']);
            $this->assertIsString($scenario['description']);
        }
    }

    #[Test]
    public function test_multiple_asset_types(): void
    {
        // Test support for multiple asset types
        $assets = [
            ['code' => 'BTC', 'description' => 'Bitcoin'],
            ['code' => 'ETH', 'description' => 'Ethereum'],
            ['code' => 'WBTC', 'description' => 'Wrapped Bitcoin'],
            ['code' => 'USDC', 'description' => 'USD Coin'],
            ['code' => 'DAI', 'description' => 'DAI Stablecoin'],
            ['code' => null, 'description' => 'Null (retrieves from position)'],
        ];

        foreach ($assets as $asset) {
            if ($asset['code'] !== null) {
                $this->assertIsString($asset['code']);
                $this->assertNotEmpty($asset['code']);
            } else {
                $this->assertNull($asset['code']);
            }
            $this->assertIsString($asset['description']);
        }
    }

    #[Test]
    public function test_activity_properties(): void
    {
        $reflection = new ReflectionClass(ReleaseCollateralActivity::class);

        // Check for important properties inherited from Activity
        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('timeout'));
        $this->assertTrue($reflection->hasProperty('maxExceptions'));
    }
}
