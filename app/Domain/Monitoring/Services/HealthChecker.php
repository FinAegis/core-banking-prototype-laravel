<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthChecker
{
    private ?MetricsCollector $metricsCollector;

    public function __construct(?MetricsCollector $metricsCollector = null)
    {
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Perform comprehensive health check.
     */
    public function check(): array
    {
        $checks = [
            'database'   => $this->checkDatabase(),
            'cache'      => $this->checkCache(),
            'redis'      => $this->checkRedis(),
            'queue'      => $this->checkQueue(),
            'storage'    => $this->checkStorage(),
            'migrations' => $this->checkMigrations(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['healthy']);

        // Update metrics if collector is available
        if ($this->metricsCollector) {
            $this->updateHealthMetrics($checks, $healthy);
        }

        return [
            'status'    => $healthy ? 'healthy' : 'unhealthy',
            'healthy'   => $healthy,
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
                'name'        => 'database',
                'healthy'     => true,
                'message'     => 'Database connection successful',
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'database',
                'healthy' => false,
                'message' => 'Database connection failed',
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
                'name'        => 'cache',
                'healthy'     => $value === true,
                'message'     => 'Cache is operational',
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'cache',
                'healthy' => false,
                'message' => 'Cache check failed',
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
                'name'        => 'redis',
                'healthy'     => true,
                'message'     => 'Redis connection successful',
                'duration_ms' => round($duration, 2),
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'redis',
                'healthy' => false,
                'message' => 'Redis connection failed',
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
                'name'         => 'queue',
                'healthy'      => $healthy,
                'message'      => $healthy ? 'Queue is operating normally' : 'Queue has issues',
                'failed_jobs'  => $failedJobs,
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'queue',
                'healthy' => false,
                'message' => 'Queue check failed',
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

            $healthy = $usedPercent < 90;

            return [
                'name'         => 'storage',
                'healthy'      => $healthy,
                'message'      => $healthy ? 'Storage has sufficient space' : 'Storage space is low',
                'free_gb'      => round($free / 1073741824, 2),
                'total_gb'     => round($total / 1073741824, 2),
                'used_percent' => round($usedPercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'storage',
                'healthy' => false,
                'message' => 'Storage check failed',
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

            $healthy = $pending === 0;

            return [
                'name'               => 'migrations',
                'healthy'            => $healthy,
                'message'            => $healthy ? 'All migrations are up to date' : 'Pending migrations found',
                'pending_migrations' => $pending,
            ];
        } catch (\Exception $e) {
            return [
                'name'    => 'migrations',
                'healthy' => false,
                'message' => 'Migration check failed',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Update health metrics.
     */
    private function updateHealthMetrics(array $checks, bool $healthy): void
    {
        if (! $this->metricsCollector) {
            return;
        }

        // Overall health metric (1 = healthy, 0 = unhealthy)
        $this->metricsCollector->recordBusinessEvent('health_check', [
            'status'  => $healthy ? 'healthy' : 'unhealthy',
            'healthy' => $healthy,
        ]);

        // Component health metrics
        foreach ($checks as $component => $check) {
            $this->metricsCollector->recordBusinessEvent("health_check_{$component}", [
                'healthy' => $check['healthy'],
                'message' => $check['message'] ?? '',
            ]);
        }
    }
}
