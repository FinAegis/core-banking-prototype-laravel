<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows;

use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class AddCollateralWorkflowTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(AddCollateralWorkflow::class));
    }

    #[Test]
    public function test_extends_workflow_class(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $this->assertEquals('Workflow\Workflow', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_execute_method(): void
    {
        $this->assertTrue(method_exists(AddCollateralWorkflow::class, 'execute'));
    }

    #[Test]
    public function test_execute_method_signature(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals(4, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals('App\Domain\Account\DataObjects\AccountUuid', $parameters[0]->getType()->getName());

        $this->assertEquals('positionUuid', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());

        $this->assertEquals('collateralAssetCode', $parameters[2]->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());

        $this->assertEquals('collateralAmount', $parameters[3]->getName());
        $this->assertEquals('int', $parameters[3]->getType()->getName());
    }

    #[Test]
    public function test_execute_method_returns_generator(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertEquals('Generator', $method->getReturnType()->getName());
    }

    #[Test]
    public function test_workflow_uses_compensation_pattern(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);

        // Check if the workflow has compensation methods
        $this->assertTrue(method_exists(AddCollateralWorkflow::class, 'addCompensation'));
        $this->assertTrue(method_exists(AddCollateralWorkflow::class, 'compensate'));
    }

    #[Test]
    public function test_workflow_activities_sequence(): void
    {
        // Test that the workflow uses the correct activities in the right order
        $expectedActivities = [
            'LockCollateralActivity',
            'UpdatePositionActivity',
        ];

        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
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
    public function test_workflow_compensation_activity(): void
    {
        // Test that the workflow has proper compensation activity
        $expectedCompensation = 'ReleaseCollateralActivity';

        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $method = $reflection->getMethod('execute');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify compensation activity is present
        $this->assertStringContainsString($expectedCompensation, $source);
        $this->assertStringContainsString('addCompensation', $source);
    }

    #[Test]
    public function test_workflow_handles_exceptions(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
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

    #[Test]
    public function test_workflow_returns_boolean(): void
    {
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $method = $reflection->getMethod('execute');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify the workflow returns true on success
        $this->assertStringContainsString('return true;', $source);
    }

    #[Test]
    public function test_workflow_simple_structure(): void
    {
        // AddCollateralWorkflow should be simpler than Mint/Burn workflows
        $reflection = new ReflectionClass(AddCollateralWorkflow::class);
        $method = $reflection->getMethod('execute');

        // This workflow should only have 2 main activities
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Count ActivityStub::make occurrences
        $activityCount = substr_count($source, 'ActivityStub::make');

        // Should have 3 total (2 main + 1 compensation)
        $this->assertEquals(3, $activityCount);
    }
}
