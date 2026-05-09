<?php

declare(strict_types=1);

use App\Domain\Pricing\Validation\MoneyFormRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

/**
 * Run the rule and capture the failure message (or null on success).
 *
 * The closure signature mirrors Laravel's ValidationRule contract:
 * `Closure(string, ?string=): PotentiallyTranslatedString`. We capture
 * the message and return a stub PotentiallyTranslatedString so the
 * signature lines up.
 *
 * @param  mixed  $value
 */
function runMoneyRule(mixed $value, bool $allowNegative = true): ?string
{
    $captured = null;
    (new MoneyFormRule(allowNegative: $allowNegative))->validate(
        'fee',
        $value,
        function (string $msg, ?string $attribute = null) use (&$captured): PotentiallyTranslatedString {
            $captured = $msg;

            return new PotentiallyTranslatedString($msg, app('translator'));
        },
    );

    return $captured;
}

// ─── Accept cases ────────────────────────────────────────────────────────

it('accepts a valid fiat triple', function (): void {
    expect(runMoneyRule(['amount' => '499', 'decimals' => 2, 'currency' => 'EUR']))
        ->toBeNull();
});

it('accepts a valid asset triple', function (): void {
    expect(runMoneyRule(['amount' => '1000000', 'decimals' => 6, 'asset' => 'USDC']))
        ->toBeNull();
});

it('accepts an explicit zero', function (): void {
    expect(runMoneyRule(['amount' => '0', 'decimals' => 2, 'currency' => 'EUR']))
        ->toBeNull();
});

it('accepts a negative amount when allowNegative=true', function (): void {
    expect(runMoneyRule(['amount' => '-499', 'decimals' => 2, 'currency' => 'EUR']))
        ->toBeNull();
});

it('rejects a negative amount when allowNegative=false', function (): void {
    $msg = runMoneyRule(['amount' => '-499', 'decimals' => 2, 'currency' => 'EUR'], allowNegative: false);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002', 'non-negative');
});

it('accepts decimals at the boundary 18 (ETH)', function (): void {
    expect(runMoneyRule(['amount' => '1', 'decimals' => 18, 'asset' => 'ETH']))
        ->toBeNull();
});

// ─── Reject cases per ADR-0004 ───────────────────────────────────────────

it('rejects a decimal-point amount with ERR_VALIDATION_002', function (): void {
    $msg = runMoneyRule(['amount' => '4.99', 'decimals' => 2, 'currency' => 'EUR']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects a number-literal amount with ERR_VALIDATION_002', function (): void {
    $msg = runMoneyRule(['amount' => 499, 'decimals' => 2, 'currency' => 'EUR']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects missing denomination with ERR_VALIDATION_002', function (): void {
    $msg = runMoneyRule(['amount' => '499', 'decimals' => 2]);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects both denominations with ERR_VALIDATION_002', function (): void {
    $msg = runMoneyRule([
        'amount'   => '499',
        'decimals' => 2,
        'currency' => 'EUR',
        'asset'    => 'USDC',
    ]);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002', 'exactly one');
});

it('rejects decimals out of range high', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => 19, 'asset' => 'ETH']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002', '0..18');
});

it('rejects decimals out of range low', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => -1, 'asset' => 'ETH']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002', '0..18');
});

it('rejects non-array value', function (): void {
    $msg = runMoneyRule('4.99');

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects currency that is not exactly 3 chars', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => 2, 'currency' => 'EU']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects lowercase currency code', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => 2, 'currency' => 'eur']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002', 'uppercase');
});

it('rejects empty asset string', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => 6, 'asset' => '']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects asset longer than 16 chars', function (): void {
    $msg = runMoneyRule([
        'amount'   => '1',
        'decimals' => 6,
        'asset'    => str_repeat('A', 17),
    ]);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects non-string amount as integer', function (): void {
    $msg = runMoneyRule(['amount' => 100, 'decimals' => 2, 'currency' => 'EUR']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects float amount', function (): void {
    $msg = runMoneyRule(['amount' => 1.0, 'decimals' => 2, 'currency' => 'EUR']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});

it('rejects non-integer decimals', function (): void {
    $msg = runMoneyRule(['amount' => '1', 'decimals' => '2', 'currency' => 'EUR']);

    expect($msg)->not->toBeNull()->toContain('ERR_VALIDATION_002');
});
