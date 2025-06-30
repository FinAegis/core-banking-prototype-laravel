<?php

use App\Domain\Payment\Activities\InitiateBankTransferActivity;
use App\Domain\Payment\DataObjects\BankWithdrawal;

it('class exists', function () {
    expect(class_exists(InitiateBankTransferActivity::class))->toBeTrue();
});

it('extends Activity base class', function () {
    $reflection = new ReflectionClass(InitiateBankTransferActivity::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Activity');
});

it('has execute method', function () {
    expect(method_exists(InitiateBankTransferActivity::class, 'execute'))->toBeTrue();
});

it('execute method has correct signature', function () {
    $reflection = new ReflectionClass(InitiateBankTransferActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getNumberOfParameters())->toBe(2);
    
    $parameters = $method->getParameters();
    expect($parameters[0]->getName())->toBe('transactionId');
    expect($parameters[0]->getType()->getName())->toBe('string');
    
    expect($parameters[1]->getName())->toBe('withdrawal');
    expect($parameters[1]->getType()->getName())->toBe(BankWithdrawal::class);
});

it('execute method returns string', function () {
    $reflection = new ReflectionClass(InitiateBankTransferActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getReturnType()->getName())->toBe('string');
});