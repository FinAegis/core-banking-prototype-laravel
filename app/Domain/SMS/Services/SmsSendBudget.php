<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daily limits on unpaid SMS sends, shared by every send path that is not
 * covered by a settled payment: POST /api/v1/sms/send on the unpaid path
 * ({@see \App\Http\Middleware\SmsSendGateMiddleware}) and the MCP sms.send
 * tool ({@see \App\Domain\AI\MCP\Tools\SMS\SmsSendTool}).
 *
 *  - per user: `sms.rate_limit.per_user_per_day` (SMS_SEND_PER_USER_PER_DAY)
 *    sends per UTC day, across both paths;
 *  - all users together: `sms.global_daily_limit` (SMS_SEND_GLOBAL_PER_DAY)
 *    sends per UTC day; 0 allows no unpaid sends.
 *
 * reserve() takes one unit of both before the send and release() hands it
 * back when no SMS went out, so only sends that happen are counted. The
 * per-user count is checked first: a request refused for the per-user limit
 * never uses a unit of the global count. Callers pass the same UTC day
 * (today()) to both, so a send reserved before midnight and released after
 * it gives the unit back to the day it was taken from.
 */
final class SmsSendBudget
{
    public const DENIED_USER_LIMIT = 'user_limit';

    public const DENIED_GLOBAL_LIMIT = 'global_limit';

    /**
     * Reserve one unpaid send for the user.
     *
     * @param  string  $source  entry point, recorded in the log context
     * @param  string|null  $day  UTC day (Y-m-d) to count against; today when null
     * @return self::DENIED_*|null null when the send may proceed
     */
    public function reserve(int|string $userId, string $source, ?string $day = null): ?string
    {
        $userLimit = max(1, (int) config('sms.rate_limit.per_user_per_day', 50));
        $globalLimit = max(0, (int) config('sms.global_daily_limit', 500));

        $day ??= self::today();
        $expiry = now()->addDays(2);
        $userKey = self::userCountCacheKey($userId, $day);
        $globalKey = self::globalCountCacheKey($day);

        // Cache::add + increment — never read-then-write (concurrency-safe).
        Cache::add($userKey, 0, $expiry);
        $userCount = (int) Cache::increment($userKey);

        if ($userCount > $userLimit) {
            Cache::decrement($userKey);

            Log::warning('sms.send per-user daily limit reached', [
                'source'     => $source,
                'user_id'    => $userId,
                'user_count' => $userCount - 1,
                'user_limit' => $userLimit,
            ]);

            return self::DENIED_USER_LIMIT;
        }

        Cache::add($globalKey, 0, $expiry);
        $globalCount = (int) Cache::increment($globalKey);

        if ($globalCount > $globalLimit) {
            Cache::decrement($globalKey);
            Cache::decrement($userKey);

            Log::warning('sms.send global daily cap reached', [
                'source' => $source,
                'count'  => $globalCount - 1,
                'limit'  => $globalLimit,
            ]);

            return self::DENIED_GLOBAL_LIMIT;
        }

        return null;
    }

    /**
     * Hand back a unit taken by reserve() when no SMS was sent.
     *
     * Pass the day given to reserve(). Only existing counts are decremented,
     * so a release never creates a negative count without an expiry.
     */
    public function release(int|string $userId, ?string $day = null): void
    {
        $day ??= self::today();

        foreach ([self::userCountCacheKey($userId, $day), self::globalCountCacheKey($day)] as $key) {
            if (Cache::has($key)) {
                Cache::decrement($key);
            }
        }
    }

    /**
     * Today's UTC day (Y-m-d), the period the counts cover.
     */
    public static function today(): string
    {
        return now()->utc()->format('Y-m-d');
    }

    /**
     * Seconds until the counts reset (00:00 UTC).
     */
    public static function secondsUntilReset(): int
    {
        return max(1, (int) now()->utc()->diffInSeconds(now()->utc()->addDay()->startOfDay()));
    }

    /**
     * Cache key of a UTC day's count of unpaid sends, all users together
     * (today when no day is given).
     */
    public static function globalCountCacheKey(?string $day = null): string
    {
        return 'sms:send:unpaid:global:' . ($day ?? self::today());
    }

    /**
     * Cache key of a UTC day's count of unpaid sends for one user (today
     * when no day is given).
     */
    public static function userCountCacheKey(int|string $userId, ?string $day = null): string
    {
        return "sms:send:unpaid:user:{$userId}:" . ($day ?? self::today());
    }
}
