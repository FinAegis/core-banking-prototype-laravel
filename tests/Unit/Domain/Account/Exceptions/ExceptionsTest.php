<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Domain\Account\Exceptions\InvalidHashException;
use App\Domain\Account\Exceptions\NotEnoughFunds;

it('can create invalid hash exception', function () {
    $message = 'Invalid hash provided';
    $exception = new InvalidHashException($message);
    
    expect($exception->getMessage())->toBe($message);
    expect($exception)->toBeInstanceOf(Exception::class);
});

it('can create not enough funds exception', function () {
    $message = 'Insufficient balance for transaction';
    $exception = new NotEnoughFunds($message);
    
    expect($exception->getMessage())->toBe($message);
    expect($exception)->toBeInstanceOf(Exception::class);
});

it('exceptions have default messages when none provided', function () {
    $hashException = new InvalidHashException();
    $fundsException = new NotEnoughFunds();
    
    expect($hashException->getMessage())->toBeString();
    expect($fundsException->getMessage())->toBeString();
});

it('exceptions have proper inheritance', function () {
    $hashException = new InvalidHashException();
    $fundsException = new NotEnoughFunds();
    
    expect($hashException)->toBeInstanceOf(Throwable::class);
    expect($fundsException)->toBeInstanceOf(Throwable::class);
});

it('can throw and catch invalid hash exception', function () {
    expect(fn() => throw new InvalidHashException('Test hash error'))
        ->toThrow(InvalidHashException::class, 'Test hash error');
});

it('can throw and catch not enough funds exception', function () {
    expect(fn() => throw new NotEnoughFunds('Test funds error'))
        ->toThrow(NotEnoughFunds::class, 'Test funds error');
});