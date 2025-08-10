<?php

declare(strict_types=1);

namespace Tests\Feature\AI\ChildWorkflows\Risk;

use App\Domain\AI\ChildWorkflows\Risk\CreditRiskWorkflow;
use Tests\TestCase;
use Workflow\WorkflowStub;

class CreditRiskWorkflowTest extends TestCase
{
    /** @test */
    public function it_can_create_workflow_stub(): void
    {
        $this->assertTrue(class_exists(CreditRiskWorkflow::class));

        $workflow = WorkflowStub::make(CreditRiskWorkflow::class);
        $this->assertInstanceOf(WorkflowStub::class, $workflow);
    }

    /** @test */
    public function it_has_execute_method_with_correct_signature(): void
    {
        $reflection = new \ReflectionClass(CreditRiskWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertTrue($method->isPublic());
        $this->assertEquals(4, $method->getNumberOfParameters());
        $this->assertEquals('Generator', $method->getReturnType()->getName());
    }

    /** @test */
    public function it_extends_workflow_base_class(): void
    {
        $reflection = new \ReflectionClass(CreditRiskWorkflow::class);
        $this->assertEquals('Workflow\Workflow', $reflection->getParentClass()->getName());
    }

    /** @test */
    public function it_has_correct_parameter_types(): void
    {
        $reflection = new \ReflectionClass(CreditRiskWorkflow::class);
        $method = $reflection->getMethod('execute');
        $parameters = $method->getParameters();

        $this->assertEquals('conversationId', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        
        $this->assertEquals('user', $parameters[1]->getName());
        $this->assertEquals('App\Models\User', $parameters[1]->getType()->getName());
        
        $this->assertEquals('financialData', $parameters[2]->getName());
        $this->assertEquals('array', $parameters[2]->getType()->getName());
        
        $this->assertEquals('parameters', $parameters[3]->getName());
        $this->assertEquals('array', $parameters[3]->getType()->getName());
    }
}