<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Governance\Activities;

use App\Domain\Governance\Activities\GetPollActivity;
use Tests\TestCase;

class GetPollActivityTest extends TestCase
{
    public function test_activity_extends_workflow_activity()
    {
        $reflection = new \ReflectionClass(GetPollActivity::class);
        $this->assertTrue($reflection->isSubclassOf(\Workflow\Activity::class));
    }
    
    public function test_execute_method_has_correct_signature()
    {
        $reflection = new \ReflectionClass(GetPollActivity::class);
        $executeMethod = $reflection->getMethod('execute');
        
        $this->assertTrue($executeMethod->isPublic());
        $this->assertEquals('execute', $executeMethod->getName());
        
        $parameters = $executeMethod->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('pollUuid', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());
    }
    
    public function test_execute_method_returns_nullable_poll()
    {
        $reflection = new \ReflectionClass(GetPollActivity::class);
        $executeMethod = $reflection->getMethod('execute');
        
        $returnType = $executeMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
    }
}