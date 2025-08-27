<?php

declare(strict_types=1);

namespace App\Domain\Performance\Services;

use App\Domain\Performance\Aggregates\PerformanceMetrics;
use App\Domain\Performance\Models\PerformanceMetric;
use App\Domain\Performance\ValueObjects\MetricType;
use App\Domain\Performance\ValueObjects\PerformanceThreshold;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MetricsCollectorService
{
    private array $thresholds = [];

    private string $systemId;

    public function __construct()
    {
        $this->systemId = config('app.name', 'finaegis');
        $this->initializeDefaultThresholds();
    }

    /**
     * Record a performance metric.
     */
    public function recordMetric(
        string $name,
        float $value,
        MetricType $type,
        array $tags = []
    ): void {
        $metricId = $this->getOrCreateMetricId($name);

        DB::transaction(function () use ($metricId, $name, $value, $type, $tags) {
            // First, check if any events exist for this aggregate
            $eventCount = DB::table('stored_events')
                ->where('aggregate_uuid', $metricId)
                ->count();

            if ($eventCount === 0) {
                // No events exist, create a new aggregate
                $metrics = PerformanceMetrics::createNew($metricId, $this->systemId);
            } else {
                // Events exist, retrieve the aggregate
                $metrics = PerformanceMetrics::retrieve($metricId);
            }

            // Set threshold if exists
            if (isset($this->thresholds[$name])) {
                $metrics->setThreshold($name, $this->thresholds[$name]);
            }

            $metrics->recordMetric($name, $value, $type, $tags);
            $metrics->persist();

            // Store in database for quick access
            PerformanceMetric::create([
                'metric_id'   => $metricId,
                'system_id'   => $this->systemId,
                'name'        => $name,
                'value'       => $value,
                'type'        => $type->value,
                'tags'        => $tags,
                'recorded_at' => now(),
            ]);
        });

        // Update cache for real-time monitoring
        $this->updateMetricCache($name, $value, $type);
    }

    /**
     * Record response time metric.
     */
    public function recordResponseTime(string $endpoint, float $milliseconds, array $tags = []): void
    {
        $tags = array_merge($tags, ['endpoint' => $endpoint]);
        $this->recordMetric("response_time.{$endpoint}", $milliseconds, MetricType::LATENCY, $tags);
    }

    /**
     * Record throughput metric.
     */
    public function recordThroughput(string $operation, int $count, array $tags = []): void
    {
        $tags = array_merge($tags, ['operation' => $operation]);
        $this->recordMetric("throughput.{$operation}", (float) $count, MetricType::THROUGHPUT, $tags);
    }

    /**
     * Record error rate.
     */
    public function recordErrorRate(string $service, float $rate, array $tags = []): void
    {
        $tags = array_merge($tags, ['service' => $service]);
        $this->recordMetric("error_rate.{$service}", $rate, MetricType::ERROR_RATE, $tags);
    }

    /**
     * Record system resource usage.
     */
    public function recordSystemMetrics(): void
    {
        // CPU Usage
        $cpuUsage = $this->getCpuUsage();
        $this->recordMetric('system.cpu_usage', $cpuUsage, MetricType::CPU_USAGE);

        // Memory Usage
        $memoryUsage = $this->getMemoryUsage();
        $this->recordMetric('system.memory_usage', $memoryUsage, MetricType::MEMORY_USAGE);

        // Disk Usage
        $diskUsage = $this->getDiskUsage();
        $this->recordMetric('system.disk_usage', $diskUsage, MetricType::DISK_USAGE);

        // Database connections
        $dbConnections = DB::connection()->select('show status like "Threads_connected"')[0]->Value ?? 0;
        $this->recordMetric('database.connections', (float) $dbConnections, MetricType::GAUGE);
    }

    /**
     * Get current metrics summary.
     */
    public function getMetricsSummary(int $minutes = 5): array
    {
        $since = now()->subMinutes($minutes);

        $metrics = PerformanceMetric::where('recorded_at', '>=', $since)
            ->selectRaw('name, AVG(value) as avg_value, MIN(value) as min_value, MAX(value) as max_value, COUNT(*) as count')
            ->groupBy('name')
            ->get();

        $summary = [];
        foreach ($metrics as $metric) {
            $summary[$metric->name] = [
                'average' => round($metric->avg_value, 2),
                'min'     => round($metric->min_value, 2),
                'max'     => round($metric->max_value, 2),
                'count'   => $metric->count,
            ];
        }

        return $summary;
    }

    /**
     * Get performance KPIs.
     */
    public function getKPIs(): array
    {
        $cache = Cache::remember('performance_kpis', 60, function () {
            $last5Minutes = now()->subMinutes(5)->toDateTimeImmutable();

            return [
                'response_time'        => $this->getAverageResponseTime($last5Minutes),
                'throughput'           => $this->getThroughput($last5Minutes),
                'error_rate'           => $this->getErrorRate($last5Minutes),
                'uptime'               => $this->getUptime(),
                'cpu_usage'            => $this->getCurrentCpuUsage(),
                'memory_usage'         => $this->getCurrentMemoryUsage(),
                'active_users'         => $this->getActiveUsers(),
                'database_performance' => $this->getDatabasePerformance($last5Minutes),
            ];
        });

        return $cache;
    }

    /**
     * Set a custom threshold.
     */
    public function setThreshold(string $metricName, PerformanceThreshold $threshold): void
    {
        $this->thresholds[$metricName] = $threshold;
    }

    /**
     * Initialize default thresholds.
     */
    private function initializeDefaultThresholds(): void
    {
        // Response time thresholds
        $this->thresholds['response_time'] = new PerformanceThreshold(
            value: 1000, // 1 second
            operator: '>',
            severity: 'warning',
            triggerAlert: true
        );

        // Error rate thresholds
        $this->thresholds['error_rate'] = new PerformanceThreshold(
            value: 5, // 5%
            operator: '>',
            severity: 'critical',
            triggerAlert: true
        );

        // CPU usage thresholds
        $this->thresholds['system.cpu_usage'] = new PerformanceThreshold(
            value: 80, // 80%
            operator: '>',
            severity: 'warning',
            triggerAlert: true
        );

        // Memory usage thresholds
        $this->thresholds['system.memory_usage'] = new PerformanceThreshold(
            value: 90, // 90%
            operator: '>',
            severity: 'critical',
            triggerAlert: true
        );
    }

    /**
     * Get or create metric ID.
     */
    private function getOrCreateMetricId(string $name): string
    {
        return Cache::remember("metric_id.{$name}", 3600, function () {
            return Str::uuid()->toString();
        });
    }

    /**
     * Update metric cache for real-time monitoring.
     */
    private function updateMetricCache(string $name, float $value, MetricType $type): void
    {
        $key = "metric.current.{$name}";
        Cache::put($key, [
            'value'     => $value,
            'type'      => $type->value,
            'timestamp' => now(),
        ], 300); // Cache for 5 minutes
    }

    /**
     * Get CPU usage percentage.
     */
    private function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            $cores = (int) shell_exec('nproc');

            return min(100, ($load[0] / $cores) * 100);
        }

        return 0; // Default for non-Linux systems
    }

    /**
     * Get memory usage in bytes.
     */
    private function getMemoryUsage(): float
    {
        return memory_get_usage(true);
    }

    /**
     * Get disk usage in bytes.
     */
    private function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');

        return $total - $free;
    }

    /**
     * Get average response time.
     */
    private function getAverageResponseTime(DateTimeInterface $since): float
    {
        $avg = PerformanceMetric::where('recorded_at', '>=', $since)
            ->where('name', 'like', 'response_time.%')
            ->avg('value');

        return round((float) ($avg ?? 0), 2);
    }

    /**
     * Get throughput.
     */
    private function getThroughput(DateTimeInterface $since): float
    {
        $sum = PerformanceMetric::where('recorded_at', '>=', $since)
            ->where('name', 'like', 'throughput.%')
            ->sum('value');

        $minutes = $since->diff(new DateTimeImmutable())->i + 1;

        return round((float) (($sum / $minutes)) * 60, 2); // Convert to per hour
    }

    /**
     * Get error rate.
     */
    private function getErrorRate(DateTimeInterface $since): float
    {
        $avg = PerformanceMetric::where('recorded_at', '>=', $since)
            ->where('name', 'like', 'error_rate.%')
            ->avg('value');

        return round((float) ($avg ?? 0), 2);
    }

    /**
     * Get uptime percentage.
     */
    private function getUptime(): float
    {
        // Simple uptime based on application start time
        $uptimeSeconds = time() - $_SERVER['REQUEST_TIME'];
        $totalSeconds = 86400; // 24 hours

        return min(100, ($uptimeSeconds / $totalSeconds) * 100);
    }

    /**
     * Get current CPU usage from cache.
     */
    private function getCurrentCpuUsage(): float
    {
        $cached = Cache::get('metric.current.system.cpu_usage');

        return $cached['value'] ?? 0;
    }

    /**
     * Get current memory usage from cache.
     */
    private function getCurrentMemoryUsage(): float
    {
        $cached = Cache::get('metric.current.system.memory_usage');

        return $cached['value'] ?? 0;
    }

    /**
     * Get active users count.
     */
    private function getActiveUsers(): int
    {
        return Cache::remember('active_users_count', 60, function () {
            return DB::table('sessions')
                ->where('last_activity', '>=', now()->subMinutes(30)->timestamp)
                ->count();
        });
    }

    /**
     * Get database performance metrics.
     */
    private function getDatabasePerformance(DateTimeInterface $since): array
    {
        $queries = DB::table('performance_metrics')
            ->where('recorded_at', '>=', $since)
            ->where('name', 'like', 'database.%')
            ->selectRaw('AVG(value) as avg_value, MAX(value) as max_value')
            ->first();

        return [
            'avg_query_time'   => round((float) ($queries->avg_value ?? 0), 2),
            'max_query_time'   => round((float) ($queries->max_value ?? 0), 2),
            'connection_count' => DB::connection()->select('show status like "Threads_connected"')[0]->Value ?? 0,
        ];
    }
}
