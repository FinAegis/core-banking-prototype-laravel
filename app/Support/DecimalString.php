<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Turns an untrusted numeric value (request input, a JSON number, config or
 * cache) into a plain decimal string that bcmath accepts.
 *
 * is_numeric() accepts values that bcmath rejects with a ValueError: floats
 * that PHP stringifies in exponent form ("1.0E-6"), INF and NAN, and strings
 * with surrounding whitespace. This helper parses the value without float
 * arithmetic, truncates it to $scale decimals exactly like
 * bcadd($value, '0', $scale), and returns null when the value is not a
 * finite, well-formed number.
 *
 * toMinorUnits() turns such a value into the minor units of an asset
 * (cents for a 2-decimal currency) and refuses, rather than rounds, a value
 * with more decimals than the asset has.
 */
final class DecimalString
{
    /**
     * @return numeric-string|null
     */
    public static function fromNumeric(mixed $value, int $scale): ?string
    {
        $decimal = self::parse($value);

        if ($decimal === null) {
            return null;
        }

        $normalized = (string) $decimal->toScale($scale, RoundingMode::Down);

        return is_numeric($normalized) ? $normalized : null;
    }

    /**
     * Converts an untrusted major-unit amount to the minor units of an asset
     * with $precision decimals ("0.29" at precision 2 is "29") without float
     * arithmetic.
     *
     * Returns null when the value is not a finite, well-formed number, and
     * when it has more decimals than $precision: the caller refuses such an
     * amount instead of rounding it.
     *
     * @return numeric-string|null
     */
    public static function toMinorUnits(mixed $value, int $precision): ?string
    {
        $decimal = self::parse($value);

        if ($decimal === null) {
            return null;
        }

        try {
            $minor = (string) $decimal->toScale($precision, RoundingMode::Unnecessary)->getUnscaledValue();
        } catch (MathException) {
            return null;
        }

        return is_numeric($minor) ? $minor : null;
    }

    /**
     * The same as toMinorUnits(), as an int. Also null when the minor units
     * do not fit in a PHP int (for example 10 units of an 18-decimal asset).
     */
    public static function toMinorUnitsInt(mixed $value, int $precision): ?int
    {
        $minor = self::toMinorUnits($value, $precision);

        if ($minor === null) {
            return null;
        }

        if (bccomp($minor, (string) PHP_INT_MAX) > 0 || bccomp($minor, (string) PHP_INT_MIN) < 0) {
            return null;
        }

        return (int) $minor;
    }

    private static function parse(mixed $value): ?BigDecimal
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }

            // PHP's shortest round-trip form ("0.29", "1.0E-6"), the same
            // string bcadd((string) $value, ...) receives today.
            $value = (string) $value;
        } elseif (is_int($value)) {
            $value = (string) $value;
        } elseif (! is_string($value) || trim($value) !== $value) {
            // Brick's parser would accept a trailing "\n" (PCRE "$"); bcmath does not.
            return null;
        }

        try {
            return BigDecimal::of($value);
        } catch (MathException) {
            return null;
        }
    }
}
