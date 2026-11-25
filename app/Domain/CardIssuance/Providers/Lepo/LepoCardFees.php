<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

/**
 * The card's fees, from configuration: a one-time issuance fee, a monthly fee
 * taken from the card balance for the month ahead, and a card-load fee as a
 * fraction of the loaded amount. Also the card-load arithmetic that depends
 * on them. Amounts are bcmath decimal strings; cents are USD.
 */
final class LepoCardFees
{
    /** The smallest card load the provider accepts, after the load fee (USD). */
    public const LOAD_MINIMUM = '5.00';

    /** Used when the configuration has no issuance fee. */
    private const DEFAULT_ISSUANCE_FEE_CENTS = 100;

    /** Used when the configuration has no monthly fee. */
    private const DEFAULT_MONTHLY_FEE_CENTS = 100;

    /** Used when the configuration has no load fee (1.5 %). */
    private const DEFAULT_TOPUP_FEE_FRACTION = '0.015';

    /**
     * The fees for the app, or null when the configuration does not have all
     * three (never a partial object). topup_fee_percent is a percentage
     * ("1.5" for 1.5 %).
     *
     * @return array{issuance_fee_cents: int, monthly_fee_cents: int, topup_fee_percent: string}|null
     */
    public static function describe(): ?array
    {
        $issuance = self::cents('issuance_fee_cents');
        $monthly = self::cents('monthly_fee_cents');
        $fraction = self::fraction();
        if ($issuance === null || $monthly === null || $fraction === null) {
            return null;
        }

        return [
            'issuance_fee_cents' => $issuance,
            'monthly_fee_cents'  => $monthly,
            'topup_fee_percent'  => self::percent($fraction),
        ];
    }

    public static function issuanceFeeCents(): int
    {
        return self::cents('issuance_fee_cents') ?? self::DEFAULT_ISSUANCE_FEE_CENTS;
    }

    public static function monthlyFeeCents(): int
    {
        return self::cents('monthly_fee_cents') ?? self::DEFAULT_MONTHLY_FEE_CENTS;
    }

    /**
     * @return numeric-string
     */
    public static function topUpFeeFraction(): string
    {
        return self::fraction() ?? self::DEFAULT_TOPUP_FEE_FRACTION;
    }

    /**
     * The load fee on a card load: the fraction of the loaded amount, rounded
     * up to the cent, plus any fixed fee.
     *
     * @param  numeric-string  $value
     * @param  numeric-string  $fraction
     * @param  numeric-string  $fixed
     * @return numeric-string
     */
    public static function loadFee(string $value, string $fraction, string $fixed = '0'): string
    {
        return bcadd(self::centsUp(bcmul($value, $fraction, 8)), $fixed, 2);
    }

    /**
     * What the card receives from a load of $value, after the load fee.
     *
     * @param  numeric-string  $value
     * @param  numeric-string  $fraction
     * @param  numeric-string  $fixed
     * @return numeric-string
     */
    public static function netOfLoad(string $value, string $fraction, string $fixed = '0'): string
    {
        return bcsub(bcadd($value, '0', 2), self::loadFee($value, $fraction, $fixed), 2);
    }

    /**
     * The smallest load, in whole cents, that leaves at least $net on the card
     * after the load fee.
     *
     * @param  numeric-string  $net
     * @param  numeric-string  $fraction
     * @param  numeric-string  $fixed
     * @return numeric-string
     */
    public static function grossForNet(string $net, string $fraction, string $fixed = '0'): string
    {
        // net + fixed = value - value x fraction at best, so value is at least
        // (net + fixed) / (1 - fraction); the cent rounding of the fee can
        // need a cent or two more.
        $keep = bcsub('1', $fraction, 8);
        $value = bccomp($keep, '0', 8) === 1
            ? self::centsUp(bcdiv(bcadd($net, $fixed, 8), $keep, 8))
            : bcadd($net, $fixed, 2);
        while (bccomp(self::netOfLoad($value, $fraction, $fixed), $net, 2) === -1) {
            $value = bcadd($value, '0.01', 2);
        }

        return $value;
    }

    /**
     * A non-negative amount rounded up to the cent.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public static function centsUp(string $amount): string
    {
        $cents = bcmul($amount, '100', 8);
        $whole = bcadd($cents, '0', 0);
        if (bccomp($cents, $whole, 8) === 1) {
            $whole = bcadd($whole, '1', 0);
        }

        return bcdiv($whole, '100', 2);
    }

    /**
     * A fraction as a percentage without needless zeros: 0.015 -> "1.5".
     *
     * @param  numeric-string  $fraction
     */
    public static function percent(string $fraction): string
    {
        $percent = bcmul($fraction, '100', 8);

        return str_contains($percent, '.') ? rtrim(rtrim($percent, '0'), '.') : $percent;
    }

    private static function cents(string $key): ?int
    {
        $value = config("cardissuance.issuers.lepo.fees.{$key}");
        if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $value = (int) trim($value);
        }

        return is_int($value) && $value >= 0 ? $value : null;
    }

    /**
     * @return numeric-string|null
     */
    private static function fraction(): ?string
    {
        $value = config('cardissuance.issuers.lepo.fees.topup_fee_fraction');
        $text = is_string($value) || is_int($value) ? trim((string) $value) : '';

        // A fraction of the loaded amount: below 1 (100 %).
        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 && is_numeric($text) && bccomp($text, '1', 8) === -1
            ? bcadd($text, '0', 8)
            : null;
    }
}
