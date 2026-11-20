<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daily limits on unpaid SMS sends, shared by every send path that is not
 * covered by a settled payment: POST /api/v1/sms/send on the unpaid path
 * ({@see \App\Http\Middleware\SmsSendGateMiddleware}) and the MCP sms.send
 * tool ({@see \App\Domain\AI\MCP\Tools\SMS\SmsSendTool}). The limits count
 * billed SMS parts ({@see self::billedParts()}), not requests; an MCP send is
 * always one part.
 *
 *  - per user: `sms.rate_limit.per_user_per_day` (SMS_SEND_PER_USER_PER_DAY)
 *    parts per UTC day, across both paths;
 *  - all users together: `sms.global_daily_limit` (SMS_SEND_GLOBAL_PER_DAY)
 *    parts per UTC day; 0 allows no unpaid sends.
 *
 * Paid REST sends have their own ceiling per user (or per payer)
 * ({@see self::reservePaid()}): `sms.rate_limit.paid_per_user_per_day`
 * (SMS_PAID_SEND_PER_USER_PER_DAY) parts per UTC day.
 *
 * reserve() takes the message's parts from both before the send and
 * release() hands them back when no SMS went out, so only sends that happen
 * are counted. The per-user count is checked first: a request refused for
 * the per-user limit never uses the global count. Callers pass the same UTC
 * day (today()) to both, so a send reserved before midnight and released
 * after it gives the parts back to the day they were taken from.
 */
final class SmsSendBudget
{
    public const DENIED_USER_LIMIT = 'user_limit';

    public const DENIED_GLOBAL_LIMIT = 'global_limit';

    /** GSM 03.38 basic character set: one septet each. */
    private const GSM7_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** GSM 03.38 extension table: two septets each (escape + character). */
    private const GSM7_EXTENSION = "\f^{}\\[~]|€";

    /**
     * Reserve an unpaid send of $parts billed parts for the user.
     *
     * @param  string  $source  entry point, recorded in the log context
     * @param  string|null  $day  UTC day (Y-m-d) to count against; today when null
     * @return self::DENIED_*|null null when the send may proceed
     */
    public function reserve(int|string $userId, string $source, ?string $day = null, int $parts = 1): ?string
    {
        $parts = max(1, $parts);
        $userLimit = max(1, (int) config('sms.rate_limit.per_user_per_day', 50));
        $globalLimit = max(0, (int) config('sms.global_daily_limit', 500));

        $day ??= self::today();
        $expiry = now()->addDays(2);
        $userKey = self::userCountCacheKey($userId, $day);
        $globalKey = self::globalCountCacheKey($day);

        // Cache::add + increment — never read-then-write (concurrency-safe).
        Cache::add($userKey, 0, $expiry);
        $userCount = (int) Cache::increment($userKey, $parts);

        if ($userCount > $userLimit) {
            Cache::decrement($userKey, $parts);

            Log::warning('sms.send per-user daily limit reached', [
                'source'     => $source,
                'user_id'    => $userId,
                'user_count' => $userCount - $parts,
                'parts'      => $parts,
                'user_limit' => $userLimit,
            ]);

            return self::DENIED_USER_LIMIT;
        }

        Cache::add($globalKey, 0, $expiry);
        $globalCount = (int) Cache::increment($globalKey, $parts);

        if ($globalCount > $globalLimit) {
            Cache::decrement($globalKey, $parts);
            Cache::decrement($userKey, $parts);

            Log::warning('sms.send global daily cap reached', [
                'source' => $source,
                'count'  => $globalCount - $parts,
                'parts'  => $parts,
                'limit'  => $globalLimit,
            ]);

            return self::DENIED_GLOBAL_LIMIT;
        }

        return null;
    }

    /**
     * Hand back the parts taken by reserve() when no SMS was sent.
     *
     * Pass the day and parts given to reserve(). Only existing counts are
     * decremented, so a release never creates a negative count without an
     * expiry.
     */
    public function release(int|string $userId, ?string $day = null, int $parts = 1): void
    {
        $day ??= self::today();

        foreach ([self::userCountCacheKey($userId, $day), self::globalCountCacheKey($day)] as $key) {
            if (Cache::has($key)) {
                Cache::decrement($key, max(1, $parts));
            }
        }
    }

    /**
     * Reserve a paid send of $parts billed parts against the payer's daily
     * ceiling. A settled payment does not lift every limit: the ceiling
     * bounds what one payer can send per UTC day.
     *
     * @param  string  $payer  stable payer key (user, payer address or client IP)
     * @return bool false when the ceiling is reached (nothing reserved)
     */
    public function reservePaid(string $payer, int $parts, ?string $day = null): bool
    {
        $parts = max(1, $parts);
        $key = self::paidCountCacheKey($payer, $day ?? self::today());

        Cache::add($key, 0, now()->addDays(2));
        $count = (int) Cache::increment($key, $parts);

        if ($count > self::paidLimit()) {
            Cache::decrement($key, $parts);

            Log::warning('sms.send paid daily ceiling reached', [
                'payer' => hash('sha256', $payer),
                'count' => $count - $parts,
                'parts' => $parts,
                'limit' => self::paidLimit(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Hand back the parts taken by reservePaid() when no SMS was sent.
     */
    public function releasePaid(string $payer, int $parts, ?string $day = null): void
    {
        $key = self::paidCountCacheKey($payer, $day ?? self::today());

        if (Cache::has($key)) {
            Cache::decrement($key, max(1, $parts));
        }
    }

    /**
     * Whether a paid send of $parts would pass the payer's daily ceiling.
     * Read-only: checked before a payment is settled, so a payer at the
     * ceiling is refused without being charged.
     */
    public function paidCeilingReached(string $payer, int $parts, ?string $day = null): bool
    {
        $count = (int) Cache::get(self::paidCountCacheKey($payer, $day ?? self::today()), 0);

        return $count + max(1, $parts) > self::paidLimit();
    }

    /**
     * SMS parts the message is billed as: GSM-7 (160 septets in one part,
     * 153 per part when split; extension characters take two) or, when a
     * character is outside GSM-7, UCS-2 (70 UTF-16 units, 67 per part).
     */
    public static function billedParts(string $message): int
    {
        $septets = 0;

        foreach (mb_str_split($message) as $character) {
            if (str_contains(self::GSM7_BASIC, $character)) {
                $septets++;

                continue;
            }

            if (str_contains(self::GSM7_EXTENSION, $character)) {
                $septets += 2;

                continue;
            }

            $units = intdiv(strlen((string) mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')), 2);

            return $units <= 70 ? 1 : intdiv($units + 66, 67);
        }

        return $septets <= 160 ? 1 : intdiv($septets + 152, 153);
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
     * Cache key of a UTC day's count of paid parts for one payer.
     */
    public static function paidCountCacheKey(string $payer, ?string $day = null): string
    {
        return 'sms:send:paid:' . hash('sha256', $payer) . ':' . ($day ?? self::today());
    }

    private static function paidLimit(): int
    {
        return max(1, (int) config('sms.rate_limit.paid_per_user_per_day', 500));
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
