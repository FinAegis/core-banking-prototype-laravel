<?php

namespace App\Services;

use App\Models\User;
use DB;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DynamicRateLimitService
{
    /**
     * System load thresholds for dynamic rate limiting.
     */
    private const LOAD_THRESHOLDS = [
        'low'      => 0.3,      // < 30% load
        'medium'   => 0.6,   // 30-60% load
        'high'     => 0.8,     // 60-80% load
        'critical' => 1.0, // > 80% load
    ];

    /**
     * Rate limit multipliers based on system load.
     */
    private const LOAD_MULTIPLIERS = [
        'low'      => 1.5,      // 150% of base limits
        'medium'   => 1.0,   // 100% of base limits
        'high'     => 0.7,     // 70% of base limits
        'critical' => 0.4, // 40% of base limits
    ];

    /**
     * User trust levels and their rate limit multipliers.
     */
    private const TRUST_MULTIPLIERS = [
        'new'      => 0.5,        // 50% of base limits for new users
        'basic'    => 1.0,      // 100% of base limits
        'verified' => 1.5,   // 150% of base limits
        'premium'  => 2.0,    // 200% of base limits
        'vip'      => 3.0,        // 300% of base limits
    ];

    /**
     * Get dynamic rate limit for a user and endpoint type.
     */
    public function getDynamicRateLimit(string $rateLimitType, ?int $userId = null): array
    {
        $baseConfig = $this->getBaseRateLimit($rateLimitType);

        // Apply system load adjustment
        $loadMultiplier = $this->getLoadMultiplier();

        // Apply user trust level adjustment
        $trustMultiplier = $this->getUserTrustMultiplier($userId);

        // Apply time-of-day adjustment
        $timeMultiplier = $this->getTimeOfDayMultiplier();

        // Calculate final multiplier
        $finalMultiplier = $loadMultiplier * $trustMultiplier * $timeMultiplier;

        // Apply multiplier to limits
        $adjustedConfig = $baseConfig;
        $adjustedConfig['limit'] = (int) ceil($baseConfig['limit'] * $finalMultiplier);
        $adjustedConfig['original_limit'] = $baseConfig['limit'];
        $adjustedConfig['multiplier'] = $finalMultiplier;
        $adjustedConfig['adjustments'] = [
            'load'  => $loadMultiplier,
            'trust' => $trustMultiplier,
            'time'  => $timeMultiplier,
        ];

        // Log dynamic adjustment if significant
        if (abs($finalMultiplier - 1.0) > 0.2) {
            Log::info(
                'Dynamic rate limit adjustment applied',
                [
                'rate_limit_type' => $rateLimitType,
                'user_id'         => $userId,
                'original_limit'  => $baseConfig['limit'],
                'adjusted_limit'  => $adjustedConfig['limit'],
                'multiplier'      => $finalMultiplier,
                'adjustments'     => $adjustedConfig['adjustments'],
                ]
            );
        }

        return $adjustedConfig;
    }

    /**
     * Get system load multiplier.
     */
    private function getLoadMultiplier(): float
    {
        $systemLoad = $this->getCurrentSystemLoad();

        foreach (self::LOAD_THRESHOLDS as $level => $threshold) {
            if ($systemLoad <= $threshold) {
                return self::LOAD_MULTIPLIERS[$level];
            }
        }

        return self::LOAD_MULTIPLIERS['critical'];
    }

    /**
     * Get current system load (0.0 to 1.0+).
     */
    private function getCurrentSystemLoad(): float
    {
        $cacheKey = 'system_load:current';

        return Cache::remember(
            $cacheKey,
            30,
            function () {
                // Combine multiple load indicators
                $cpuLoad = $this->getCpuLoad();
                $memoryLoad = $this->getMemoryLoad();
                $redisLoad = $this->getRedisLoad();
                $databaseLoad = $this->getDatabaseLoad();

                // Weighted average of different load types
                $systemLoad = ($cpuLoad * 0.3) + ($memoryLoad * 0.2) + ($redisLoad * 0.25) + ($databaseLoad * 0.25);

                // Cache system load metrics for monitoring
                Cache::put('system_metrics:cpu_load', $cpuLoad, 300);
                Cache::put('system_metrics:memory_load', $memoryLoad, 300);
                Cache::put('system_metrics:redis_load', $redisLoad, 300);
                Cache::put('system_metrics:database_load', $databaseLoad, 300);

                return min(1.5, $systemLoad); // Cap at 150%
            }
        );
    }

    /**
     * Get CPU load (simplified).
     */
    private function getCpuLoad(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpuCount = $this->getCpuCount();

            return $cpuCount > 0 ? $load[0] / $cpuCount : 0.5;
        }

        return 0.5; // Default moderate load
    }

    /**
     * Get memory load.
     */
    private function getMemoryLoad(): float
    {
        $memoryInfo = $this->getMemoryInfo();

        if ($memoryInfo['total'] > 0) {
            return $memoryInfo['used'] / $memoryInfo['total'];
        }

        return 0.5; // Default moderate load
    }

    /**
     * Get Redis load.
     */
    private function getRedisLoad(): float
    {
        try {
            $info = Redis::info('memory');
            $usedMemory = $info['used_memory'] ?? 0;
            $maxMemory = $info['maxmemory'] ?? 0;

            if ($maxMemory > 0) {
                return $usedMemory / $maxMemory;
            }

            // Fallback: check connected clients
            $clientInfo = Redis::info('clients');
            $connectedClients = $clientInfo['connected_clients'] ?? 1;

            // Assume high load if many clients connected
            return min(1.0, $connectedClients / 100);
        } catch (Exception $e) {
            Log::warning('Failed to get Redis load metrics', ['error' => $e->getMessage()]);

            return 0.5;
        }
    }

    /**
     * Get database load (simplified).
     */
    private function getDatabaseLoad(): float
    {
        try {
            // Count active connections (MySQL specific)
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $connections = $result[0]->Value ?? 10;

            // Assume high load with many connections
            return min(1.0, $connections / 50);
        } catch (Exception $e) {
            Log::warning('Failed to get database load metrics', ['error' => $e->getMessage()]);

            return 0.5;
        }
    }

    /**
     * Get user trust level multiplier.
     */
    private function getUserTrustMultiplier(?int $userId): float
    {
        if (! $userId) {
            return self::TRUST_MULTIPLIERS['new'];
        }

        $cacheKey = "user_trust_level:{$userId}";

        return Cache::remember(
            $cacheKey,
            3600,
            function () use ($userId) {
                $trustLevel = $this->calculateUserTrustLevel($userId);

                return self::TRUST_MULTIPLIERS[$trustLevel] ?? self::TRUST_MULTIPLIERS['basic'];
            }
        );
    }

    /**
     * Calculate user trust level based on account metrics.
     */
    private function calculateUserTrustLevel(int $userId): string
    {
        try {
            $user = User::find($userId);
            if (! $user) {
                return 'new';
            }

            $accountAge = $user->created_at->diffInDays(now());
            $transactionCount = $this->getUserTransactionCount($userId);
            $violationCount = $this->getUserViolationCount($userId);

            // Trust level calculation logic
            if ($violationCount > 5) {
                return 'new'; // Demote users with many violations
            }

            if ($accountAge >= 365 && $transactionCount >= 1000) {
                return 'vip';
            }

            if ($accountAge >= 180 && $transactionCount >= 500) {
                return 'premium';
            }

            if ($accountAge >= 90 && $transactionCount >= 100) {
                return 'verified';
            }

            if ($accountAge >= 30 && $transactionCount >= 10) {
                return 'basic';
            }

            return 'new';
        } catch (Exception $e) {
            Log::warning(
                'Failed to calculate user trust level',
                [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
                ]
            );

            return 'basic';
        }
    }

    /**
     * Get time-of-day multiplier.
     */
    private function getTimeOfDayMultiplier(): float
    {
        $hour = (int) now()->format('H');

        // Business hours (9 AM - 5 PM): higher limits
        if ($hour >= 9 && $hour <= 17) {
            return 1.2;
        }

        // Evening hours (6 PM - 10 PM): normal limits
        if ($hour >= 18 && $hour <= 22) {
            return 1.0;
        }

        // Night/early morning (11 PM - 8 AM): lower limits
        return 0.8;
    }

    /**
     * Get base rate limit configuration.
     */
    private function getBaseRateLimit(string $rateLimitType): array
    {
        // This would typically come from a configuration service
        $baseRateLimits = [
            'auth'        => ['limit' => 5, 'window' => 60],
            'transaction' => ['limit' => 30, 'window' => 60],
            'query'       => ['limit' => 100, 'window' => 60],
            'admin'       => ['limit' => 200, 'window' => 60],
            'public'      => ['limit' => 60, 'window' => 60],
        ];

        return $baseRateLimits[$rateLimitType] ?? $baseRateLimits['query'];
    }

    /**
     * Helper methods for system metrics.
     */
    private function getCpuCount(): int
    {
        return (int) (shell_exec('nproc') ?: 1);
    }

    private function getMemoryInfo(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        if (! $meminfo) {
            return ['total' => 0, 'used' => 0];
        }

        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatch);

        $total = ($totalMatch[1] ?? 0) * 1024; // Convert to bytes
        $available = ($availableMatch[1] ?? 0) * 1024;
        $used = $total - $available;

        return ['total' => $total, 'used' => $used];
    }

    private function getUserTransactionCount(int $userId): int
    {
        // This would query your transaction tables
        return 0; // Placeholder
    }

    private function getUserViolationCount(int $userId): int
    {
        $key = "user_violations:{$userId}";

        return Cache::get($key, 0);
    }

    /**
     * Record rate limit violation for user trust calculation.
     */
    public function recordViolation(int $userId, string $violationType): void
    {
        $key = "user_violations:{$userId}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, 86400 * 30); // 30 days

        Log::warning(
            'Rate limit violation recorded',
            [
            'user_id'          => $userId,
            'violation_type'   => $violationType,
            'total_violations' => $count + 1,
            ]
        );
    }

    /**
     * Get system load metrics for monitoring.
     */
    public function getSystemMetrics(): array
    {
        return [
            'cpu_load'      => Cache::get('system_metrics:cpu_load', 0),
            'memory_load'   => Cache::get('system_metrics:memory_load', 0),
            'redis_load'    => Cache::get('system_metrics:redis_load', 0),
            'database_load' => Cache::get('system_metrics:database_load', 0),
            'overall_load'  => $this->getCurrentSystemLoad(),
        ];
    }
}
