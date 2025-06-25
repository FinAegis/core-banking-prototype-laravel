<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Basket\Activities;

use App\Domain\Basket\Activities\ComposeBasketActivity;
use App\Domain\Basket\Activities\ComposeBasketBusinessActivity;
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
    
    public function test_execute_method_validates_required_parameters()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters: account_uuid, basket_code, amount');
        
        $activity->execute([]);
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
        $this->assertCount(1, $parameters);
        $this->assertEquals('input', $parameters[0]->getName());
        $this->assertEquals('array', $parameters[0]->getType()->getName());
    }
    
    public function test_execute_method_validates_missing_account_uuid()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $this->expectException(\InvalidArgumentException::class);
        
        $activity->execute([
            'basket_code' => 'GCU',
            'amount' => 1000
        ]);
    }
    
    public function test_execute_method_validates_missing_basket_code()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $this->expectException(\InvalidArgumentException::class);
        
        $activity->execute([
            'account_uuid' => 'test-uuid',
            'amount' => 1000
        ]);
    }
    
    public function test_execute_method_validates_missing_amount()
    {
        $basketService = Mockery::mock(ComposeBasketBusinessActivity::class);
        $activity = new ComposeBasketActivity($basketService);
        
        $this->expectException(\InvalidArgumentException::class);
        
        $activity->execute([
            'account_uuid' => 'test-uuid',
            'basket_code' => 'GCU'
        ]);
    }
}