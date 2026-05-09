<?php

/**
 * Money value object — wire-format Money per ADR-0004.
 *
 * Smallest-unit string + explicit decimals + exactly one of currency (ISO 4217)
 * or asset (ticker). All arithmetic via bcmath; never (float).
 *
 * @see docs/adr/0004-money-on-the-wire.md
 */

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final class Money implements JsonSerializable
{
    public function __construct(
        public readonly string $amount,
        public readonly int $decimals,
        public readonly ?string $currency = null,
        public readonly ?string $asset = null,
    ) {
        if (preg_match('/^-?[0-9]+$/', $amount) !== 1) {
            throw new InvalidArgumentException(
                'Money amount must be an integer string (no decimal point), got: ' . $amount
            );
        }

        if ($decimals < 0 || $decimals > 18) {
            throw new InvalidArgumentException(
                'Money decimals must be between 0 and 18 inclusive, got: ' . $decimals
            );
        }

        $hasCurrency = $currency !== null && $currency !== '';
        $hasAsset = $asset !== null && $asset !== '';

        if (! $hasCurrency && ! $hasAsset) {
            throw new InvalidArgumentException(
                'Money must specify exactly one of currency (fiat) or asset (token).'
            );
        }

        if ($hasCurrency && $hasAsset) {
            throw new InvalidArgumentException(
                'Money cannot specify both currency and asset; choose exactly one.'
            );
        }

        if ($hasCurrency && strlen((string) $currency) !== 3) {
            throw new InvalidArgumentException(
                'Money currency must be a 3-letter ISO 4217 code, got: ' . $currency
            );
        }
    }

    /**
     * Construct EUR money from a smallest-unit cents string.
     */
    public static function eur(string $cents): self
    {
        return new self(amount: $cents, decimals: 2, currency: 'EUR');
    }

    /**
     * Construct asset money (e.g. USDC) at the given decimals.
     */
    public static function asset(string $smallestUnits, string $asset, int $decimals): self
    {
        return new self(amount: $smallestUnits, decimals: $decimals, asset: $asset);
    }

    /**
     * Add another Money value of the same denomination + decimals.
     *
     * @throws InvalidArgumentException when denominations or decimals differ
     */
    public function add(self $other): self
    {
        $this->assertSameDenomination($other);

        return new self(
            amount: bcadd($this->numericAmount(), $other->numericAmount(), 0),
            decimals: $this->decimals,
            currency: $this->currency,
            asset: $this->asset,
        );
    }

    /**
     * Subtract another Money value of the same denomination + decimals.
     *
     * @throws InvalidArgumentException when denominations or decimals differ
     */
    public function subtract(self $other): self
    {
        $this->assertSameDenomination($other);

        return new self(
            amount: bcsub($this->numericAmount(), $other->numericAmount(), 0),
            decimals: $this->decimals,
            currency: $this->currency,
            asset: $this->asset,
        );
    }

    public function negate(): self
    {
        return new self(
            amount: bcmul($this->numericAmount(), '-1', 0),
            decimals: $this->decimals,
            currency: $this->currency,
            asset: $this->asset,
        );
    }

    /**
     * Compare to another Money. Returns -1, 0, 1.
     *
     * @throws InvalidArgumentException when denominations differ
     */
    public function compare(self $other): int
    {
        $this->assertSameDenomination($other);

        return bccomp($this->numericAmount(), $other->numericAmount(), 0);
    }

    public function isZero(): bool
    {
        return bccomp($this->numericAmount(), '0', 0) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->numericAmount(), '0', 0) < 0;
    }

    /**
     * Return the validated amount as a numeric-string (PHPStan-friendly narrowing).
     * The constructor regex guarantees this; the runtime `is_numeric` check is the
     * narrowing assertion PHPStan recognises.
     *
     * @return numeric-string
     */
    private function numericAmount(): string
    {
        if (! is_numeric($this->amount)) {
            // Unreachable: constructor validates with regex /^-?[0-9]+$/.
            throw new InvalidArgumentException('Money amount is not numeric: ' . $this->amount);
        }

        return $this->amount;
    }

    public function isFiat(): bool
    {
        return $this->currency !== null;
    }

    public function denomination(): string
    {
        return $this->currency ?? (string) $this->asset;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        $out = [
            'amount'   => $this->amount,
            'decimals' => $this->decimals,
        ];

        if ($this->currency !== null) {
            $out['currency'] = $this->currency;
        } else {
            $out['asset'] = (string) $this->asset;
        }

        return $out;
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function assertSameDenomination(self $other): void
    {
        if ($this->decimals !== $other->decimals) {
            throw new InvalidArgumentException(
                'Cannot operate on Money values with different decimals.'
            );
        }

        if ($this->currency !== $other->currency || $this->asset !== $other->asset) {
            throw new InvalidArgumentException(
                'Cannot operate on Money values with different denominations.'
            );
        }
    }
}
