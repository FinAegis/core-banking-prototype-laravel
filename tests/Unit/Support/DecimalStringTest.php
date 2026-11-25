<?php

declare(strict_types=1);

use App\Support\DecimalString;

it('normalises plain numeric strings and ints exactly like bcadd', function (mixed $input, int $scale, string $expected): void {
    expect(DecimalString::fromNumeric($input, $scale))->toBe($expected);
})->with([
    'decimal string'          => ['10.5', 8, '10.50000000'],
    'integer'                 => [42, 2, '42.00'],
    'truncates, never rounds' => ['0.123456789', 8, '0.12345678'],
    'negative truncates'      => ['-1.239', 2, '-1.23'],
    'leading plus sign'       => ['+5', 0, '5'],
    'zero scale'              => ['7.99', 0, '7'],
]);

it('keeps the shortest float representation that bcadd sees today', function (): void {
    // (string) 0.29 is "0.29"; a fixed 20-digit format would expose the
    // binary error (0.28999999...) and change the truncated result.
    expect(DecimalString::fromNumeric(0.29, 8))->toBe('0.29000000')
        ->and(DecimalString::fromNumeric(0.1, 2))->toBe('0.10')
        ->and(DecimalString::fromNumeric(1234.5, 4))->toBe('1234.5000');
});

it('expands floats that PHP stringifies in exponent form', function (): void {
    // (string) 0.000001 === "1.0E-6", which bcadd() rejects with a ValueError.
    expect(DecimalString::fromNumeric(0.000001, 8))->toBe('0.00000100')
        ->and(DecimalString::fromNumeric(1.0E+21, 2))->toBe('1000000000000000000000.00')
        ->and(DecimalString::fromNumeric('2.5e-3', 4))->toBe('0.0025');
});

it('returns null for values bcmath cannot take', function (mixed $input): void {
    expect(DecimalString::fromNumeric($input, 8))->toBeNull();
})->with([
    'INF'                => [INF],
    'negative INF'       => [-INF],
    'NAN'                => [NAN],
    'leading whitespace' => [' 12'],
    'trailing newline'   => ["12\n"],
    'hex string'         => ['0x1A'],
    'empty string'       => [''],
    'word'               => ['abc'],
    'null'               => [null],
    'bool'               => [true],
    'array'              => [['1']],
]);

it('converts a major-unit amount to exact minor units', function (mixed $input, int $precision, string $expected): void {
    expect(DecimalString::toMinorUnits($input, $precision))->toBe($expected);
})->with([
    // (int) (0.29 * 100) is 28 and (int) (128.14 * 100) is 12813.
    'float 0.29 at 2'        => [0.29, 2, '29'],
    'float 128.14 at 2'      => [128.14, 2, '12814'],
    'string 128.14 at 2'     => ['128.14', 2, '12814'],
    'integer at 2'           => [50, 2, '5000'],
    'trailing zeros allowed' => ['1.2300', 2, '123'],
    '1.1 at 18 decimals'     => [1.1, 18, '1100000000000000000'],
    '1.1 at 8 decimals'      => ['1.1', 8, '110000000'],
    'zero precision'         => ['7', 0, '7'],
    'negative'               => ['-0.29', 2, '-29'],
    'exponent float'         => [0.000001, 6, '1'],
    'larger than an int'     => ['100', 18, '100000000000000000000'],
]);

it('refuses an amount with more decimals than the asset has', function (mixed $input, int $precision): void {
    expect(DecimalString::toMinorUnits($input, $precision))->toBeNull()
        ->and(DecimalString::toMinorUnitsInt($input, $precision))->toBeNull();
})->with([
    'three decimals at 2' => ['0.295', 2],
    'float at 2'          => [128.145, 2],
    'any decimal at 0'    => ['7.5', 0],
    'nine decimals at 8'  => ['0.000000001', 8],
]);

it('refuses values that are not finite, well-formed numbers', function (mixed $input): void {
    expect(DecimalString::toMinorUnits($input, 2))->toBeNull()
        ->and(DecimalString::toMinorUnitsInt($input, 2))->toBeNull();
})->with([
    'INF'          => [INF],
    'NAN'          => [NAN],
    'whitespace'   => [' 12'],
    'empty string' => [''],
    'word'         => ['abc'],
    'null'         => [null],
    'bool'         => [true],
]);

it('gives minor units as an int only when they fit in one', function (): void {
    expect(DecimalString::toMinorUnitsInt(0.29, 2))->toBe(29)
        ->and(DecimalString::toMinorUnitsInt('1.1', 18))->toBe(1100000000000000000)
        // 10 units of an 18-decimal asset is 10^19, above PHP_INT_MAX.
        ->and(DecimalString::toMinorUnitsInt('10', 18))->toBeNull()
        ->and(DecimalString::toMinorUnitsInt('-10', 18))->toBeNull();
});
