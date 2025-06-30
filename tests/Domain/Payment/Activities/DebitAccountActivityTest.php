<?php

use App\Domain\Payment\Activities\DebitAccountActivity;

it('class exists', function () {
    expect(class_exists(DebitAccountActivity::class))->toBeTrue();
});

it('extends Activity base class', function () {
    $reflection = new ReflectionClass(DebitAccountActivity::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Activity');
});

it('has execute method', function () {
    expect(method_exists(DebitAccountActivity::class, 'execute'))->toBeTrue();
});

it('execute method has correct signature', function () {
    $reflection = new ReflectionClass(DebitAccountActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getNumberOfParameters())->toBe(3);
    
    $parameters = $method->getParameters();
    expect($parameters[0]->getName())->toBe('accountUuid');
    expect($parameters[0]->getType()->getName())->toBe('string');
    
    expect($parameters[1]->getName())->toBe('amount');
    expect($parameters[1]->getType()->getName())->toBe('int');
    
    expect($parameters[2]->getName())->toBe('currency');
    expect($parameters[2]->getType()->getName())->toBe('string');
});

it('execute method returns void', function () {
    $reflection = new ReflectionClass(DebitAccountActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getReturnType()->getName())->toBe('void');
});