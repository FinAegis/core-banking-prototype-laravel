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
 * 00:00 UTC. A refused request leaves both counts unchanged.
 *
 * When the units are taken differs by entry point:
 *  - generic sponsorship spends gas in the same request, so it takes both
 *    units at admission, after the request is validated ({@see consume()});
 *  - the wallet send is two-step. Prepare is cheap and spends nothing, so it
 *    only reserves a unit of the caller's own per-user count
 *    ({@see reserveUser()}, given back with {@see releaseUser()} when the
 *    prepare fails or is an idempotent replay) and checks the global count
 *    read-only. The shared global unit is taken at submit, right before the
 *    platform broadcasts ({@see consumeGlobal()}, given back with
 *    {@see releaseGlobal()} when the broadcast is refused). Abandoned
 *    prepares therefore only ever use up the caller's own allowance, so a
 *    pool of throwaway accounts cannot exhaust the platform-wide ceiling.
 */
class SponsorshipBudget
{
    public const DENIED_BUDGET_EXHAUSTED = 'budget_exhausted';

    public const DENIED_GLOBAL_LIMIT = 'global_limit';

    public const DENIED_USER_LIMIT = 'user_limit';

    /** Per-user key a {@see reserveUser()} unit was taken from, until released. */
    private ?string $reservedUserKey = null;

    /** Global key a {@see consumeGlobal()} unit was taken from, until released. */
    private ?string $consumedGlobalKey = null;

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
        // Value-denominated budget: checked before the operation and charged
        // afterwards — wallet sends with the real fee at confirmation,
        // generic sponsorship with the paymaster fee estimate at submission
        // (RecordGenericSponsorshipSpend). See SponsorshipCostTracker.
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
     * Wallet-send prepare: check the USD budget and the global count
     * (read-only) and reserve one unit of the user's per-user count.
     *
     * @return self::DENIED_*|null null when the prepare may proceed
     */
    public function reserveUser(int|string $userId, string $source = 'wallet.send'): ?string
    {
        if ($this->costTracker->isDailyBudgetExhausted()) {
            Log::critical('wallet.send daily sponsorship USD budget exhausted', [
                'source'          => $source,
                'spent_usd_micro' => $this->costTracker->todaysSpendUsdMicro(),
                'budget_usd'      => $this->costTracker->dailyBudgetUsd(),
            ]);

            return self::DENIED_BUDGET_EXHAUSTED;
        }

        $globalLimit = (int) config('wallet.sponsorship.global_daily_limit', 5000);

        // Read-only pre-check so users are not asked to sign a send that
        // submit would refuse; the authoritative check is consumeGlobal().
        if ((int) Cache::get(SponsorshipCostTracker::globalSendCountCacheKey(), 0) >= $globalLimit) {
            Log::critical('wallet.send global daily sponsorship ceiling reached', [
                'source'       => $source,
                'global_limit' => $globalLimit,
            ]);

            return self::DENIED_GLOBAL_LIMIT;
        }

        $perUserLimit = (int) config('wallet.sponsorship.per_user_daily_limit', 30);
        $perUserKey = self::perUserCountCacheKey($userId);

        Cache::add($perUserKey, 0, now()->addDay());
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

        $this->reservedUserKey = $perUserKey;

        return null;
    }

    /**
     * Give back the per-user unit this instance took with
     * {@see reserveUser()} for a prepare that failed or replayed an existing
     * intent. A no-op when nothing is reserved, so it can never release twice.
     */
    public function releaseUser(): void
    {
        $key = $this->reservedUserKey;
        $this->reservedUserKey = null;

        if ($key !== null) {
            self::giveBack($key);
        }
    }

    /**
     * Wallet-send submit: take one unit of the shared global count right
     * before the platform broadcasts (and pays for) the send.
     *
     * @return self::DENIED_*|null null when the submit may proceed
     */
    public function consumeGlobal(string $source = 'wallet.send'): ?string
    {
        $globalLimit = (int) config('wallet.sponsorship.global_daily_limit', 5000);
        $globalKey = SponsorshipCostTracker::globalSendCountCacheKey();

        Cache::add($globalKey, 0, now()->addDay());
        $globalCount = (int) Cache::increment($globalKey);

        if ($globalCount > $globalLimit) {
            Cache::decrement($globalKey);

            Log::critical('wallet.send global daily sponsorship ceiling reached', [
                'source'       => $source,
                'global_count' => $globalCount - 1,
                'global_limit' => $globalLimit,
            ]);

            return self::DENIED_GLOBAL_LIMIT;
        }

        $this->consumedGlobalKey = $globalKey;

        return null;
    }

    /**
     * Give back the global unit this instance took with
     * {@see consumeGlobal()} for a submit whose broadcast was refused
     * (nothing was spent). A no-op when nothing was taken.
     */
    public function releaseGlobal(): void
    {
        $key = $this->consumedGlobalKey;
        $this->consumedGlobalKey = null;

        if ($key !== null) {
            self::giveBack($key);
        }
    }

    /**
     * Return one unit to a counter. Decrement first and undo when it would go
     * below zero, instead of read-then-write, so concurrent releases cannot
     * push a counter negative. The key is the one the unit was taken from,
     * so a release just after 00:00 UTC never touches the new day's counter.
     */
    private static function giveBack(string $key): void
    {
        $after = Cache::decrement($key);

        if (is_int($after) && $after < 0) {
            Cache::increment($key);
        }
    }

    /**
     * Cache key for today's (UTC) per-user sponsored-operation count.
     */
    public static function perUserCountCacheKey(int|string $userId): string
    {
        return "wallet_send:count:user:{$userId}:" . now()->utc()->format('Y-m-d');
    }
}
