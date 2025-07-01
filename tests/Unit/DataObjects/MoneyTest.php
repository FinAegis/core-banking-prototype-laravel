<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Domain\Account\DataObjects\Money;

it('can create money with integer amount', function () {
    $money = new Money(1000);
    
    expect($money->getAmount())->toBe(1000);
});

it('can create money with zero amount', function () {
    $money = new Money(0);
    
    expect($money->getAmount())->toBe(0);
});

it('can handle negative amounts', function () {
    $money = new Money(-500);
    
    expect($money->getAmount())->toBe(-500);
});

it('can invert money amount', function () {
    $money = new Money(1000);
    $inverted = $money->invert();
    
    expect($inverted->getAmount())->toBe(-1000);
});

it('can invert negative money amount', function () {
    $money = new Money(-750);
    $inverted = $money->invert();
    
    expect($inverted->getAmount())->toBe(750);
});

it('can convert to array', function () {
    $money = new Money(2500);
    $array = $money->toArray();
    
    expect($array)->toHaveKey('amount');
    expect($array['amount'])->toBe(2500);
});

it('handles large amounts correctly', function () {
    $money = new Money(999999999);
    
    expect($money->getAmount())->toBe(999999999);
});

it('invert operation creates new instance', function () {
    $money = new Money(500);
    $inverted = $money->invert();
    
    expect($money->getAmount())->toBe(500);
    expect($inverted->getAmount())->toBe(-500);
    expect($money)->not->toBe($inverted);
});