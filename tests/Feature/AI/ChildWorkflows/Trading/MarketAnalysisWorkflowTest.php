<?php

declare(strict_types=1);

namespace Tests\Feature\AI\ChildWorkflows\Trading;

use App\Domain\AI\ChildWorkflows\Trading\MarketAnalysisWorkflow;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;
use Workflow\WorkflowStub;

class MarketAnalysisWorkflowTest extends TestCase
{
        #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_workflow_stub(): void
    {
        $this->assertTrue(class_exists(MarketAnalysisWorkflow::class));

        $workflow = WorkflowStub::make(MarketAnalysisWorkflow::class);
        $this->assertInstanceOf(WorkflowStub::class, $workflow);
    }

        #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_execute_method_with_correct_signature(): void
    {
        $reflection = new ReflectionClass(MarketAnalysisWorkflow::class);
        $method = $reflection->getMethod('execute');

        $this->assertTrue($method->isPublic());
        $this->assertEquals(3, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertEquals('Generator', $returnType->getName());
    }

        #[\PHPUnit\Framework\Attributes\Test]
    public function it_extends_workflow_base_class(): void
    {
        $reflection = new ReflectionClass(MarketAnalysisWorkflow::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass);
        $this->assertEquals('Workflow\Workflow', $parentClass->getName());
    }

        #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_correct_parameter_types(): void
    {
        $reflection = new ReflectionClass(MarketAnalysisWorkflow::class);
        $method = $reflection->getMethod('execute');
        $parameters = $method->getParameters();

        $this->assertEquals('conversationId', $parameters[0]->getName());
        $type0 = $parameters[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type0);
        $this->assertEquals('string', $type0->getName());

        $this->assertEquals('symbol', $parameters[1]->getName());
        $type1 = $parameters[1]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type1);
        $this->assertEquals('string', $type1->getName());

        $this->assertEquals('marketData', $parameters[2]->getName());
        $type2 = $parameters[2]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type2);
        $this->assertEquals('array', $type2->getName());
    }
}
