<?php

declare(strict_types=1);

use App\Domain\Pricing\Exceptions\MoneyMismatchException;
use App\Domain\Pricing\ValueObjects\Money;
use Tests\TestCase;

uses(TestCase::class);

// ─── Construction & validation ────────────────────────────────────────────

it('constructs a fiat Money via the fiat() factory', function (): void {
    $m = Money::fiat('499', 2, 'EUR');

    expect($m->amount)->toBe('499')
        ->and($m->decimals)->toBe(2)
        ->and($m->denomination)->toBe('EUR')
        ->and($m->isFiat)->toBeTrue();
});

it('constructs an asset Money via the asset() factory', function (): void {
    $m = Money::asset('1000000', 6, 'USDC');

    expect($m->amount)->toBe('1000000')
        ->and($m->decimals)->toBe(6)
        ->and($m->denomination)->toBe('USDC')
        ->and($m->isFiat)->toBeFalse();
});

it('accepts a sign-prefix negative amount', function (): void {
    $m = Money::fiat('-499', 2, 'EUR');

    expect($m->amount)->toBe('-499')
        ->and($m->isNegative())->toBeTrue();
});

it('accepts an explicit zero', function (): void {
    $m = Money::fiat('0', 2, 'EUR');

    expect($m->isZero())->toBeTrue();
});

it('rejects a decimal-point amount', function (): void {
    expect(fn () => Money::fiat('4.99', 2, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a non-numeric amount', function (): void {
    expect(fn () => Money::fiat('abc', 2, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects decimals above 18', function (): void {
    expect(fn () => Money::fiat('1', 19, 'EUR'))
        ->toThrow(InvalidArgumentException::class, 'decimals must be 0..18');
});

it('rejects decimals below 0', function (): void {
    expect(fn () => Money::fiat('1', -1, 'EUR'))
        ->toThrow(InvalidArgumentException::class, 'decimals must be 0..18');
});

// ─── Arithmetic ───────────────────────────────────────────────────────────

it('adds two same-shape Money values via bcmath', function (): void {
    $sum = Money::fiat('250', 2, 'EUR')->add(Money::fiat('249', 2, 'EUR'));

    expect($sum->amount)->toBe('499');
});

it('add() throws on denomination mismatch', function (): void {
    Money::fiat('100', 2, 'EUR')->add(Money::fiat('100', 2, 'USD'));
})->throws(MoneyMismatchException::class);

it('supports negative amounts via sign-prefix', function (): void {
    $refund = Money::fiat('-499', 2, 'EUR');

    expect($refund->isNegative())->toBeTrue()
        ->and($refund->amount)->toBe('-499');
});

it('throws when adding mismatched denominations (fiat vs asset)', function (): void {
    Money::fiat('100', 2, 'EUR')->add(Money::asset('100', 6, 'USDC'));
})->throws(MoneyMismatchException::class);

// ─── JSON serialization (ADR-0004 shape) ─────────────────────────────────

it('serializes fiat as {amount, decimals, currency}', function (): void {
    $shape = Money::fiat('499', 2, 'EUR')->jsonSerialize();

    expect($shape)->toBe([
        'amount'   => '499',
        'decimals' => 2,
        'currency' => 'EUR',
    ]);
});

it('serializes asset as {amount, decimals, asset}', function (): void {
    $shape = Money::asset('1000000', 6, 'USDC')->jsonSerialize();

    expect($shape)->toBe([
        'amount'   => '1000000',
        'decimals' => 6,
        'asset'    => 'USDC',
    ]);
});
