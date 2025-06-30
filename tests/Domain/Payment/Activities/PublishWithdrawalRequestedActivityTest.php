<?php

use App\Domain\Payment\Activities\PublishWithdrawalRequestedActivity;
use App\Domain\Payment\DataObjects\BankWithdrawal;

it('class exists', function () {
    expect(class_exists(PublishWithdrawalRequestedActivity::class))->toBeTrue();
});

it('extends Activity base class', function () {
    $reflection = new ReflectionClass(PublishWithdrawalRequestedActivity::class);
    expect($reflection->getParentClass()->getName())->toBe('Workflow\Activity');
});

it('has execute method', function () {
    expect(method_exists(PublishWithdrawalRequestedActivity::class, 'execute'))->toBeTrue();
});

it('execute method has correct signature', function () {
    $reflection = new ReflectionClass(PublishWithdrawalRequestedActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getNumberOfParameters())->toBe(3);
    
    $parameters = $method->getParameters();
    expect($parameters[0]->getName())->toBe('transactionId');
    expect($parameters[0]->getType()->getName())->toBe('string');
    
    expect($parameters[1]->getName())->toBe('transferId');
    expect($parameters[1]->getType()->getName())->toBe('string');
    
    expect($parameters[2]->getName())->toBe('withdrawal');
    expect($parameters[2]->getType()->getName())->toBe(BankWithdrawal::class);
});

it('execute method returns void', function () {
    $reflection = new ReflectionClass(PublishWithdrawalRequestedActivity::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->getReturnType()->getName())->toBe('void');
});