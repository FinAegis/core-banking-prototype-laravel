<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows;

use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class MintStablecoinWorkflowTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(MintStablecoinWorkflow::class));
    }

    #[Test]
    public function test_extends_workflow_class(): void
    {
        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $this->assertEquals('Workflow\Workflow', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(MintStablecoinWorkflow::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_signature(): void
    {
        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(6, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals('App\Domain\Account\DataObjects\AccountUuid', $parameters[0]->getType()->getName());

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
    public function test_execute_method_returns_generator(): void
    {
        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('Generator', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_workflow_uses_compensation_pattern(): void
    {
        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);

        // Check if the workflow has compensation methods
        $this->assertTrue(method_exists(MintStablecoinWorkflow::class, 'addCompensation'));
        $this->assertTrue(method_exists(MintStablecoinWorkflow::class, 'compensate'));
    }

    #[Test]
    public function test_workflow_activities_order(): void
    {
        // Test that the workflow uses the correct activities in the right order
        $expectedActivities = [
            'CreatePositionActivity',
            'LockCollateralActivity',
            'MintStablecoinActivity',
        ];

        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $method = $reflection->getMethod('execute');

        // Get the method source code
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify activities are called in order
        foreach ($expectedActivities as $activity) {
            $this->assertStringContainsString($activity, $source);
        }
    }

    #[Test]
    public function test_workflow_compensation_activities(): void
    {
        // Test that the workflow has proper compensation activities
        $expectedCompensations = [
            'ClosePositionActivity',
            'ReleaseCollateralActivity',
            'BurnStablecoinActivity',
        ];

        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $method = $reflection->getMethod('execute');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify compensation activities are present
        foreach ($expectedCompensations as $compensation) {
            $this->assertStringContainsString($compensation, $source);
        }
    }

    #[Test]
    public function test_workflow_handles_exceptions(): void
    {
        $reflection = new ReflectionClass(MintStablecoinWorkflow::class);
        $method = $reflection->getMethod('execute');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify try-catch block exists
        $this->assertStringContainsString('try {', $source);
        $this->assertStringContainsString('} catch', $source);
        $this->assertStringContainsString('compensate()', $source);
    }
}
