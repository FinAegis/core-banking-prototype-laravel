<?php

declare(strict_types=1);

use App\Domain\Pricing\Exceptions\MoneyMismatchException;
use App\Domain\Pricing\ValueObjects\Money;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

// ─── Construction & validation ───────────────────────────────────────────

it('constructs a fiat Money via the factory', function (): void {
    $m = Money::fiat('499', 2, 'EUR');

    expect($m->amount)->toBe('499')
        ->and($m->decimals)->toBe(2)
        ->and($m->denomination)->toBe('EUR')
        ->and($m->isFiat)->toBeTrue();
});

it('constructs an asset Money via the factory', function (): void {
    $m = Money::asset('1000000', 6, 'USDC');

    expect($m->amount)->toBe('1000000')
        ->and($m->decimals)->toBe(6)
        ->and($m->denomination)->toBe('USDC')
        ->and($m->isFiat)->toBeFalse();
});

it('accepts a sign-prefix amount', function (): void {
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
        ->toThrow(InvalidArgumentException::class, '/^-?[0-9]+$/');
});

it('rejects a non-numeric amount', function (): void {
    expect(fn () => Money::fiat('abc', 2, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects empty amount', function (): void {
    expect(fn () => Money::fiat('', 2, 'EUR'))
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

it('rejects empty denomination', function (): void {
    expect(fn () => Money::fiat('1', 2, ''))
        ->toThrow(InvalidArgumentException::class, 'denomination must be 1..16');
});

it('rejects denomination longer than 16 chars', function (): void {
    expect(fn () => Money::fiat('1', 2, str_repeat('A', 17)))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts decimals at the boundary 18 (ETH)', function (): void {
    $m = Money::asset('1000000000000000000', 18, 'ETH');

    expect($m->decimals)->toBe(18);
});

// ─── Predicates ──────────────────────────────────────────────────────────

it('isZero / isNegative / isPositive return correctly', function (): void {
    expect(Money::fiat('0', 2, 'EUR')->isZero())->toBeTrue()
        ->and(Money::fiat('0', 2, 'EUR')->isPositive())->toBeFalse()
        ->and(Money::fiat('0', 2, 'EUR')->isNegative())->toBeFalse()
        ->and(Money::fiat('1', 2, 'EUR')->isPositive())->toBeTrue()
        ->and(Money::fiat('-1', 2, 'EUR')->isNegative())->toBeTrue();
});

// ─── Arithmetic ──────────────────────────────────────────────────────────

it('adds two same-shape Money values', function (): void {
    $sum = Money::fiat('499', 2, 'EUR')->add(Money::fiat('100', 2, 'EUR'));

    expect($sum->amount)->toBe('599')
        ->and($sum->denomination)->toBe('EUR');
});

it('add() throws on denomination mismatch', function (): void {
    Money::fiat('100', 2, 'EUR')->add(Money::fiat('100', 2, 'USD'));
})->throws(MoneyMismatchException::class, 'EUR');

it('add() throws on decimals mismatch', function (): void {
    Money::fiat('100', 2, 'EUR')->add(Money::fiat('100', 3, 'EUR'));
})->throws(MoneyMismatchException::class, '2 vs 3');

it('add() throws when one side is fiat and the other asset with same string', function (): void {
    // Defensive: this would only happen if a caller hand-constructed Money objects
    // with the same denomination string but different isFiat.
    $fiatTether = new Money(amount: '100', decimals: 6, denomination: 'USDT', isFiat: true);
    $assetTether = new Money(amount: '100', decimals: 6, denomination: 'USDT', isFiat: false);

    $fiatTether->add($assetTether);
})->throws(MoneyMismatchException::class);

it('subtracts two same-shape Money values', function (): void {
    $diff = Money::fiat('500', 2, 'EUR')->subtract(Money::fiat('100', 2, 'EUR'));

    expect($diff->amount)->toBe('400');
});

it('subtract goes negative cleanly', function (): void {
    $diff = Money::fiat('100', 2, 'EUR')->subtract(Money::fiat('500', 2, 'EUR'));

    expect($diff->amount)->toBe('-400')
        ->and($diff->isNegative())->toBeTrue();
});

it('subtract() throws on denomination mismatch', function (): void {
    Money::fiat('100', 2, 'EUR')->subtract(Money::fiat('100', 2, 'USD'));
})->throws(MoneyMismatchException::class);

it('negates positive to negative and back', function (): void {
    $m = Money::fiat('499', 2, 'EUR');

    expect($m->negate()->amount)->toBe('-499')
        ->and($m->negate()->negate()->amount)->toBe('499');
});

it('negates zero to zero (no -0)', function (): void {
    $zero = Money::fiat('0', 2, 'EUR');

    expect($zero->negate()->amount)->toBe('0');
});

it('compareTo: less, equal, greater', function (): void {
    $a = Money::fiat('100', 2, 'EUR');
    $b = Money::fiat('200', 2, 'EUR');
    $c = Money::fiat('100', 2, 'EUR');

    expect($a->compareTo($b))->toBe(-1)
        ->and($b->compareTo($a))->toBe(1)
        ->and($a->compareTo($c))->toBe(0);
});

it('compareTo throws on denomination mismatch', function (): void {
    Money::fiat('100', 2, 'EUR')->compareTo(Money::fiat('100', 2, 'USD'));
})->throws(MoneyMismatchException::class);

it('preserves sign-prefix through arithmetic', function (): void {
    $a = Money::fiat('-100', 2, 'EUR');
    $b = Money::fiat('-200', 2, 'EUR');

    expect($a->add($b)->amount)->toBe('-300');
});

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

it('never serializes both currency and asset', function (): void {
    $fiatKeys = array_keys(Money::fiat('1', 2, 'EUR')->jsonSerialize());
    $assetKeys = array_keys(Money::asset('1', 6, 'USDC')->jsonSerialize());

    expect($fiatKeys)->toContain('currency');
    expect($fiatKeys)->not()->toContain('asset');
    expect($assetKeys)->toContain('asset');
    expect($assetKeys)->not()->toContain('currency');
});

it('round-trips zero through json_encode/json_decode', function (): void {
    $zero = Money::fiat('0', 2, 'EUR');
    $encoded = json_encode($zero);

    expect($encoded)->toBeString();
    if ($encoded === false) {
        return;
    }
    $decoded = json_decode($encoded, true);

    expect($decoded)->toBe([
        'amount'   => '0',
        'decimals' => 2,
        'currency' => 'EUR',
    ]);
});

it('round-trips negative through json_encode/json_decode', function (): void {
    $refund = Money::fiat('-499', 2, 'EUR');
    $encoded = (string) json_encode($refund);
    $decoded = json_decode($encoded, true);

    expect($decoded)->toBe([
        'amount'   => '-499',
        'decimals' => 2,
        'currency' => 'EUR',
    ]);
});
