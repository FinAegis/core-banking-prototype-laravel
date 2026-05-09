<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use InvalidArgumentException;

/**
 * Helpers for parsing decimal-string Money inputs and formatting Money for
 * display. Distinct from the Money VO itself, which is the canonical
 * smallest-unit-string representation.
 *
 * For v1.3.0 we use simple decimal-point formatting with no thousands
 * separator and no locale-specific grouping. Mobile/web own display-layer
 * polish; this is the backend canonical formatter.
 *
 * @see docs/adr/0004-money-on-the-wire.md
 */
final class MoneyFormatter
{
    private const KNOWN_FIAT_SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
    ];

    private function __construct()
    {
        // Static helpers only.
    }

    /**
     * Render a Money VO as a human-readable string.
     *
     * EUR €4.99             → "€4.99"
     * USDC 1.000000         → "1.000000 USDC"
     * EUR -€0.05            → "-€0.05"
     * Unknown fiat (e.g. SEK)  → "4.99 SEK"
     */
    public static function format(Money $money): string
    {
        $decimal = self::amountToDecimalString($money->amount, $money->decimals);

        if ($money->isFiat && isset(self::KNOWN_FIAT_SYMBOLS[$money->denomination])) {
            $symbol = self::KNOWN_FIAT_SYMBOLS[$money->denomination];

            // Negative goes outside the symbol: "-€0.05".
            if (str_starts_with($decimal, '-')) {
                return '-' . $symbol . substr($decimal, 1);
            }

            return $symbol . $decimal;
        }

        return $decimal . ' ' . $money->denomination;
    }

    /**
     * Parse a decimal-string fiat amount ("4.99") into a Money VO.
     *
     * Currency code MUST be exactly 3 chars (ISO-4217). Decimals default to 2
     * for typical fiat — callers needing 0-decimal currencies (JPY, KRW) or
     * 3-decimal ones (BHD) should use Money::fiat() directly.
     *
     * @throws InvalidArgumentException on malformed decimal or wrong currency length
     */
    public static function parseFiat(string $decimal, string $currency, int $decimals = 2): Money
    {
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException(sprintf(
                'Fiat currency must be 3 chars (ISO-4217), got "%s".',
                $currency,
            ));
        }

        return Money::fiat(
            amount: self::decimalStringToAmount($decimal, $decimals),
            decimals: $decimals,
            currency: $currency,
        );
    }

    /**
     * Parse a decimal-string asset amount ("1.000000") into a Money VO.
     *
     * @throws InvalidArgumentException on malformed decimal
     */
    public static function parseAsset(string $decimal, string $asset, int $decimals): Money
    {
        return Money::asset(
            amount: self::decimalStringToAmount($decimal, $decimals),
            decimals: $decimals,
            asset: $asset,
        );
    }

    /**
     * Smallest-unit string + decimals → human decimal string.
     *
     * "499", 2  → "4.99"
     * "0", 2    → "0.00"
     * "-499", 2 → "-4.99"
     * "1", 6    → "0.000001"
     * "1000000", 6 → "1.000000"
     * "499", 0  → "499"
     */
    private static function amountToDecimalString(string $amount, int $decimals): string
    {
        if ($decimals === 0) {
            return $amount;
        }

        $negative = str_starts_with($amount, '-');
        $abs = $negative ? substr($amount, 1) : $amount;

        // Pad with leading zeros so the integer part is at least 1 digit.
        if (strlen($abs) <= $decimals) {
            $abs = str_pad($abs, $decimals + 1, '0', STR_PAD_LEFT);
        }

        $intPart = substr($abs, 0, -$decimals);
        $fracPart = substr($abs, -$decimals);

        return ($negative ? '-' : '') . $intPart . '.' . $fracPart;
    }

    /**
     * Human decimal string → smallest-unit integer string.
     *
     * "4.99", 2 → "499"
     * "4", 2    → "400"
     * "0", 2    → "0"
     * "-4.99", 2 → "-499"
     * "1.000000", 6 → "1000000"
     * "0.5", 2  → "50"
     *
     * Rejects: scientific notation, multiple decimal points, trailing-decimal
     * fractions longer than `decimals`.
     *
     * @return numeric-string
     */
    private static function decimalStringToAmount(string $decimal, int $decimals): string
    {
        if (preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $decimal) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Malformed decimal string: "%s".',
                $decimal,
            ));
        }

        $negative = str_starts_with($decimal, '-');
        $abs = $negative ? substr($decimal, 1) : $decimal;

        $dotPos = strpos($abs, '.');

        if ($dotPos === false) {
            $intPart = $abs;
            $fracPart = '';
        } else {
            $intPart = substr($abs, 0, $dotPos);
            $fracPart = substr($abs, $dotPos + 1);
        }

        if (strlen($fracPart) > $decimals) {
            throw new InvalidArgumentException(sprintf(
                'Decimal "%s" has more fractional digits than allowed by decimals=%d.',
                $decimal,
                $decimals,
            ));
        }

        $fracPart = str_pad($fracPart, $decimals, '0', STR_PAD_RIGHT);

        // Strip any leading zeros from int part except keep at least "0".
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') {
            $intPart = '0';
        }

        $combined = $intPart . $fracPart;
        // Re-strip any leading zeros introduced by the concatenation while
        // keeping at least one digit so "0.00" → "0" and not "".
        $combined = ltrim($combined, '0');
        if ($combined === '') {
            $combined = '0';
        }

        $signed = ($negative && $combined !== '0') ? '-' . $combined : $combined;

        // Normalise via bcadd so PHPStan sees the result as numeric-string.
        // The regex above already guarantees it is numeric; this is a no-op
        // typing wrapper.
        if (! is_numeric($signed)) {
            // Defensive: should be unreachable given the regex above.
            throw new InvalidArgumentException('Internal: decimal-to-amount produced non-numeric output.');
        }

        return bcadd($signed, '0', 0);
    }
}
