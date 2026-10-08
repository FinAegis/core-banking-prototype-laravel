<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daily budget for gas-sponsored operations, shared by every entry point
 * that spends platform gas: the wallet send (POST
 * /api/v1/wallet/transactions/prepare) and generic sponsorship (REST POST
 * /api/v1/relayer/sponsor and the GraphQL sponsorTransaction mutation).
 *
 * All of them draw on the same counters (config/wallet.php `sponsorship`):
 *  - the optional USD budget (`daily_budget_usd`, see
 *    {@see SponsorshipCostTracker::isDailyBudgetExhausted()});
 *  - the global daily count (`global_daily_limit`);
 *  - the per-user daily count (`per_user_daily_limit`).
 *
 * Counts are cache counters keyed by the UTC date, so they reset at
 * 00:00 UTC. One unit is consumed per admitted request; abandoned requests
 * count too, which is deliberately conservative. A refused request leaves
 * both counts unchanged.
 */
class SponsorshipBudget
{
    public const DENIED_BUDGET_EXHAUSTED = 'budget_exhausted';

    public const DENIED_GLOBAL_LIMIT = 'global_limit';

    public const DENIED_USER_LIMIT = 'user_limit';

    public function __construct(
        private readonly SponsorshipCostTracker $costTracker,
    ) {
    }

    /**
     * Consume one sponsored operation for the user.
     *
     * @param  string  $source  entry point, recorded in the log context
     * @return self::DENIED_*|null null when the operation may proceed
     */
    public function consume(int|string $userId, string $source = 'wallet.send'): ?string
    {
        // Value-denominated budget: checked before the operation, charged
        // when the real fee is known (see SponsorshipCostTracker).
        if ($this->costTracker->isDailyBudgetExhausted()) {
            Log::critical('wallet.send daily sponsorship USD budget exhausted', [
                'source'          => $source,
                'spent_usd_micro' => $this->costTracker->todaysSpendUsdMicro(),
                'budget_usd'      => $this->costTracker->dailyBudgetUsd(),
            ]);

            return self::DENIED_BUDGET_EXHAUSTED;
        }

        $perUserLimit = (int) config('wallet.sponsorship.per_user_daily_limit', 30);
        $globalLimit = (int) config('wallet.sponsorship.global_daily_limit', 5000);

        $expiry = now()->addDay();
        $perUserKey = self::perUserCountCacheKey($userId);
        $globalKey = SponsorshipCostTracker::globalSendCountCacheKey();

        // Cache::add + increment — never read-then-write (concurrency-safe).
        // The per-user count is checked first, so a request refused for the
        // per-user limit never uses a unit of the global count.
        Cache::add($perUserKey, 0, $expiry);
        $userCount = (int) Cache::increment($perUserKey);

        if ($userCount > $perUserLimit) {
            Cache::decrement($perUserKey);

            Log::warning('wallet.send per-user daily limit reached', [
                'source'     => $source,
                'user_id'    => $userId,
                'user_count' => $userCount - 1,
                'user_limit' => $perUserLimit,
            ]);

            return self::DENIED_USER_LIMIT;
        }

        Cache::add($globalKey, 0, $expiry);
        $globalCount = (int) Cache::increment($globalKey);

        if ($globalCount > $globalLimit) {
            Cache::decrement($globalKey);
            Cache::decrement($perUserKey);

            Log::critical('wallet.send global daily sponsorship ceiling reached', [
                'source'       => $source,
                'global_count' => $globalCount - 1,
                'global_limit' => $globalLimit,
            ]);

            return self::DENIED_GLOBAL_LIMIT;
        }

        return null;
    }

    /**
     * Cache key for today's (UTC) per-user sponsored-operation count.
     */
    public static function perUserCountCacheKey(int|string $userId): string
    {
        return "wallet_send:count:user:{$userId}:" . now()->utc()->format('Y-m-d');
    }
}
