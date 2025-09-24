<?php

declare(strict_types=1);

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Aggregates\PerformanceMetricsAggregate;
use App\Models\PerformanceAlert;
use App\Models\PerformanceMetric;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Log;

class PerformanceMonitoringService
{
    private array $thresholds = [
        'response_time'    => ['value' => 1000, 'comparison' => 'gt', 'severity' => 'warning'],
        'memory_usage'     => ['value' => 80, 'comparison' => 'gt', 'severity' => 'critical'],
        'cpu_usage'        => ['value' => 80, 'comparison' => 'gt', 'severity' => 'warning'],
        'error_rate'       => ['value' => 5, 'comparison' => 'gt', 'severity' => 'critical'],
        'database_queries' => ['value' => 100, 'comparison' => 'gt', 'severity' => 'warning'],
        'cache_hit_ratio'  => ['value' => 60, 'comparison' => 'lt', 'severity' => 'warning'],
    ];

    /**
     * Record a performance metric.
     */
    public function recordMetric(
        string $systemId,
        string $name,
        float $value,
        string $type = 'gauge',
        array $tags = []
    ): void {
        $aggregateUuid = "performance-{$systemId}";
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        // Set thresholds if this metric has them
        if (isset($this->thresholds[$name])) {
            $threshold = $this->thresholds[$name];
            $aggregate->setThreshold(
                $name,
                $threshold['value'],
                $threshold['comparison'],
                $threshold['severity']
            );
        }

        $aggregate->recordMetric(
            systemId: $systemId,
            name: $name,
            value: $value,
            type: $type,
            tags: $tags
        );

        $aggregate->persist();
    }

    /**
     * Record response time metric.
     */
    public function recordResponseTime(string $endpoint, float $milliseconds, array $tags = []): void
    {
        $this->recordMetric(
            'api',
            'response_time',
            $milliseconds,
            'timing',
            array_merge($tags, ['endpoint' => $endpoint])
        );

        // Update cache for real-time monitoring
        $this->updateRealtimeMetrics('response_time', $milliseconds);
    }

    /**
     * Record memory usage metric.
     */
    public function recordMemoryUsage(float $percentage, string $processType = 'web'): void
    {
        $this->recordMetric(
            'system',
            'memory_usage',
            $percentage,
            'gauge',
            ['process_type' => $processType]
        );

        // Trigger alert if critical
        if ($percentage > 90) {
            $this->triggerAlert(
                'system',
                'high_memory_usage',
                "Memory usage critically high: {$percentage}%",
                'critical'
            );
        }
    }

    /**
     * Record database query performance.
     */
    public function recordDatabaseQuery(string $query, float $duration, string $connection = 'default'): void
    {
        $this->recordMetric(
            'database',
            'query_duration',
            $duration,
            'timing',
            [
                'connection' => $connection,
                'query_type' => $this->getQueryType($query),
            ]
        );
    }

    /**
     * Record cache performance.
     */
    public function recordCachePerformance(bool $hit, string $key, float $duration): void
    {
        $this->recordMetric(
            'cache',
            'cache_' . ($hit ? 'hit' : 'miss'),
            1,
            'counter',
            ['key_pattern' => $this->getKeyPattern($key)]
        );

        $this->recordMetric(
            'cache',
            'cache_operation_duration',
            $duration,
            'timing',
            ['operation' => $hit ? 'get' : 'set']
        );
    }

    /**
     * Record transaction performance.
     */
    public function recordTransactionPerformance(
        string $transactionType,
        float $duration,
        bool $success,
        array $metadata = []
    ): void {
        $this->recordMetric(
            'transaction',
            "{$transactionType}_duration",
            $duration,
            'timing',
            array_merge($metadata, ['success' => $success ? 'true' : 'false'])
        );

        if (! $success) {
            $this->recordMetric(
                'transaction',
                "{$transactionType}_errors",
                1,
                'counter',
                $metadata
            );
        }
    }

    /**
     * Trigger a performance alert.
     */
    public function triggerAlert(
        string $systemId,
        string $alertType,
        string $message,
        string $severity = 'warning'
    ): void {
        $aggregateUuid = "performance-{$systemId}";
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        $aggregate->triggerAlert(
            systemId: $systemId,
            alertType: $alertType,
            message: $message,
            severity: $severity,
            context: [
                'timestamp' => now()->toDateTimeString(),
                'server'    => gethostname(),
            ]
        );

        $aggregate->persist();

        // Send notifications for critical alerts
        if ($severity === 'critical') {
            $this->notifyCriticalAlert($systemId, $alertType, $message);
        }
    }

    /**
     * Generate performance report.
     */
    public function generateReport(
        string $systemId,
        string $reportType,
        DateTimeImmutable $fromDate,
        DateTimeImmutable $toDate
    ): array {
        $metrics = PerformanceMetric::where('system_id', $systemId)
            ->whereBetween('recorded_at', [$fromDate, $toDate])
            ->get();

        $reportData = $this->analyzeMetrics($metrics, $reportType);

        // Store report
        $aggregateUuid = "performance-{$systemId}";
        $aggregate = PerformanceMetricsAggregate::retrieve($aggregateUuid);

        $aggregate->generateReport(
            systemId: $systemId,
            reportType: $reportType,
            fromDate: $fromDate,
            toDate: $toDate,
            reportData: $reportData
        );

        $aggregate->persist();

        return $reportData;
    }

    /**
     * Get real-time performance dashboard data.
     */
    public function getDashboardData(): array
    {
        return Cache::remember('performance_dashboard', 10, function () {
            $now = now();
            $hourAgo = $now->subHour();

            return [
                'current_metrics'  => $this->getCurrentMetrics(),
                'recent_alerts'    => $this->getRecentAlerts(10),
                'system_health'    => $this->calculateSystemHealth(),
                'trends'           => $this->calculateTrends($hourAgo, $now),
                'top_slow_queries' => $this->getTopSlowQueries(5),
                'resource_usage'   => $this->getResourceUsage(),
            ];
        });
    }

    /**
     * Get current metrics from cache.
     */
    private function getCurrentMetrics(): array
    {
        return [
            'avg_response_time'   => Cache::get('metric:response_time:avg', 0),
            'error_rate'          => Cache::get('metric:error_rate', 0),
            'requests_per_minute' => Cache::get('metric:rpm', 0),
            'active_users'        => Cache::get('metric:active_users', 0),
            'cache_hit_ratio'     => Cache::get('metric:cache_hit_ratio', 0),
        ];
    }

    /**
     * Get recent alerts.
     */
    private function getRecentAlerts(int $limit = 10): array
    {
        return PerformanceAlert::where('resolved_at', null)
            ->orderBy('triggered_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Calculate system health score.
     */
    private function calculateSystemHealth(): array
    {
        $healthScore = 100;
        $issues = [];

        // Check response time
        $avgResponseTime = Cache::get('metric:response_time:avg', 0);
        if ($avgResponseTime > 1000) {
            $healthScore -= 20;
            $issues[] = 'High response time';
        }

        // Check error rate
        $errorRate = Cache::get('metric:error_rate', 0);
        if ($errorRate > 5) {
            $healthScore -= 30;
            $issues[] = 'High error rate';
        }

        // Check memory usage
        $memoryUsage = Cache::get('metric:memory_usage', 0);
        if ($memoryUsage > 80) {
            $healthScore -= 15;
            $issues[] = 'High memory usage';
        }

        // Check cache performance
        $cacheHitRatio = Cache::get('metric:cache_hit_ratio', 100);
        if ($cacheHitRatio < 60) {
            $healthScore -= 10;
            $issues[] = 'Low cache hit ratio';
        }

        return [
            'score'  => max(0, $healthScore),
            'status' => $this->getHealthStatus($healthScore),
            'issues' => $issues,
        ];
    }

    /**
     * Calculate performance trends.
     */
    private function calculateTrends(DateTimeImmutable $fromDate, DateTimeImmutable $toDate): array
    {
        $metrics = PerformanceMetric::selectRaw('
            name,
            AVG(value) as avg_value,
            MAX(value) as max_value,
            MIN(value) as min_value,
            COUNT(*) as count
        ')
        ->whereBetween('recorded_at', [$fromDate, $toDate])
        ->groupBy('name')
        ->get();

        $trends = [];
        foreach ($metrics as $metric) {
            $trends[$metric->name] = [
                'average' => round($metric->avg_value, 2),
                'max'     => round($metric->max_value, 2),
                'min'     => round($metric->min_value, 2),
                'count'   => $metric->count,
            ];
        }

        return $trends;
    }

    /**
     * Get top slow database queries.
     */
    private function getTopSlowQueries(int $limit = 5): array
    {
        return PerformanceMetric::where('name', 'query_duration')
            ->where('recorded_at', '>=', now()->subHour())
            ->orderBy('value', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($metric) {
                return [
                    'duration'   => $metric->value,
                    'query_type' => $metric->tags['query_type'] ?? 'unknown',
                    'timestamp'  => $metric->recorded_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get current resource usage.
     */
    private function getResourceUsage(): array
    {
        return [
            'cpu' => [
                'usage' => Cache::get('metric:cpu_usage', 0),
                'cores' => Cache::get('metric:cpu_cores', 1),
            ],
            'memory' => [
                'usage' => Cache::get('metric:memory_usage', 0),
                'total' => Cache::get('metric:memory_total', 0),
                'free'  => Cache::get('metric:memory_free', 0),
            ],
            'disk' => [
                'usage' => Cache::get('metric:disk_usage', 0),
                'total' => Cache::get('metric:disk_total', 0),
                'free'  => Cache::get('metric:disk_free', 0),
            ],
            'connections' => [
                'database' => Cache::get('metric:db_connections', 0),
                'redis'    => Cache::get('metric:redis_connections', 0),
                'http'     => Cache::get('metric:http_connections', 0),
            ],
        ];
    }

    /**
     * Update real-time metrics in cache.
     */
    private function updateRealtimeMetrics(string $metric, float $value): void
    {
        $key = "metric:{$metric}:values";
        $values = Cache::get($key, []);

        // Keep last 100 values
        $values[] = $value;
        if (count($values) > 100) {
            array_shift($values);
        }

        Cache::put($key, $values, 300); // 5 minutes

        // Calculate and cache aggregates
        if (! empty($values)) {
            Cache::put("metric:{$metric}:avg", array_sum($values) / count($values), 300);
            Cache::put("metric:{$metric}:max", max($values), 300);
            Cache::put("metric:{$metric}:min", min($values), 300);
        }
    }

    /**
     * Analyze metrics for reporting.
     */
    private function analyzeMetrics($metrics, string $reportType): array
    {
        $analysis = [
            'summary' => [
                'total_metrics' => $metrics->count(),
                'period'        => $reportType,
                'generated_at'  => now()->toDateTimeString(),
            ],
            'metrics' => [],
        ];

        $grouped = $metrics->groupBy('name');

        foreach ($grouped as $name => $metricGroup) {
            $values = $metricGroup->pluck('value')->toArray();

            $analysis['metrics'][$name] = [
                'count'       => count($values),
                'average'     => array_sum($values) / count($values),
                'min'         => min($values),
                'max'         => max($values),
                'percentiles' => [
                    'p50' => $this->calculatePercentile($values, 50),
                    'p90' => $this->calculatePercentile($values, 90),
                    'p99' => $this->calculatePercentile($values, 99),
                ],
            ];
        }

        return $analysis;
    }

    /**
     * Calculate percentile from values.
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = (int) ceil(count($values) * ($percentile / 100)) - 1;

        return $values[$index] ?? 0;
    }

    /**
     * Get query type from SQL.
     */
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));

        if (str_starts_with($query, 'SELECT')) {
            return 'select';
        } elseif (str_starts_with($query, 'INSERT')) {
            return 'insert';
        } elseif (str_starts_with($query, 'UPDATE')) {
            return 'update';
        } elseif (str_starts_with($query, 'DELETE')) {
            return 'delete';
        }

        return 'other';
    }

    /**
     * Get cache key pattern.
     */
    private function getKeyPattern(string $key): string
    {
        // Extract pattern from cache key (e.g., user:123 -> user:*)
        return preg_replace('/:\d+/', ':*', $key);
    }

    /**
     * Get health status from score.
     */
    private function getHealthStatus(int $score): string
    {
        if ($score >= 90) {
            return 'healthy';
        } elseif ($score >= 70) {
            return 'degraded';
        } elseif ($score >= 50) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Notify about critical alert.
     */
    private function notifyCriticalAlert(string $systemId, string $alertType, string $message): void
    {
        // Here you would implement actual notification logic
        // For now, just log it
        Log::critical("Performance Alert: {$systemId} - {$alertType}", [
            'message'   => $message,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
