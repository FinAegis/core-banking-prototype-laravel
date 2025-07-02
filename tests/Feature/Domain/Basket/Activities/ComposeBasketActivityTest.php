<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Basket\Activities;

use App\Domain\Basket\Activities\ComposeBasketActivity;
use App\Domain\Basket\Activities\ComposeBasketBusinessActivity;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Models\Account;
use Tests\TestCase;
use Mockery;

class ComposeBasketActivityTest extends TestCase
{
    public function test_activity_extends_workflow_activity()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $this->assertInstanceOf(\Workflow\Activity::class, $activity);
    }
    
    public function test_execute_method_calls_business_activity()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $accountUuid = new AccountUuid('test-uuid');
        $basketCode = 'GCU';
        $amount = 1000;
        $expectedResult = ['success' => true];
        
        $basketService->shouldReceive('execute')
            ->once()
            ->with($accountUuid, $basketCode, $amount)
            ->andReturn($expectedResult);
        
        $result = $activity->execute($accountUuid, $basketCode, $amount);
        
        $this->assertEquals($expectedResult, $result);
    }
    
    public function test_execute_method_has_correct_signature()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $reflection = new \ReflectionClass($activity);
        $executeMethod = $reflection->getMethod('execute');
        
        $this->assertTrue($executeMethod->isPublic());
        $this->assertEquals('execute', $executeMethod->getName());
        
        $parameters = $executeMethod->getParameters();
        $this->assertCount(3, $parameters);
        
        $this->assertEquals('accountUuid', $parameters[0]->getName());
        $this->assertEquals('App\Domain\Account\DataObjects\AccountUuid', $parameters[0]->getType()->getName());
        
        $this->assertEquals('basketCode', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());
        
        $this->assertEquals('amount', $parameters[2]->getName());
        $this->assertEquals('int', $parameters[2]->getType()->getName());
    }
    
    public function test_execute_method_returns_business_activity_result()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $accountUuid = new AccountUuid('test-uuid');
        $basketCode = 'GCU';
        $amount = 5000;
        $expectedResult = [
            'transaction_id' => 'txn-123',
            'basket_units' => 50,
            'fee' => 25
        ];
        
        $basketService->shouldReceive('execute')
            ->once()
            ->with($accountUuid, $basketCode, $amount)
            ->andReturn($expectedResult);
        
        $result = $activity->execute($accountUuid, $basketCode, $amount);
        
        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('basket_units', $result);
        $this->assertArrayHasKey('fee', $result);
    }
}