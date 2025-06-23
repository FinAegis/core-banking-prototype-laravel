<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use Workflow\WorkflowStub;

it('can create workflow stub for asset deposit', function () {
    expect(class_exists(AssetDepositWorkflow::class))->toBeTrue();
    
    $workflow = WorkflowStub::make(AssetDepositWorkflow::class);
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

it('has execute method with correct signature', function () {
    $reflection = new ReflectionClass(AssetDepositWorkflow::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(4);
    expect($method->getReturnType()->getName())->toBe('Generator');
});

it('extends workflow base class', function () {
    $reflection = new ReflectionClass(AssetDepositWorkflow::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Workflow');
});

it('has correct parameter types', function () {
    $reflection = new ReflectionClass(AssetDepositWorkflow::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();
    
    expect($parameters[0]->getName())->toBe('accountUuid');
    expect($parameters[1]->getName())->toBe('assetCode');
    expect($parameters[2]->getName())->toBe('amount');
    expect($parameters[3]->getName())->toBe('description');
    
    // Check parameter types
    expect($parameters[0]->getType()->getName())->toBe('App\Domain\Account\DataObjects\AccountUuid');
    expect($parameters[1]->getType()->getName())->toBe('string');
    expect($parameters[2]->getType()->getName())->toBe('int');
    expect($parameters[3]->getType()?->getName())->toBe('string');
    expect($parameters[3]->allowsNull())->toBeTrue();
});