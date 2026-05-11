<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Quote value object — wire-facing representation of an issued price quote.
 *
 * This is a serialisation VO, distinct from the PriceQuote Eloquent model.
 * Constructed by PriceQuoteIssuer and returned to PricingController for
 * JSON serialisation. Implements JsonSerializable so the controller can
 * call response()->json($quote) directly.
 *
 * All money fields are Money VO triples per ADR-0004. Wire keys are camelCase
 * per spec §10 wire contract (quoteId, expiresAt, feeBreakdown, …).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.7
 * @see docs/adr/0004-money-on-the-wire.md
 */
final readonly class Quote implements JsonSerializable
{
    /**
     * @param  array<int, array<string, mixed>>  $feeBreakdown
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>|null  $saveWithPro
     */
    public function __construct(
        /** UUID — matches price_quotes.id; wire key: quoteId. */
        public readonly string $id,
        public readonly string $kind,
        /** Null for dry-run requests (no persistence). */
        public readonly ?DateTimeImmutable $expiresAt,
        /** Array of fee-breakdown line items. */
        public readonly array $feeBreakdown,
        /** Rate map keyed by pair string e.g. "USDC/EUR". */
        public readonly array $rates,
        public readonly FeeTier $feeTier,
        /** keccak256 of canonicalized userOp; null for fiat-only kinds. */
        public readonly ?string $userOpHash,
        /** True when a refresh produced a materially different fee (Q3.2). */
        public readonly bool $termsChanged,
        /** Null when user is Pro tier (no saving) or native-asset send (no service fee). */
        public readonly ?array $saveWithPro,
        /**
         * Status field — populated on GET /pricing/quote/{quoteId} responses only.
         * Null in POST responses.
         */
        public readonly ?string $status = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'quoteId'      => $this->id,
            'kind'         => $this->kind,
            'expiresAt'    => $this->expiresAt?->format(DateTimeImmutable::ATOM),
            'feeBreakdown' => $this->feeBreakdown,
            'rates'        => $this->rates,
            'feeTier'      => $this->feeTier->jsonSerialize(),
            'userOpHash'   => $this->userOpHash,
            'termsChanged' => $this->termsChanged,
            'saveWithPro'  => $this->saveWithPro,
        ];

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
    }
}
