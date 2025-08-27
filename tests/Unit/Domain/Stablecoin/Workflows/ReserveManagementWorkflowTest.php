<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows;

use App\Domain\Stablecoin\Workflows\ReserveManagementWorkflow;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ReserveManagementWorkflowTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(ReserveManagementWorkflow::class));
    }

    #[Test]
    public function test_extends_workflow_class(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);
        $this->assertEquals('Workflow\Workflow', $reflection->getParentClass()->getName());
    }

    #[Test]
    public function test_has_workflow_methods(): void
    {
        $this->assertTrue(method_exists(ReserveManagementWorkflow::class, 'depositReserve'));
        $this->assertTrue(method_exists(ReserveManagementWorkflow::class, 'withdrawReserve'));
        $this->assertTrue(method_exists(ReserveManagementWorkflow::class, 'rebalanceReserves'));
    }

    #[Test]
    public function test_deposit_reserve_method_signature(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);
        $method = $reflection->getMethod('depositReserve');

        $this->assertEquals(1, $method->getNumberOfParameters());

        $parameters = $method->getParameters();

        $this->assertEquals('data', $parameters[0]->getName());
        $this->assertEquals('App\Domain\Stablecoin\Workflows\Data\ReserveDepositData', $parameters[0]->getType()->getName());
    }

    #[Test]
    public function test_workflow_methods_return_generator(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        $methods = ['depositReserve', 'withdrawReserve', 'rebalanceReserves'];
        foreach ($methods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $this->assertEquals('Generator', $method->getReturnType()->getName());
        }
    }

    #[Test]
    public function test_workflow_uses_compensation_pattern(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Check if the workflow has compensation methods
        $this->assertTrue(method_exists(ReserveManagementWorkflow::class, 'addCompensation'));
        $this->assertTrue(method_exists(ReserveManagementWorkflow::class, 'compensate'));
    }

    #[Test]
    public function test_workflow_has_separate_methods(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Verify separate methods exist for each operation
        $this->assertTrue($reflection->hasMethod('depositReserve'));
        $this->assertTrue($reflection->hasMethod('withdrawReserve'));
        $this->assertTrue($reflection->hasMethod('rebalanceReserves'));

        // Verify compensation methods exist
        $this->assertTrue($reflection->hasMethod('compensateDeposit'));
        $this->assertTrue($reflection->hasMethod('compensateWithdrawal'));
        $this->assertTrue($reflection->hasMethod('compensateRebalance'));
    }

    #[Test]
    public function test_workflow_uses_data_objects(): void
    {
        // Test that the workflow expects specific data objects
        $expectedDataTypes = [
            'ReserveDepositData',
            'ReserveWithdrawalData',
            'ReserveRebalanceData',
        ];

        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Check imports
        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        foreach ($expectedDataTypes as $dataType) {
            $this->assertStringContainsString("use App\\Domain\\Stablecoin\\Workflows\\Data\\$dataType;", $fileContent);
        }
    }

    #[Test]
    public function test_workflow_handles_exceptions(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Check exception handling in depositReserve method
        $method = $reflection->getMethod('depositReserve');
        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify try-catch block exists
        $this->assertStringContainsString('try {', $source);
        $this->assertStringContainsString('} catch', $source);
        $this->assertStringContainsString('compensateDeposit', $source);
    }

    #[Test]
    public function test_workflow_uses_activity_stubs(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Check that workflow uses activity stubs
        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Verify activity stub usage
        $this->assertStringContainsString('ActivityStub', $fileContent);
        $this->assertStringContainsString('Workflow::newActivityStub', $fileContent);
        $this->assertStringContainsString('ReserveManagementActivity', $fileContent);
    }

    #[Test]
    public function test_workflow_returns_array(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);
        $method = $reflection->getMethod('depositReserve');

        $fileName = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify the workflow returns array with success info
        $this->assertStringContainsString('return [', $source);
        $this->assertStringContainsString("'success'", $source);
    }

    #[Test]
    public function test_workflow_activity_constructor(): void
    {
        $reflection = new ReflectionClass(ReserveManagementWorkflow::class);

        // Check constructor exists and initializes activity
        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $fileName = $reflection->getFileName();
        $startLine = $constructor->getStartLine();
        $endLine = $constructor->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Verify activity initialization
        $this->assertStringContainsString('Workflow::newActivityStub', $source);
        $this->assertStringContainsString('ReserveManagementActivity::class', $source);
    }
}
