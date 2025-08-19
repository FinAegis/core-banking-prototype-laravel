<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class HealthChecker
{
    private MonitoringAggregateRepository $repository;

    private MetricsCollector $metricsCollector;

    public function __construct(
        MonitoringAggregateRepository $repository,
        MetricsCollector $metricsCollector
    ) {
        $this->repository = $repository;
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Perform a comprehensive health check.
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

        $overall = $this->calculateOverallHealth($checks);

        // Record health check in event store
        $this->recordHealthCheck($checks, $overall);

        // Update metrics
        $this->updateHealthMetrics($checks, $overall);

        return [
            'status'    => $overall['status'],
            'healthy'   => $overall['healthy'],
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
            'summary'   => $overall['summary'],
        ];
    }

    /**
     * Check database health.
     */
    public function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            DB::select('SELECT 1');

            $connectionCount = DB::connection()->count();
            $maxConnections = config('database.connections.mysql.max_connections', 100);

            $duration = (microtime(true) - $startTime) * 1000;

            $healthy = $duration < 100 && $connectionCount < $maxConnections * 0.8;

            return [
                'status'          => $healthy ? 'healthy' : 'degraded',
                'healthy'         => $healthy,
                'duration_ms'     => $duration,
                'connections'     => $connectionCount,
                'max_connections' => $maxConnections,
                'message'         => $healthy ? 'Database is responsive' : 'Database performance degraded',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unhealthy',
                'healthy' => false,
                'error'   => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache health.
     */
    public function checkCache(): array
    {
        $startTime = microtime(true);

        try {
            $testKey = 'health:check:' . Str::random(16);
            $testValue = Str::random(32);

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            $duration = (microtime(true) - $startTime) * 1000;

            $healthy = $retrieved === $testValue && $duration < 50;

            return [
                'status'      => $healthy ? 'healthy' : 'degraded',
                'healthy'     => $healthy,
                'duration_ms' => $duration,
                'message'     => $healthy ? 'Cache is operational' : 'Cache performance degraded',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unhealthy',
                'healthy' => false,
                'error'   => $e->getMessage(),
                'message' => 'Cache operation failed',
            ];
        }
    }

    /**
     * Check Redis health.
     */
    public function checkRedis(): array
    {
        $startTime = microtime(true);

        try {
            $redis = Redis::connection();
            $pong = $redis->ping();

            $info = $redis->info();
            $memoryUsed = $info['used_memory'] ?? 0;
            $memoryPeak = $info['used_memory_peak'] ?? 0;

            $duration = (microtime(true) - $startTime) * 1000;

            $healthy = $pong && $duration < 50;

            return [
                'status'      => $healthy ? 'healthy' : 'degraded',
                'healthy'     => $healthy,
                'duration_ms' => $duration,
                'memory_used' => $memoryUsed,
                'memory_peak' => $memoryPeak,
                'message'     => $healthy ? 'Redis is operational' : 'Redis performance degraded',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unhealthy',
                'healthy' => false,
                'error'   => $e->getMessage(),
                'message' => 'Redis connection failed',
            ];
        }
    }

    /**
     * Check queue health.
     */
    public function checkQueue(): array
    {
        try {
            $defaultQueue = config('queue.default');
            $queueSize = \Queue::size($defaultQueue);

            $failedJobs = DB::table('failed_jobs')->count();
            $maxQueueSize = config('monitoring.max_queue_size', 1000);
            $maxFailedJobs = config('monitoring.max_failed_jobs', 100);

            $healthy = $queueSize < $maxQueueSize && $failedJobs < $maxFailedJobs;

            return [
                'status'      => $healthy ? 'healthy' : 'degraded',
                'healthy'     => $healthy,
                'queue_size'  => $queueSize,
                'failed_jobs' => $failedJobs,
                'message'     => $healthy ? 'Queue is processing normally' : 'Queue backlog detected',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unhealthy',
                'healthy' => false,
                'error'   => $e->getMessage(),
                'message' => 'Queue check failed',
            ];
        }
    }

    /**
     * Check storage health.
     */
    public function checkStorage(): array
    {
        try {
            $path = storage_path('app');

            // Check if storage is writable
            $testFile = $path . '/health-check-' . Str::random(16) . '.tmp';
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            // Check disk space
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            $healthy = $content === 'test' && $usedPercentage < 90;

            return [
                'status'          => $healthy ? 'healthy' : 'degraded',
                'healthy'         => $healthy,
                'free_space_gb'   => round($freeSpace / 1073741824, 2),
                'total_space_gb'  => round($totalSpace / 1073741824, 2),
                'used_percentage' => round($usedPercentage, 2),
                'message'         => $healthy ? 'Storage is operational' : 'Storage space low',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unhealthy',
                'healthy' => false,
                'error'   => $e->getMessage(),
                'message' => 'Storage check failed',
            ];
        }
    }

    /**
     * Check if migrations are up to date.
     */
    public function checkMigrations(): array
    {
        try {
            $pending = \Artisan::call('migrate:status', ['--pending' => true]);
            $hasPending = $pending > 0;

            return [
                'status'             => ! $hasPending ? 'healthy' : 'degraded',
                'healthy'            => ! $hasPending,
                'pending_migrations' => $pending,
                'message'            => ! $hasPending ? 'All migrations applied' : 'Pending migrations detected',
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'unknown',
                'healthy' => true, // Don't fail health check on migration check failure
                'error'   => $e->getMessage(),
                'message' => 'Could not check migration status',
            ];
        }
    }

    /**
     * Calculate overall health status.
     */
    private function calculateOverallHealth(array $checks): array
    {
        $healthy = 0;
        $degraded = 0;
        $unhealthy = 0;

        foreach ($checks as $check) {
            switch ($check['status']) {
                case 'healthy':
                    $healthy++;
                    break;
                case 'degraded':
                    $degraded++;
                    break;
                case 'unhealthy':
                    $unhealthy++;
                    break;
            }
        }

        $total = count($checks);

        if ($unhealthy > 0) {
            $status = 'unhealthy';
        } elseif ($degraded > $total / 2) {
            $status = 'unhealthy';
        } elseif ($degraded > 0) {
            $status = 'degraded';
        } else {
            $status = 'healthy';
        }

        return [
            'status'  => $status,
            'healthy' => $status === 'healthy',
            'summary' => [
                'healthy'   => $healthy,
                'degraded'  => $degraded,
                'unhealthy' => $unhealthy,
                'total'     => $total,
            ],
        ];
    }

    /**
     * Record health check in event store.
     */
    private function recordHealthCheck(array $checks, array $overall): void
    {
        $aggregate = $this->getOrCreateAggregate();

        foreach ($checks as $component => $check) {
            $aggregate->performHealthCheck(
                $component,
                $check['healthy'],
                $check['message'] ?? null,
                $check
            );
        }

        // Record overall health
        $aggregate->performHealthCheck(
            'overall',
            $overall['healthy'],
            "System status: {$overall['status']}",
            $overall
        );

        $this->repository->store($aggregate);
    }

    /**
     * Update health metrics.
     */
    private function updateHealthMetrics(array $checks, array $overall): void
    {
        // Overall health metric (1 = healthy, 0 = unhealthy)
        $this->metricsCollector->setGauge(
            'app_health_status',
            $overall['healthy'] ? 1 : 0,
            [],
            null,
            'Overall application health status'
        );

        // Component health metrics
        foreach ($checks as $component => $check) {
            $this->metricsCollector->setGauge(
                'app_health_component_status',
                $check['healthy'] ? 1 : 0,
                ['component' => $component],
                null,
                "Health status of {$component}"
            );

            // Component-specific metrics
            if (isset($check['duration_ms'])) {
                $this->metricsCollector->setGauge(
                    'app_health_check_duration_ms',
                    $check['duration_ms'],
                    ['component' => $component],
                    'milliseconds',
                    "Health check duration for {$component}"
                );
            }
        }
    }

    private function getOrCreateAggregate(): MonitoringAggregate
    {
        $sessionId = Cache::get('monitoring:session:id', Str::uuid()->toString());

        $aggregate = $this->repository->findBySessionId($sessionId);

        if (! $aggregate) {
            $aggregate = MonitoringAggregate::fake(Str::uuid()->toString());
            $aggregate->startSession($sessionId, [
                'type'       => 'health_check',
                'started_at' => now(),
            ]);
        }

        return $aggregate;
    }
}
