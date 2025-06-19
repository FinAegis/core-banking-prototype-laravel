<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Workflows\UnfreezeAccountWorkflow;
use Workflow\WorkflowStub;

it('can create workflow stub for unfreeze', function () {
    expect(class_exists(UnfreezeAccountWorkflow::class))->toBeTrue();
    
    $workflow = WorkflowStub::make(UnfreezeAccountWorkflow::class);
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

it('has execute method with correct signature', function () {
    $reflection = new ReflectionClass(UnfreezeAccountWorkflow::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(3);
    expect($method->getReturnType()->getName())->toBe('Generator');
});

it('extends workflow base class', function () {
    $reflection = new ReflectionClass(UnfreezeAccountWorkflow::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Workflow');
});

it('has correct parameter types', function () {
    $reflection = new ReflectionClass(UnfreezeAccountWorkflow::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();
    
    expect($parameters[0]->getName())->toBe('uuid');
    expect($parameters[1]->getName())->toBe('reason');
    expect($parameters[2]->getName())->toBe('authorizedBy');
    
    // Check parameter types
    expect($parameters[0]->getType()->getName())->toBe('App\Domain\Account\DataObjects\AccountUuid');
    expect($parameters[1]->getType()->getName())->toBe('string');
    expect($parameters[2]->getType()?->getName())->toBe('string');
    expect($parameters[2]->allowsNull())->toBeTrue();
});