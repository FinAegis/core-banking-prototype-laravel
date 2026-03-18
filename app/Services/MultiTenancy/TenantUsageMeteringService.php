<?php

declare(strict_types=1);

namespace App\Services\MultiTenancy;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for tracking and metering tenant resource usage.
 *
 * Handles:
 * - Real-time API call counting via Cache
 * - Storage usage tracking per tenant
 * - User count tracking per tenant
 * - Monthly usage aggregation and reporting
 */
class TenantUsageMeteringService
{
    /**
     * Cache key prefix for API call counters.
     */
    private const API_CALLS_PREFIX = 'tenant_metering:api_calls:';

    /**
     * Cache key prefix for storage usage.
     */
    private const STORAGE_PREFIX = 'tenant_metering:storage:';

    /**
     * Cache key prefix for user counts.
     */
    private const USER_COUNT_PREFIX = 'tenant_metering:users:';

    /**
     * Cache key prefix for monthly aggregation.
     */
    private const MONTHLY_PREFIX = 'tenant_metering:monthly:';

    /**
     * Default TTL for daily counters (25 hours to handle timezone overlap).
     */
    private const DAILY_TTL = 90000;

    /**
     * Default TTL for monthly aggregation (35 days).
     */
    private const MONTHLY_TTL = 3024000;

    /**
     * Record an API call for a tenant.
     */
    public function recordApiCall(Tenant $tenant, string $endpoint = ''): void
    {
        $tenantId = (string) $tenant->id;
        $today = Carbon::now()->format('Y-m-d');

        // Increment daily counter
        $dailyKey = self::API_CALLS_PREFIX . $tenantId . ':' . $today;
        $this->incrementCounter($dailyKey, self::DAILY_TTL);

        // Increment monthly counter
        $month = Carbon::now()->format('Y-m');
        $monthlyKey = self::API_CALLS_PREFIX . $tenantId . ':month:' . $month;
        $this->incrementCounter($monthlyKey, self::MONTHLY_TTL);

        // Track endpoint-specific usage if provided
        if ($endpoint !== '') {
            $endpointKey = self::API_CALLS_PREFIX . $tenantId . ':endpoint:' . $today . ':' . md5($endpoint);
            $this->incrementCounter($endpointKey, self::DAILY_TTL);
        }
    }

    /**
     * Get the number of API calls for a tenant on a specific date.
     */
    public function getApiCallCount(Tenant $tenant, ?string $date = null): int
    {
        $tenantId = (string) $tenant->id;
        $date = $date ?? Carbon::now()->format('Y-m-d');
        $key = self::API_CALLS_PREFIX . $tenantId . ':' . $date;

        return (int) Cache::get($key, 0);
    }

    /**
     * Get the number of API calls for a tenant in a specific month.
     */
    public function getMonthlyApiCallCount(Tenant $tenant, ?string $month = null): int
    {
        $tenantId = (string) $tenant->id;
        $month = $month ?? Carbon::now()->format('Y-m');
        $key = self::API_CALLS_PREFIX . $tenantId . ':month:' . $month;

        return (int) Cache::get($key, 0);
    }

    /**
     * Update the storage usage for a tenant (in megabytes).
     */
    public function updateStorageUsage(Tenant $tenant, float $storageMb): void
    {
        $tenantId = (string) $tenant->id;
        $key = self::STORAGE_PREFIX . $tenantId;

        Cache::put($key, $storageMb, self::MONTHLY_TTL);

        Log::debug('Tenant storage usage updated', [
            'tenant_id'  => $tenantId,
            'storage_mb' => $storageMb,
        ]);
    }

    /**
     * Get the current storage usage for a tenant (in megabytes).
     */
    public function getStorageUsage(Tenant $tenant): float
    {
        $tenantId = (string) $tenant->id;
        $key = self::STORAGE_PREFIX . $tenantId;

        return (float) Cache::get($key, 0.0);
    }

    /**
     * Update the user count for a tenant.
     */
    public function updateUserCount(Tenant $tenant, int $userCount): void
    {
        $tenantId = (string) $tenant->id;
        $key = self::USER_COUNT_PREFIX . $tenantId;

        Cache::put($key, $userCount, self::MONTHLY_TTL);
    }

    /**
     * Get the current user count for a tenant.
     */
    public function getUserCount(Tenant $tenant): int
    {
        $tenantId = (string) $tenant->id;
        $key = self::USER_COUNT_PREFIX . $tenantId;

        return (int) Cache::get($key, 0);
    }

    /**
     * Calculate the user count from the database for a tenant.
     */
    public function calculateUserCount(Tenant $tenant): int
    {
        if ($tenant->team_id === null) {
            return 0;
        }

        $count = DB::table('team_user')
            ->where('team_id', $tenant->team_id)
            ->count();

        // Include the team owner
        $ownerExists = DB::table('teams')
            ->where('id', $tenant->team_id)
            ->whereNotNull('user_id')
            ->exists();

        if ($ownerExists) {
            $count++;
        }

        // Store the calculated count
        $this->updateUserCount($tenant, $count);

        return $count;
    }

    /**
     * Get monthly aggregated usage for a tenant.
     *
     * @return array{api_calls: int, storage_mb: float, user_count: int, month: string}
     */
    public function getMonthlyUsage(Tenant $tenant, ?string $month = null): array
    {
        $month = $month ?? Carbon::now()->format('Y-m');
        $tenantId = (string) $tenant->id;

        // Check if we have a cached aggregation
        $cacheKey = self::MONTHLY_PREFIX . $tenantId . ':' . $month;

        /** @var array{api_calls: int, storage_mb: float, user_count: int, month: string}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $usage = [
            'api_calls'  => $this->getMonthlyApiCallCount($tenant, $month),
            'storage_mb' => $this->getStorageUsage($tenant),
            'user_count' => $this->getUserCount($tenant),
            'month'      => $month,
        ];

        // Cache the aggregation for 5 minutes
        Cache::put($cacheKey, $usage, 300);

        return $usage;
    }

    /**
     * Aggregate monthly usage and persist to long-term storage.
     *
     * Call this at the end of each month via scheduled task.
     *
     * @return array{api_calls: int, storage_mb: float, user_count: int, month: string}
     */
    public function aggregateMonthlyUsage(Tenant $tenant, ?string $month = null): array
    {
        $month = $month ?? Carbon::now()->format('Y-m');
        $tenantId = (string) $tenant->id;

        $usage = [
            'api_calls'  => $this->getMonthlyApiCallCount($tenant, $month),
            'storage_mb' => $this->getStorageUsage($tenant),
            'user_count' => $this->getUserCount($tenant),
            'month'      => $month,
        ];

        // Store aggregation in long-term cache
        $cacheKey = self::MONTHLY_PREFIX . $tenantId . ':' . $month;
        Cache::put($cacheKey, $usage, self::MONTHLY_TTL);

        Log::info('Tenant monthly usage aggregated', [
            'tenant_id' => $tenantId,
            'month'     => $month,
            'usage'     => $usage,
        ]);

        return $usage;
    }

    /**
     * Check if a tenant has exceeded its plan limits.
     *
     * @param array<string, mixed> $planLimits The plan limits to check against
     *
     * @return array{exceeded: bool, violations: array<string, array{current: int|float, limit: int|float}>}
     */
    public function checkPlanLimits(Tenant $tenant, array $planLimits): array
    {
        $violations = [];

        // Check API calls
        if (isset($planLimits['max_api_calls']) && (int) $planLimits['max_api_calls'] > 0) {
            $currentCalls = $this->getMonthlyApiCallCount($tenant);
            $maxCalls = (int) $planLimits['max_api_calls'];

            if ($currentCalls >= $maxCalls) {
                $violations['api_calls'] = [
                    'current' => $currentCalls,
                    'limit'   => $maxCalls,
                ];
            }
        }

        // Check storage
        if (isset($planLimits['max_storage_mb']) && (int) $planLimits['max_storage_mb'] > 0) {
            $currentStorage = $this->getStorageUsage($tenant);
            $maxStorage = (float) $planLimits['max_storage_mb'];

            if ($currentStorage >= $maxStorage) {
                $violations['storage'] = [
                    'current' => $currentStorage,
                    'limit'   => $maxStorage,
                ];
            }
        }

        // Check user count
        if (isset($planLimits['max_users']) && (int) $planLimits['max_users'] > 0) {
            $currentUsers = $this->getUserCount($tenant);
            $maxUsers = (int) $planLimits['max_users'];

            if ($currentUsers >= $maxUsers) {
                $violations['users'] = [
                    'current' => $currentUsers,
                    'limit'   => $maxUsers,
                ];
            }
        }

        return [
            'exceeded'   => count($violations) > 0,
            'violations' => $violations,
        ];
    }

    /**
     * Reset daily counters for a tenant (useful for testing).
     */
    public function resetDailyCounters(Tenant $tenant, ?string $date = null): void
    {
        $tenantId = (string) $tenant->id;
        $date = $date ?? Carbon::now()->format('Y-m-d');

        $dailyKey = self::API_CALLS_PREFIX . $tenantId . ':' . $date;
        Cache::forget($dailyKey);
    }

    /**
     * Increment a cache counter atomically.
     */
    protected function incrementCounter(string $key, int $ttl): int
    {
        if (Cache::has($key)) {
            /** @var int|false $newValue */
            $newValue = Cache::increment($key);

            return $newValue !== false ? $newValue : 1;
        }

        Cache::put($key, 1, $ttl);

        return 1;
    }
}
