<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Exceptions\FeeResolverException;
use App\Domain\Pricing\ValueObjects\FeeTier;
use App\Domain\Pricing\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * FeeResolverService — resolves a user's fee tier and returns a FeeTier VO.
 *
 * In v1.3.0 the tier is determined from the user's active subscription:
 * if the user has an active Pro subscription the 'pro' tier applies, otherwise
 * the 'free' tier applies. The resolver reads config/pricing.php via config()
 * — it is the sole path between Domain/Subscription and the pricing config.
 *
 * Per ADR-0003 acceptance criterion: "Subscription-domain code never reads
 * config('pricing.tiers') directly; goes through ResolveFeeTier query handler."
 * This service IS that query handler (simplified for v1.3.0 — a full CQRS
 * command/query bus integration is deferred to v1.4 alongside A/B pricing).
 *
 * Result is cached per user for 5 minutes (busted on subscription.changed event
 * in a future slice — currently the TTL drift is acceptable for v1.3.0).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.6
 * @see docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md §2 (fee resolver)
 */
final class FeeResolverService
{
    /** Cache TTL: 5 minutes, matching entitlements TTL. */
    private const CACHE_TTL_SECONDS = 300;

    public function resolve(User $user): FeeTier
    {
        $cacheKey = 'fee_tier:user:' . $user->id;

        try {
            /** @var FeeTier|null $cached */
            $cached = Cache::get($cacheKey);

            if ($cached instanceof FeeTier) {
                return $cached;
            }

            $tier = $this->determineTierName($user);
            $feeTier = $this->buildFeeTier($tier);

            Cache::put($cacheKey, $feeTier, self::CACHE_TTL_SECONDS);

            return $feeTier;
        } catch (Throwable $e) {
            throw FeeResolverException::unresolvable((int) $user->id, $e->getMessage());
        }
    }

    /**
     * Flush the cached fee tier for a user (call on subscription.changed events).
     */
    public function flush(User $user): void
    {
        Cache::forget('fee_tier:user:' . $user->id);
    }

    // ──────────────────────────────────────────────────────────────────────

    /**
     * Determine the tier name for a user.
     *
     * v1.3.0: checks Cashier subscription status. 'pro' if the user has an
     * active Stripe subscription, 'free' otherwise.
     */
    private function determineTierName(User $user): string
    {
        // Check Cashier subscription (Stripe). subscribed() returns true for
        // any active/trialing subscription — the plan name is not checked here
        // as both monthly_pro and annual_pro grant Pro tier.
        // User uses Laravel\Cashier\Billable — subscribed() is always available.
        if ($user->subscribed()) {
            return 'pro';
        }

        return 'free';
    }

    private function buildFeeTier(string $tierName): FeeTier
    {
        /** @var array<string, array<string, mixed>> $tiers */
        $tiers = config('pricing.tiers', []);

        if (! isset($tiers[$tierName])) {
            throw new FeeResolverException(
                sprintf('Fee tier "%s" not found in config/pricing.php.', $tierName)
            );
        }

        /** @var array<string, mixed> $config */
        $config = $tiers[$tierName];

        // txFlat: the flat per-transaction fee in USDC (asset denomination).
        // Null for fiat-only kinds — the controller passes null in that case.
        // Here we always build it from config; the controller omits it from
        // the feeBreakdown for fiat-only kinds but the FeeTier VO carries it.
        $txFlatAmount = isset($config['tx_flat_asset_amount'])
            ? (string) $config['tx_flat_asset_amount']
            : null;

        $txFlat = $txFlatAmount !== null
            ? Money::asset($txFlatAmount, 6, 'USDC')
            : null;

        return new FeeTier(
            txFlat: $txFlat,
            swapMarginBps: (int) ($config['swap_margin_bps'] ?? 0),
            rampMarginBps: (int) ($config['ramp_margin_bps'] ?? 0),
            tierName: $tierName,
        );
    }
}
