<?php

declare(strict_types=1);

use App\Domain\Pricing\ValueObjects\Money;

it('constructs a EUR money from cents string', function () {
    $money = Money::eur('499');

    expect($money->amount)->toBe('499')
        ->and($money->decimals)->toBe(2)
        ->and($money->currency)->toBe('EUR')
        ->and($money->asset)->toBeNull();
});

it('serialises to the wire shape per ADR-0004', function () {
    $eur = Money::eur('499');

    expect($eur->toArray())->toBe([
        'amount'   => '499',
        'decimals' => 2,
        'currency' => 'EUR',
    ]);

    $usdc = Money::asset('1000000', 'USDC', 6);
    expect($usdc->toArray())->toBe([
        'amount'   => '1000000',
        'decimals' => 6,
        'asset'    => 'USDC',
    ]);
});

it('rejects decimal-point amounts', function () {
    Money::eur('4.99');
})->throws(InvalidArgumentException::class);

it('rejects when neither currency nor asset is given', function () {
    new Money(amount: '0', decimals: 2);
})->throws(InvalidArgumentException::class);

it('rejects when both currency and asset are given', function () {
    new Money(amount: '0', decimals: 2, currency: 'EUR', asset: 'USDC');
})->throws(InvalidArgumentException::class);

it('adds two compatible Money values via bcmath', function () {
    $sum = Money::eur('250')->add(Money::eur('249'));
    expect($sum->amount)->toBe('499');
});

it('supports negative amounts via sign-prefix', function () {
    $refund = Money::eur('-499');
    expect($refund->isNegative())->toBeTrue();
    expect($refund->toArray()['amount'])->toBe('-499');
});

it('throws when adding mismatched denominations', function () {
    Money::eur('100')->add(Money::asset('100', 'USDC', 6));
})->throws(InvalidArgumentException::class);
