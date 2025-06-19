<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Custodian\Workflows\CustodianTransferWorkflow;
use Workflow\WorkflowStub;

it('can create workflow stub for custodian transfer', function () {
    expect(class_exists(CustodianTransferWorkflow::class))->toBeTrue();
    
    $workflow = WorkflowStub::make(CustodianTransferWorkflow::class);
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

it('has execute method with correct signature', function () {
    $reflection = new ReflectionClass(CustodianTransferWorkflow::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(7);
    expect($method->getReturnType()->getName())->toBe('Generator');
});

it('extends workflow base class', function () {
    $reflection = new ReflectionClass(CustodianTransferWorkflow::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Workflow');
});

it('has correct parameter types', function () {
    $reflection = new ReflectionClass(CustodianTransferWorkflow::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();
    
    expect($parameters[0]->getName())->toBe('internalAccount');
    expect($parameters[1]->getName())->toBe('custodianAccount');
    expect($parameters[2]->getName())->toBe('assetCode');
    expect($parameters[3]->getName())->toBe('amount');
    expect($parameters[4]->getName())->toBe('custodianName');
    expect($parameters[5]->getName())->toBe('direction');
    expect($parameters[6]->getName())->toBe('reference');
    
    // Check parameter types
    expect($parameters[0]->getType()->getName())->toBe('App\Domain\Account\DataObjects\AccountUuid');
    expect($parameters[1]->getType()->getName())->toBe('string');
    expect($parameters[2]->getType()->getName())->toBe('string');
    expect($parameters[3]->getType()->getName())->toBe('App\Domain\Account\DataObjects\Money');
    expect($parameters[4]->getType()->getName())->toBe('string');
    expect($parameters[5]->getType()->getName())->toBe('string');
    expect($parameters[6]->getType()?->getName())->toBe('string');
    expect($parameters[6]->allowsNull())->toBeTrue();
});

it('has default parameter values', function () {
    $reflection = new ReflectionClass(CustodianTransferWorkflow::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();
    
    expect($parameters[5]->isDefaultValueAvailable())->toBeTrue();
    expect($parameters[5]->getDefaultValue())->toBe('deposit');
    
    expect($parameters[6]->isDefaultValueAvailable())->toBeTrue();
    expect($parameters[6]->getDefaultValue())->toBeNull();
});