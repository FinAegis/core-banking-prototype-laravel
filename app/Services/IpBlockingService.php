<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IpBlockingService
{
    private const FAILED_ATTEMPTS_KEY = 'failed_login_attempts:';

    private const BLOCKED_IPS_KEY = 'blocked_ips:';

    private const MAX_ATTEMPTS = 10; // Max attempts before blocking

    private const ATTEMPT_WINDOW = 3600; // 1 hour window for attempts

    private const BLOCK_DURATION = 86400; // 24 hour block

    /**
     * Record a failed login attempt.
     */
    public function recordFailedAttempt(string $ip, string $email): void
    {
        $key = self::FAILED_ATTEMPTS_KEY . $ip;
        $attempts = Cache::get($key, []);

        $attempts[] = [
            'email'      => $email,
            'timestamp'  => now()->timestamp,
            'user_agent' => request()->userAgent(),
        ];

        // Keep only attempts within the window
        $cutoff = now()->subSeconds(self::ATTEMPT_WINDOW)->timestamp;
        $attempts = array_filter($attempts, fn ($attempt) => $attempt['timestamp'] > $cutoff);

        Cache::put($key, $attempts, self::ATTEMPT_WINDOW);

        // Check if should block
        if (count($attempts) >= self::MAX_ATTEMPTS) {
            $this->blockIp($ip, 'Exceeded maximum failed login attempts');
        }

        // Log the failed attempt
        Log::warning('Failed login attempt', [
            'ip'            => $ip,
            'email'         => $email,
            'attempt_count' => count($attempts),
            'user_agent'    => request()->userAgent(),
        ]);
    }

    /**
     * Block an IP address.
     */
    public function blockIp(string $ip, string $reason): void
    {
        // Store in cache for immediate blocking
        $key = self::BLOCKED_IPS_KEY . $ip;
        Cache::put($key, [
            'reason'     => $reason,
            'blocked_at' => now(),
            'expires_at' => now()->addSeconds(self::BLOCK_DURATION),
        ], self::BLOCK_DURATION);

        // Store in database for persistent blocking
        DB::table('blocked_ips')->updateOrInsert(
            ['ip_address' => $ip],
            [
                'reason'          => $reason,
                'failed_attempts' => $this->getFailedAttemptCount($ip),
                'blocked_at'      => now(),
                'expires_at'      => now()->addSeconds(self::BLOCK_DURATION),
                'updated_at'      => now(),
            ]
        );

        // Clear failed attempts
        Cache::forget(self::FAILED_ATTEMPTS_KEY . $ip);

        Log::alert('IP address blocked', [
            'ip'       => $ip,
            'reason'   => $reason,
            'duration' => self::BLOCK_DURATION . ' seconds',
        ]);
    }

    /**
     * Check if an IP is blocked.
     */
    public function isBlocked(string $ip): bool
    {
        // Check cache first
        $key = self::BLOCKED_IPS_KEY . $ip;
        if (Cache::has($key)) {
            $blockInfo = Cache::get($key);
            if ($blockInfo['expires_at']->isFuture()) {
                return true;
            }
            // Remove expired block
            Cache::forget($key);
        }

        // Check database
        $blocked = DB::table('blocked_ips')
            ->where('ip_address', $ip)
            ->where('expires_at', '>', now())
            ->first();

        if ($blocked) {
            // Re-cache for faster lookups
            Cache::put($key, [
                'reason'     => $blocked->reason,
                'blocked_at' => $blocked->blocked_at,
                'expires_at' => $blocked->expires_at,
            ], (int) now()->diffInSeconds($blocked->expires_at));

            return true;
        }

        return false;
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(string $ip): void
    {
        // Remove from cache
        Cache::forget(self::BLOCKED_IPS_KEY . $ip);
        Cache::forget(self::FAILED_ATTEMPTS_KEY . $ip);

        // Remove from database
        DB::table('blocked_ips')->where('ip_address', $ip)->delete();

        Log::info('IP address unblocked', ['ip' => $ip]);
    }

    /**
     * Get failed attempt count for an IP.
     */
    public function getFailedAttemptCount(string $ip): int
    {
        $key = self::FAILED_ATTEMPTS_KEY . $ip;
        $attempts = Cache::get($key, []);

        // Filter to current window
        $cutoff = now()->subSeconds(self::ATTEMPT_WINDOW)->timestamp;
        $attempts = array_filter($attempts, fn ($attempt) => $attempt['timestamp'] > $cutoff);

        return count($attempts);
    }

    /**
     * Get block information for an IP.
     */
    public function getBlockInfo(string $ip): ?array
    {
        if (! $this->isBlocked($ip)) {
            return null;
        }

        $key = self::BLOCKED_IPS_KEY . $ip;
        $cacheInfo = Cache::get($key);

        if ($cacheInfo) {
            return $cacheInfo;
        }

        $dbInfo = DB::table('blocked_ips')
            ->where('ip_address', $ip)
            ->where('expires_at', '>', now())
            ->first();

        return $dbInfo ? (array) $dbInfo : null;
    }

    /**
     * Clean up expired blocks.
     */
    public function cleanupExpiredBlocks(): int
    {
        return DB::table('blocked_ips')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
