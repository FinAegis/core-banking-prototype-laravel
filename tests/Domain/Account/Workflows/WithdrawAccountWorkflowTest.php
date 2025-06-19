<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use Workflow\WorkflowStub;

it('can create workflow stub for withdraw', function () {
    expect(class_exists(WithdrawAccountWorkflow::class))->toBeTrue();
    
    $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

it('has execute method with correct signature', function () {
    $reflection = new ReflectionClass(WithdrawAccountWorkflow::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(2);
    expect($method->getReturnType()->getName())->toBe('Generator');
});

it('extends workflow base class', function () {
    $reflection = new ReflectionClass(WithdrawAccountWorkflow::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Workflow');
});

it('has correct parameter types', function () {
    $reflection = new ReflectionClass(WithdrawAccountWorkflow::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();
    
    expect($parameters[0]->getName())->toBe('uuid');
    expect($parameters[1]->getName())->toBe('money');
    
    // Check parameter types
    expect($parameters[0]->getType()->getName())->toBe('App\Domain\Account\DataObjects\AccountUuid');
    expect($parameters[1]->getType()->getName())->toBe('App\Domain\Account\DataObjects\Money');
});