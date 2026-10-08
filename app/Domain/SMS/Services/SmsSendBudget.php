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
 * never uses a unit of the global count.
 */
final class SmsSendBudget
{
    public const DENIED_USER_LIMIT = 'user_limit';

    public const DENIED_GLOBAL_LIMIT = 'global_limit';

    /**
     * Reserve one unpaid send for the user.
     *
     * @param  string  $source  entry point, recorded in the log context
     * @return self::DENIED_*|null null when the send may proceed
     */
    public function reserve(int|string $userId, string $source): ?string
    {
        $userLimit = max(1, (int) config('sms.rate_limit.per_user_per_day', 50));
        $globalLimit = max(0, (int) config('sms.global_daily_limit', 500));

        $expiry = now()->addDays(2);
        $userKey = self::userCountCacheKey($userId);
        $globalKey = self::globalCountCacheKey();

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
     */
    public function release(int|string $userId): void
    {
        Cache::decrement(self::userCountCacheKey($userId));
        Cache::decrement(self::globalCountCacheKey());
    }

    /**
     * Seconds until the counts reset (00:00 UTC).
     */
    public static function secondsUntilReset(): int
    {
        return max(1, (int) now()->utc()->diffInSeconds(now()->utc()->addDay()->startOfDay()));
    }

    /**
     * Cache key of today's (UTC) count of unpaid sends, all users together.
     */
    public static function globalCountCacheKey(): string
    {
        return 'sms:send:unpaid:global:' . now()->utc()->format('Y-m-d');
    }

    /**
     * Cache key of today's (UTC) count of unpaid sends for one user.
     */
    public static function userCountCacheKey(int|string $userId): string
    {
        return "sms:send:unpaid:user:{$userId}:" . now()->utc()->format('Y-m-d');
    }
}
