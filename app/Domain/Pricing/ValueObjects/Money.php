<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use App\Domain\Pricing\Exceptions\MoneyMismatchException;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Money value object — implementation of ADR-0004 ("Money on the wire").
 *
 * Every Money-typed field on the Plan B v1.3.0 wire is a triple of
 * (amount, decimals, denomination):
 *
 * - amount       smallest-unit integer string, sign-prefix permitted
 * - decimals     0..18 — the power-of-ten the smallest unit represents
 * - denomination 'EUR' (ISO-4217, fiat) or 'USDC' (token ticker, asset)
 *
 * Internal arithmetic uses bcmath (`bcadd`, `bcsub`, `bccomp`) on the
 * smallest-unit string. Never `(float)` cast for money — see CLAUDE.md
 * #1 subagent mistake.
 *
 * Per ADR-0004: the JSON shape is `{amount, decimals, currency}` for
 * fiat OR `{amount, decimals, asset}` for tokens. NEVER both — that
 * would be `ERR_VALIDATION_002` on the wire.
 *
 * @see docs/adr/0004-money-on-the-wire.md
 */
final readonly class Money implements JsonSerializable
{
    /** Maximum decimals supported (covers ETH's 18). */
    public const MAX_DECIMALS = 18;

    /** Smallest-unit integer string with optional leading sign. */
    private const AMOUNT_REGEX = '/^-?[0-9]+$/';

    public function __construct(
        public string $amount,
        public int $decimals,
        public string $denomination,
        public bool $isFiat,
    ) {
        if (preg_match(self::AMOUNT_REGEX, $amount) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Money amount must match /^-?[0-9]+$/, got "%s".',
                $amount,
            ));
        }

        if ($decimals < 0 || $decimals > self::MAX_DECIMALS) {
            throw new InvalidArgumentException(sprintf(
                'Money decimals must be 0..%d, got %d.',
                self::MAX_DECIMALS,
                $decimals,
            ));
        }

        $denominationLength = strlen($denomination);
        if ($denominationLength < 1 || $denominationLength > 16) {
            throw new InvalidArgumentException(sprintf(
                'Money denomination must be 1..16 chars, got "%s".',
                $denomination,
            ));
        }
    }

    /**
     * Construct a fiat Money triple (currency code, ISO-4217 shape on the wire).
     */
    public static function fiat(string $amount, int $decimals, string $currency): self
    {
        return new self(
            amount: $amount,
            decimals: $decimals,
            denomination: $currency,
            isFiat: true,
        );
    }

    /**
     * Construct an asset Money triple (token ticker, asset shape on the wire).
     */
    public static function asset(string $amount, int $decimals, string $asset): self
    {
        return new self(
            amount: $amount,
            decimals: $decimals,
            denomination: $asset,
            isFiat: false,
        );
    }

    public function add(self $other): self
    {
        $this->assertSameShape($other);

        return new self(
            amount: bcadd($this->numericAmount(), $other->numericAmount(), 0),
            decimals: $this->decimals,
            denomination: $this->denomination,
            isFiat: $this->isFiat,
        );
    }

    public function subtract(self $other): self
    {
        $this->assertSameShape($other);

        return new self(
            amount: bcsub($this->numericAmount(), $other->numericAmount(), 0),
            decimals: $this->decimals,
            denomination: $this->denomination,
            isFiat: $this->isFiat,
        );
    }

    public function negate(): self
    {
        // bcmul with '-1' then bcadd '0' to normalize "-0" → "0".
        $negated = bcmul($this->numericAmount(), '-1', 0);

        return new self(
            amount: bcadd($negated, '0', 0),
            decimals: $this->decimals,
            denomination: $this->denomination,
            isFiat: $this->isFiat,
        );
    }

    public function isZero(): bool
    {
        return bccomp($this->numericAmount(), '0', 0) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->numericAmount(), '0', 0) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->numericAmount(), '0', 0) > 0;
    }

    /**
     * @return int -1 if this < other, 0 if equal, 1 if this > other
     */
    public function compareTo(self $other): int
    {
        $this->assertSameShape($other);

        return bccomp($this->numericAmount(), $other->numericAmount(), 0);
    }

    /**
     * Type-narrowing accessor for `$this->amount`. The constructor's regex
     * already guarantees the value is a numeric string; this method makes
     * that fact visible to PHPStan via is_numeric() narrowing so bcmath
     * functions accept it without a cast.
     *
     * @return numeric-string
     */
    private function numericAmount(): string
    {
        if (! is_numeric($this->amount)) {
            // Unreachable — the constructor's regex prohibits this. Defensive.
            throw new InvalidArgumentException('Money.amount is not numeric.');
        }

        return $this->amount;
    }

    /**
     * Serialize per ADR-0004 — fiat → `currency`, asset → `asset`, never both.
     *
     * @return array{amount: string, decimals: int, currency?: string, asset?: string}
     */
    public function jsonSerialize(): array
    {
        $shape = [
            'amount'   => $this->amount,
            'decimals' => $this->decimals,
        ];

        if ($this->isFiat) {
            $shape['currency'] = $this->denomination;
        } else {
            $shape['asset'] = $this->denomination;
        }

        return $shape;
    }

    private function assertSameShape(self $other): void
    {
        if ($this->denomination !== $other->denomination) {
            throw MoneyMismatchException::denominationMismatch(
                $this->denomination,
                $other->denomination,
            );
        }

        if ($this->decimals !== $other->decimals) {
            throw MoneyMismatchException::decimalsMismatch(
                $this->decimals,
                $other->decimals,
            );
        }

        if ($this->isFiat !== $other->isFiat) {
            // Defensive: same denomination string but one's fiat and one's asset.
            // Treat as denomination mismatch — they're not interchangeable.
            throw MoneyMismatchException::denominationMismatch(
                $this->isFiat ? $this->denomination . ' (fiat)' : $this->denomination . ' (asset)',
                $other->isFiat ? $other->denomination . ' (fiat)' : $other->denomination . ' (asset)',
            );
        }
    }
}
