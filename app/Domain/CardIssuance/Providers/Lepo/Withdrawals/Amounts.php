<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo\Withdrawals;

use App\Support\DecimalString;

/**
 * Exact decimal helpers for withdrawals (bcmath, never floats). What the
 * user receives is rounded down; every fee is rounded up.
 */
final class Amounts
{
    private function __construct()
    {
    }

    /**
     * A provider amount (JSON number or decimal string) truncated to $scale
     * decimals; null when it is missing, malformed or negative.
     *
     * @return numeric-string|null
     */
    public static function parse(mixed $value, int $scale): ?string
    {
        if (is_float($value)) {
            $value = is_finite($value) ? sprintf('%.6F', $value) : null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $decimal = DecimalString::fromNumeric($value, $scale);

        return $decimal !== null && bccomp($decimal, '0', $scale) >= 0 ? $decimal : null;
    }

    /**
     * A stored amount (a decimal cast reads back as a string), or zero when
     * there is none.
     *
     * @return numeric-string
     */
    public static function of(mixed $value, int $scale): string
    {
        return self::parse($value, $scale) ?? bcadd('0', '0', $scale);
    }

    /**
     * Rounded up to $scale decimals (for fees).
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function up(string $value, int $scale): string
    {
        $truncated = bcadd($value, '0', $scale);
        if (bccomp($value, $truncated, 18) === 1) {
            $truncated = bcadd($truncated, bcpow('10', (string) -$scale, $scale), $scale);
        }

        return $truncated;
    }

    /**
     * Rounded down to $scale decimals (for what the user receives).
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function down(string $value, int $scale): string
    {
        return bcadd($value, '0', $scale);
    }

    /**
     * Whole cents, rounded down.
     *
     * @param  numeric-string  $value
     */
    public static function cents(string $value): int
    {
        return (int) bcmul($value, '100', 0);
    }

    /**
     * Whole cents, rounded up.
     *
     * @param  numeric-string  $value
     */
    public static function centsUp(string $value): int
    {
        return (int) bcmul(self::up($value, 2), '100', 0);
    }

    /**
     * @return numeric-string
     */
    public static function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function max(string $a, string $b, int $scale = 6): string
    {
        return bccomp($a, $b, $scale) >= 0 ? $a : $b;
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function min(string $a, string $b, int $scale = 6): string
    {
        return bccomp($a, $b, $scale) <= 0 ? $a : $b;
    }
}
