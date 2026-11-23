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
