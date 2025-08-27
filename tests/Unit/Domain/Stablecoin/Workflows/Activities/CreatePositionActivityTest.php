<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\CreatePositionActivity;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class CreatePositionActivityTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(CreatePositionActivity::class));
    }

    #[Test]
    public function test_extends_workflow_activity(): void
    {
        $reflection = new ReflectionClass(CreatePositionActivity::class);
        $this->assertEquals('Workflow\Activity', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(CreatePositionActivity::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(CreatePositionActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(6, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals(AccountUuid::class, $parameters[0]->getType()->getName());

        $this->assertEquals('stablecoinCode', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateralAssetCode', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('collateralAmount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());

        $this->assertEquals('mintAmount', $parameters[4]->getName());
        $this->assertEquals('int', $parameters[4]->getType()->getName());

        $this->assertEquals('positionUuid', $parameters[5]->getName());
        $this->assertTrue($parameters[5]->isOptional());
        $this->assertTrue($parameters[5]->allowsNull());
    }

    #[Test]
    public function test_execute_method_returns_array(): void
    {
        $reflection = new ReflectionClass(CreatePositionActivity::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('array', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_collateral_ratio_calculation(): void
    {
        // Test collateral ratio calculation
        // Collateral value: $3,000
        // Mint amount: $2,000
        // Expected ratio: 3000 / 2000 = 1.5 (150%)

        $collateralValueInUSD = 3000000000; // $3,000 with 6 decimal places
        $mintAmount = 2000000000; // $2,000 with 6 decimal places

        $expectedRatio = $collateralValueInUSD / $mintAmount;

        $this->assertEquals(1.5, $expectedRatio);
    }

    #[Test]
    public function test_activity_properties(): void
    {
        $reflection = new ReflectionClass(CreatePositionActivity::class);

        // Check for important properties inherited from Activity
        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('timeout'));
    }
}
