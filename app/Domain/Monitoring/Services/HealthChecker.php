<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthChecker
{
    /**
     * Perform comprehensive health check.
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'redis'    => $this->checkRedis(),
            'queue'    => $this->checkQueue(),
            'storage'  => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['healthy']);

        return [
            'status'    => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ];
    }

    /**
     * Check if application is ready to serve traffic.
     */
    public function checkReadiness(): array
    {
        $checks = [
            'database'   => $this->checkDatabase(),
            'cache'      => $this->checkCache(),
            'migrations' => $this->checkMigrations(),
        ];

        $ready = collect($checks)->every(fn ($check) => $check['healthy']);

        return [
            'ready'     => $ready,
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ];
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $duration = (microtime(true) - $start) * 1000;

            return [
                'healthy'     => true,
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    protected function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health:check:' . time();
            Cache::put($key, true, 1);
            $value = Cache::get($key);
            Cache::forget($key);
            $duration = (microtime(true) - $start) * 1000;

            return [
                'healthy'     => $value === true,
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity.
     */
    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $duration = (microtime(true) - $start) * 1000;

            return [
                'healthy'     => true,
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue health.
     */
    protected function checkQueue(): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            $pendingJobs = DB::table('jobs')->count();

            $healthy = $failedJobs < 10 && $pendingJobs < 1000;

            return [
                'healthy'      => $healthy,
                'failed_jobs'  => $failedJobs,
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage availability.
     */
    protected function checkStorage(): array
    {
        try {
            $path = storage_path('app');
            $free = disk_free_space($path);
            $total = disk_total_space($path);
            $usedPercent = (($total - $free) / $total) * 100;

            return [
                'healthy'      => $usedPercent < 90,
                'free_gb'      => round($free / 1073741824, 2),
                'total_gb'     => round($total / 1073741824, 2),
                'used_percent' => round($usedPercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if migrations are up to date.
     */
    protected function checkMigrations(): array
    {
        try {
            $pending = \Artisan::call('migrate:status', ['--pending' => true]);

            return [
                'healthy'            => $pending === 0,
                'pending_migrations' => $pending,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
