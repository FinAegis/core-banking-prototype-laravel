<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Domain\Asset\Models\Asset;
use App\Support\DecimalString;
use Illuminate\Validation\ValidationException;

/**
 * Converts an amount a client sends in major units ("128.14" USD, "1.1" ETH)
 * to the asset's minor units (12814 cents, 1100000000000000000 wei) with
 * exact decimal arithmetic. Float maths is wrong for assets with many
 * decimals: it books 1.1 ETH as 1100000000000000128 wei.
 */
trait ConvertsMajorUnitAmounts
{
    /**
     * How many decimals past the asset's precision are read to find a digit
     * that cannot be booked.
     */
    private const EXTRA_DECIMALS_CHECKED = 64;

    /**
     * @throws ValidationException when the amount is not a number, has more
     *                             decimals than the asset has, or is too
     *                             large for the ledger
     */
    protected function minorUnitsOrFail(mixed $amount, Asset $asset): int
    {
        $precision = (int) ($asset->getAttribute('precision') ?? 2);
        $code = (string) $asset->getAttribute('code');
        $scanScale = $precision + self::EXTRA_DECIMALS_CHECKED;

        $booked = DecimalString::fromNumeric($amount, $precision);
        $scanned = DecimalString::fromNumeric($amount, $scanScale);

        if ($booked === null || $scanned === null) {
            throw ValidationException::withMessages(['amount' => ['The amount must be a number.']]);
        }

        if (bccomp($booked, $scanned, $scanScale) !== 0) {
            throw ValidationException::withMessages(['amount' => [
                $precision === 0
                    ? "The amount must be a whole number for {$code}."
                    : "The amount can have at most {$precision} decimals for {$code}.",
            ]]);
        }

        $minor = bcmul($booked, bcpow('10', (string) $precision), 0);

        if (bccomp($minor, (string) PHP_INT_MAX) > 0) {
            throw ValidationException::withMessages(['amount' => ['The amount is too large.']]);
        }

        return (int) $minor;
    }
}
