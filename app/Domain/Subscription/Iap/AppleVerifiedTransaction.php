<?php

/**
 * AppleVerifiedTransaction — typed result of AppleReceiptVerifier::verify().
 *
 * Wire-shape mirror of the relevant fields from Apple's
 * `signedTransactionInfo` JWS payload after local verification. All money is
 * stored per ADR-0004 (`amountSmallestUnit` + `decimals` + `currency`).
 *
 * Apple's `price` field is a currency-amount in the storefront's currency
 * (with implicit 2 decimals for most storefronts; we accept the storefront
 * currency for reconciliation and convert to EUR at projection time).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.7
 * @see docs/adr/0004-money-on-the-wire.md
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use Illuminate\Support\Carbon;

final class AppleVerifiedTransaction
{
    public function __construct(
        public readonly string $originalTransactionId,
        public readonly string $transactionId,
        public readonly string $productId,
        public readonly string $bundleId,
        public readonly ?string $appAccountToken,
        public readonly int $amountSmallestUnit,
        public readonly int $amountDecimals,
        public readonly string $amountCurrency,
        public readonly ?Carbon $purchaseDate,
        public readonly ?Carbon $originalPurchaseDate,
        public readonly ?Carbon $expiresDate,
        public readonly bool $isInIntroOfferPeriod,
        public readonly bool $isTrialPeriod,
        public readonly bool $isFamilyShared,
        public readonly bool $isSandbox,
        public readonly string $rawJws,
    ) {
    }
}
