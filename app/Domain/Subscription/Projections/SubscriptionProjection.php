<?php

/**
 * SubscriptionProjection — unified entitlements read model (Plan B Backend-Q1).
 *
 * Slice 1 read Cashier subscriptions only; slice 2 unions in IAP rows from
 * `iap_subscriptions` without changing the wire shape. `source` carries
 * `stripe_web`, `apple_iap`, or `google_play`; the conflict gate
 * `hasActiveProSubscription(..., source: 'iap')` returns the OR of apple and
 * google IAP rows so slice 1's existing call sites become accurate.
 *
 * Read-time union (rather than a materialised projection) per §8.4 of the
 * spec — cached version is deferred.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Projections;

use App\Domain\Subscription\Models\IapSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription as CashierSubscription;

final class SubscriptionProjection
{
    /**
     * @return array{
     *     tier: string,
     *     status: string|null,
     *     source: string|null,
     *     plan: string|null,
     *     currentPeriodEnd: string|null,
     *     trialEndsAt: string|null,
     *     cancelledAtPeriodEnd: bool,
     *     pausedUntil: string|null
     * }
     */
    public function for(User $user): array
    {
        $stripeShape = $this->stripeShape($user);
        $iapShape = $this->iapShape($user);

        // Pick the source with the later currentPeriodEnd. If only one source
        // is present, that one wins. The conflict gate at /iap/verify and
        // /checkout time should already prevent a true two-source race, but
        // defensive code here matches the §5.8 union rule.
        if ($iapShape === null) {
            return $stripeShape;
        }

        if ($stripeShape['tier'] === 'free') {
            return $iapShape;
        }

        $stripeEnd = $stripeShape['currentPeriodEnd'];
        $iapEnd = $iapShape['currentPeriodEnd'];

        if ($stripeEnd === null) {
            return $iapShape;
        }
        if ($iapEnd === null) {
            return $stripeShape;
        }

        return Carbon::parse($iapEnd)->gt(Carbon::parse($stripeEnd))
            ? $iapShape
            : $stripeShape;
    }

    /**
     * Cross-source conflict gate. Slice 2 wires the IAP source.
     *
     * - `stripe`         → Cashier-active subscription
     * - `apple`          → live `iap_subscriptions` row with store=apple
     * - `google`         → same with store=google
     * - `iap`            → OR of apple+google (used by the slice 1 Stripe-side gate)
     */
    public function hasActiveProSubscription(User $user, string $source): bool
    {
        if ($source === 'stripe') {
            $subscription = $user->subscription('default');

            return $subscription !== null && $subscription->valid();
        }

        $query = IapSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', IapSubscription::ALIVE_STATUSES);

        if ($source === IapSubscription::STORE_APPLE) {
            $query->where('store', IapSubscription::STORE_APPLE);
        } elseif ($source === IapSubscription::STORE_GOOGLE) {
            $query->where('store', IapSubscription::STORE_GOOGLE);
        }
        // For 'iap' (or any other value) — no further filter; the alive-status
        // filter alone narrows correctly.

        return $query->exists();
    }

    /**
     * @return array{
     *     tier: string,
     *     status: string|null,
     *     source: string|null,
     *     plan: string|null,
     *     currentPeriodEnd: string|null,
     *     trialEndsAt: string|null,
     *     cancelledAtPeriodEnd: bool,
     *     pausedUntil: string|null
     * }
     */
    private function stripeShape(User $user): array
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            return [
                'tier'                 => 'free',
                'status'               => null,
                'source'               => null,
                'plan'                 => null,
                'currentPeriodEnd'     => null,
                'trialEndsAt'          => null,
                'cancelledAtPeriodEnd' => false,
                'pausedUntil'          => null,
            ];
        }

        $isPro = $subscription->valid();
        $cancelledAtPeriodEnd = $subscription->ends_at !== null
            && Carbon::parse($subscription->ends_at)->isFuture();

        return [
            'tier'             => $isPro ? 'pro' : 'free',
            'status'           => (string) $subscription->stripe_status,
            'source'           => 'stripe_web',
            'plan'             => $this->planFromPriceId((string) $subscription->stripe_price),
            'currentPeriodEnd' => $this->currentPeriodEnd($subscription)?->toIso8601String(),
            'trialEndsAt'      => $subscription->trial_ends_at !== null
                ? Carbon::parse($subscription->trial_ends_at)->toIso8601String()
                : null,
            'cancelledAtPeriodEnd' => $cancelledAtPeriodEnd,
            'pausedUntil'          => null,
        ];
    }

    /**
     * Read the most recent alive IAP row (apple OR google). Returns null when
     * the user has no live IAP subscription.
     *
     * @return array{
     *     tier: string,
     *     status: string|null,
     *     source: string|null,
     *     plan: string|null,
     *     currentPeriodEnd: string|null,
     *     trialEndsAt: string|null,
     *     cancelledAtPeriodEnd: bool,
     *     pausedUntil: string|null
     * }|null
     */
    private function iapShape(User $user): ?array
    {
        /** @var IapSubscription|null $sub */
        $sub = IapSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', IapSubscription::ALIVE_STATUSES)
            ->orderByDesc('current_period_ends_at')
            ->first();

        if ($sub === null) {
            return null;
        }

        $source = $sub->store === IapSubscription::STORE_APPLE ? 'apple_iap' : 'google_play';

        return [
            'tier'                 => 'pro',
            'status'               => $sub->status,
            'source'               => $source,
            'plan'                 => $this->planForIapSubscription($sub),
            'currentPeriodEnd'     => $sub->current_period_ends_at?->toIso8601String(),
            'trialEndsAt'          => $sub->trial_ends_at?->toIso8601String(),
            'cancelledAtPeriodEnd' => (bool) $sub->cancel_at_period_end,
            'pausedUntil'          => $sub->paused_until?->toIso8601String(),
        ];
    }

    private function planForIapSubscription(IapSubscription $sub): ?string
    {
        // Best-effort: read the most recent receipt's product_id and reverse-map.
        // We avoid the FK round-trip from the projection hot path and instead
        // rely on the tier column (we currently only support `pro`); the plan
        // field reflects monthly vs annual via the trial/period heuristic.
        $store = $sub->store;
        $map = (array) config("subscription.iap.{$store}.product_ids", []);

        if ($map === []) {
            return null;
        }

        // Without joining receipts we can't map exactly — but the most recent
        // receipt's product_id is the authoritative source. Fall back to a
        // single lookup; this is acceptable for v1.3.0 traffic levels.
        /** @var \App\Domain\Subscription\Models\IapReceipt|null $receipt */
        $receipt = \App\Domain\Subscription\Models\IapReceipt::query()
            ->where('iap_subscription_id', $sub->id)
            ->orderByDesc('id')
            ->first();

        if ($receipt === null) {
            return null;
        }

        foreach ($map as $plan => $productId) {
            if ((string) $productId === (string) $receipt->product_id) {
                return (string) $plan;
            }
        }

        return null;
    }

    private function currentPeriodEnd(CashierSubscription $subscription): ?Carbon
    {
        $candidate = $subscription->ends_at
            ?? ($subscription->trial_ends_at !== null
                && Carbon::parse($subscription->trial_ends_at)->isFuture()
                ? $subscription->trial_ends_at
                : null);

        return $candidate !== null ? Carbon::parse($candidate) : null;
    }

    private function planFromPriceId(string $priceId): ?string
    {
        $prices = (array) config('services.stripe.subscription_prices', []);

        foreach ($prices as $plan => $configuredPrice) {
            if ((string) $configuredPrice === $priceId && $configuredPrice !== '') {
                return (string) $plan;
            }
        }

        return null;
    }
}
