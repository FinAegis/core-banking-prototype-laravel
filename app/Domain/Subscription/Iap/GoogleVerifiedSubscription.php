<?php

/**
 * GoogleVerifiedSubscription — typed result of GooglePlayReceiptVerifier::verify().
 *
 * Stable subscription resource id (`subscriptionResourceId`) is returned by
 * Play Developer API `purchases.subscriptionsv2.get`. This is the canonical
 * Google PK for `iap_subscriptions.play_subscription_resource_id` (we
 * deliberately do NOT use `orderId` — see §8.7 of the spec, mobile-dev
 * confirmed orderId is unreliable across sandbox/prod).
 *
 * Money: Google's `priceAmountMicros` is integer-string with implicit 6
 * decimals. Stored on `iap_receipts` as smallest_unit + decimals=6 +
 * priceCurrencyCode for reconciliation.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.7
 * @see docs/adr/0004-money-on-the-wire.md
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use Illuminate\Support\Carbon;

final class GoogleVerifiedSubscription
{
    public function __construct(
        public readonly string $purchaseToken,
        public readonly string $productId,
        public readonly string $packageName,
        public readonly string $subscriptionResourceId,
        public readonly ?string $obfuscatedAccountId,
        public readonly int $amountSmallestUnit,
        public readonly int $amountDecimals,
        public readonly string $amountCurrency,
        public readonly ?Carbon $startTime,
        public readonly ?Carbon $expiryTime,
        public readonly bool $autoRenewing,
        public readonly bool $isTrialPeriod,
        public readonly string $state,
        public readonly bool $isAcknowledged,
        public readonly ?string $linkedPurchaseToken,
    ) {
    }
}
