<?php

/**
 * SubscriptionProjection — unified entitlements read model (Plan B Backend-Q1).
 *
 * Slice 1 reads ONLY from Cashier subscriptions; slice 2 will join in the IAP
 * source without changing the wire shape. The cross-source conflict gate
 * `hasActiveProSubscription(..., source: 'iap')` is wired up now (returns false
 * always) so slice 2 plugs in without touching the call sites.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Projections;

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

        // Slice 1: Stripe-only. Once IAP is added in slice 2 the source detection
        // flips to whichever active subscription is the canonical match.
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
     * Cross-source conflict gate. Slice 2 wires the IAP source; slice 1
     * answers `false` for IAP and uses Cashier for `stripe`.
     */
    public function hasActiveProSubscription(User $user, string $source): bool
    {
        if ($source === 'stripe') {
            $subscription = $user->subscription('default');

            return $subscription !== null && $subscription->valid();
        }

        // IAP source not yet implemented — slice 2 will join the iap_subscriptions
        // projection here without touching call sites.
        return false;
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
