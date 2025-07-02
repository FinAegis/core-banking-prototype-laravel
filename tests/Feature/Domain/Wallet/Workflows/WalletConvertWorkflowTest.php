<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Wallet\Workflows;

use App\Domain\Wallet\Workflows\WalletConvertWorkflow;
use Tests\TestCase;

class WalletConvertWorkflowTest extends TestCase
{
    public function test_workflow_extends_base_workflow()
    {
        $reflection = new \ReflectionClass(WalletConvertWorkflow::class);
        $this->assertTrue($reflection->isSubclassOf(\Workflow\Workflow::class));
    }
    
    public function test_execute_method_has_correct_signature()
    {
        $reflection = new \ReflectionClass(WalletConvertWorkflow::class);
        $executeMethod = $reflection->getMethod('execute');
        
        $this->assertTrue($executeMethod->isPublic());
        $this->assertEquals('execute', $executeMethod->getName());
        
        $parameters = $executeMethod->getParameters();
        $this->assertCount(4, $parameters);
        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals('fromAssetCode', $parameters[1]->getName());
        $this->assertEquals('toAssetCode', $parameters[2]->getName());
        $this->assertEquals('amount', $parameters[3]->getName());
    }
    
    public function test_workflow_has_compensation_pattern()
    {
        $reflection = new \ReflectionClass(WalletConvertWorkflow::class);
        $executeMethod = $reflection->getMethod('execute');
        
        // Get the method source to check for try-catch pattern
        $filename = $reflection->getFileName();
        $startLine = $executeMethod->getStartLine();
        $endLine = $executeMethod->getEndLine();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
        
        // Check that compensation patterns are present
        $this->assertStringContainsString('try {', $source);
        $this->assertStringContainsString('addCompensation', $source);
        $this->assertStringContainsString('compensate()', $source);
        $this->assertStringContainsString('catch (\Throwable', $source);
    }
}