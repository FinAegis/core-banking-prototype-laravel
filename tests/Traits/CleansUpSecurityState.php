<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

trait CleansUpSecurityState
{
    /**
     * Clear all security-related state before each test.
     */
    protected function clearSecurityState(): void
    {
        // Clear rate limiter
        RateLimiter::clear('login');
        RateLimiter::clear('api');
        RateLimiter::clear('forgot-password');
        RateLimiter::clear('password-reset');

        // Clear all rate limit keys
        $this->clearRateLimiterKeys();

        // Clear IP blocking cache
        $this->clearIpBlockingCache();

        // Clear blocked IPs table
        $this->clearBlockedIpsTable();

        // Clear any authentication cache
        Cache::flush();
    }

    /**
     * Clear all rate limiter keys from cache.
     */
    private function clearRateLimiterKeys(): void
    {
        // Get all possible IPs that might be used in tests
        $testIps = [
            '127.0.0.1',
            '::1',
            'localhost',
            '192.168.1.1',
            '10.0.0.1',
        ];

        // Clear rate limiter for each IP
        foreach ($testIps as $ip) {
            RateLimiter::clear('login:' . $ip);
            RateLimiter::clear('api:' . $ip);
            RateLimiter::clear('forgot-password:' . $ip);
            RateLimiter::clear('password-reset:' . $ip);

            // Clear with email combinations
            RateLimiter::clear('login:' . $ip . '|test@example.com');
            RateLimiter::clear('forgot-password:' . $ip . '|test@example.com');
        }

        // Clear any dynamic rate limiter keys
        Cache::forget('laravel_rate_limiter:login');
        Cache::forget('laravel_rate_limiter:api');
        Cache::forget('laravel_rate_limiter:forgot-password');
    }

    /**
     * Clear IP blocking related cache entries.
     */
    private function clearIpBlockingCache(): void
    {
        // Clear failed attempts and blocked IPs from cache
        $cacheKeys = [
            'failed_login_attempts:*',
            'blocked_ips:*',
            'ip_block:*',
            'failed_attempts:*',
        ];

        foreach ($cacheKeys as $pattern) {
            // Laravel doesn't support wildcard cache clearing, so we need to be specific
            $testIps = ['127.0.0.1', '::1', 'localhost', '192.168.1.1', '10.0.0.1'];
            foreach ($testIps as $ip) {
                Cache::forget(str_replace('*', $ip, $pattern));
            }
        }

        // Also clear any prefixed keys
        Cache::forget('failed_login_attempts:127.0.0.1');
        Cache::forget('blocked_ips:127.0.0.1');
    }

    /**
     * Clear blocked IPs database table.
     */
    private function clearBlockedIpsTable(): void
    {
        if (Schema::hasTable('blocked_ips')) {
            DB::table('blocked_ips')->truncate();
        }
    }

    /**
     * Set up clean security state for testing.
     */
    protected function setUpSecurityTesting(): void
    {
        $this->clearSecurityState();

        // Ensure clean configuration
        config([
            'auth.rate_limits.max_attempts'  => 5,
            'auth.rate_limits.decay_minutes' => 1,
            'sanctum.expiration'             => 60,
        ]);
    }
}
