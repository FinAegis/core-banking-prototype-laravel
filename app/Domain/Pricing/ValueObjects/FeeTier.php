<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use JsonSerializable;

/**
 * FeeTier value object — wire-facing representation of a user's resolved fee tier.
 *
 * Carried by the Quote VO. Contains the resolved fee amounts and margin
 * basis-point values as of quote-issuance time.
 *
 * Note (F-17): no `id` property on the wire-facing feeTier object. Free/Pro
 * distinction is available from swapMarginBps value (Pro = 5, Free = 20).
 * Internal logging may use the tier name string; it is NOT serialised here.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.7
 */
final readonly class FeeTier implements JsonSerializable
{
    public function __construct(
        /** Flat transaction fee in the send/swap asset. Null for fiat-only kinds. */
        public readonly ?Money $txFlat,
        /** Swap margin in basis points (1 bp = 0.01%). */
        public readonly int $swapMarginBps,
        /** Ramp (on/off) margin in basis points. */
        public readonly int $rampMarginBps,
        /** Internal tier name for logging — NOT included in wire serialisation. */
        public readonly string $tierName,
    ) {
    }

    /**
     * @return array{txFlat: array<string, mixed>|null, swapMarginBps: int, rampMarginBps: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'txFlat'        => $this->txFlat?->jsonSerialize(),
            'swapMarginBps' => $this->swapMarginBps,
            'rampMarginBps' => $this->rampMarginBps,
        ];
    }
}
