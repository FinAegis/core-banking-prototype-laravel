<?php

use App\Domain\Payment\Activities\ValidateWithdrawalActivity;
use App\Domain\Payment\DataObjects\BankWithdrawal;

it('class exists', function () {
    expect(class_exists(ValidateWithdrawalActivity::class))->toBeTrue();
});

it('extends Activity base class', function () {
    $reflection = new ReflectionClass(ValidateWithdrawalActivity::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Activity');
});

it('has execute method', function () {
    expect(method_exists(ValidateWithdrawalActivity::class, 'execute'))->toBeTrue();
});

it('execute method has correct signature', function () {
    $reflection = new ReflectionClass(ValidateWithdrawalActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getNumberOfParameters())->toBe(1);
    
    $parameters = $method->getParameters();
    expect($parameters[0]->getName())->toBe('withdrawal');
    expect($parameters[0]->getType()->getName())->toBe(BankWithdrawal::class);
});

it('execute method returns array', function () {
    $reflection = new ReflectionClass(ValidateWithdrawalActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getReturnType()->getName())->toBe('array');
});