<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Form-Request validation rule for the composite Money triple.
 *
 * Used in Form Requests like:
 *
 *     'fee' => ['required', 'array', new MoneyFormRule()],
 *
 * Per ADR-0004 a Money payload must be either:
 *
 *     {amount: "499", decimals: 2, currency: "EUR"}    (fiat)
 *
 * or
 *
 *     {amount: "1000000", decimals: 6, asset: "USDC"}  (asset)
 *
 * but never both, never neither. The rule rejects with `ERR_VALIDATION_002`
 * (per the Plan B error registry).
 *
 * @see docs/adr/0004-money-on-the-wire.md
 */
final class MoneyFormRule implements ValidationRule
{
    /** Smallest-unit integer string with optional sign prefix. */
    private const AMOUNT_REGEX = '/^-?[0-9]+$/';

    public function __construct(
        public readonly bool $allowNegative = true,
    ) {
    }

    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = 'ERR_VALIDATION_002';

        if (! is_array($value)) {
            $fail($code . ': Money field must be an object/array.');

            return;
        }

        // amount: must be a string of digits with optional leading sign.
        if (! array_key_exists('amount', $value) || ! is_string($value['amount'])) {
            $fail($code . ': Money.amount must be a string.');

            return;
        }
        if (preg_match(self::AMOUNT_REGEX, $value['amount']) !== 1) {
            $fail($code . ': Money.amount must match /^-?[0-9]+$/ (smallest-unit integer string).');

            return;
        }
        if (! $this->allowNegative && str_starts_with($value['amount'], '-')) {
            $fail($code . ': Money.amount must be non-negative for this field.');

            return;
        }

        // decimals: integer 0..18.
        if (! array_key_exists('decimals', $value) || ! is_int($value['decimals'])) {
            $fail($code . ': Money.decimals must be an integer.');

            return;
        }
        if ($value['decimals'] < 0 || $value['decimals'] > 18) {
            $fail($code . ': Money.decimals must be in range 0..18.');

            return;
        }

        // Denomination: exactly one of currency or asset.
        $hasCurrency = array_key_exists('currency', $value) && $value['currency'] !== null && $value['currency'] !== '';
        $hasAsset = array_key_exists('asset', $value) && $value['asset'] !== null && $value['asset'] !== '';

        if (! $hasCurrency && ! $hasAsset) {
            $fail($code . ': Money must specify either "currency" (fiat) or "asset" (token).');

            return;
        }

        if ($hasCurrency && $hasAsset) {
            $fail($code . ': Money must specify exactly one of "currency" or "asset", not both.');

            return;
        }

        if ($hasCurrency) {
            if (! is_string($value['currency'])) {
                $fail($code . ': Money.currency must be a string.');

                return;
            }
            if (strlen($value['currency']) !== 3) {
                $fail($code . ': Money.currency must be a 3-letter ISO-4217 code.');

                return;
            }
            // Uppercase enforcement — ISO-4217 is uppercase.
            if (strtoupper($value['currency']) !== $value['currency']) {
                $fail($code . ': Money.currency must be uppercase.');

                return;
            }
        } else {
            if (! is_string($value['asset'])) {
                $fail($code . ': Money.asset must be a string.');

                return;
            }
            $assetLength = strlen($value['asset']);
            if ($assetLength < 1 || $assetLength > 16) {
                $fail($code . ': Money.asset must be 1..16 chars.');

                return;
            }
        }
    }
}
