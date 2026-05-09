<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exceptions;

use RuntimeException;

/**
 * Thrown when arithmetic between two Money value objects is attempted with
 * mismatched denomination (EUR vs USDC) or mismatched decimals (e.g. EUR-2
 * vs EUR-3 — should not happen for fiat, but the VO is decimals-strict so
 * the assertion is unconditional).
 *
 * Internal arithmetic between EUR and USDC is a programming error and
 * MUST fail loud — silently coercing one side to the other is the kind
 * of bug that ships money to the wrong place.
 */
final class MoneyMismatchException extends RuntimeException
{
    public static function denominationMismatch(string $a, string $b): self
    {
        return new self(sprintf(
            'Cannot perform arithmetic on Money values with different denominations: "%s" vs "%s".',
            $a,
            $b,
        ));
    }

    public static function decimalsMismatch(int $a, int $b): self
    {
        return new self(sprintf(
            'Cannot perform arithmetic on Money values with different decimals: %d vs %d.',
            $a,
            $b,
        ));
    }
}
