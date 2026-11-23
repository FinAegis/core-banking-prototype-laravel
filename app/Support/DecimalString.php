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
 */
final class DecimalString
{
    /**
     * @return numeric-string|null
     */
    public static function fromNumeric(mixed $value, int $scale): ?string
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
            $normalized = (string) BigDecimal::of($value)->toScale($scale, RoundingMode::Down);
        } catch (MathException) {
            return null;
        }

        return is_numeric($normalized) ? $normalized : null;
    }
}
