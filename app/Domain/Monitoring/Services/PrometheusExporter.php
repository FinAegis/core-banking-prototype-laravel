<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;

class PrometheusExporter
{
    private CollectorRegistry $registry;

    private array $counters = [];

    private array $gauges = [];

    private array $histograms = [];

    public function __construct()
    {
        Redis::setDefaultOptions([
            'host'     => config('database.redis.default.host', '127.0.0.1'),
            'port'     => (int) config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password'),
            'database' => (int) config('database.redis.default.database', 0),
        ]);

        $this->registry = new CollectorRegistry(new Redis());
    }

    /**
     * Record a counter metric (always increasing).
     */
    public function incrementCounter(
        string $name,
        string $help,
        array $labels = [],
        array $labelValues = [],
        float $value = 1
    ): void {
        $key = $this->getMetricKey($name, $labels);

        if (! isset($this->counters[$key])) {
            $this->counters[$key] = $this->registry->getOrRegisterCounter(
                'finaegis',
                $name,
                $help,
                $labels
            );
        }

        $this->counters[$key]->incBy($value, $labelValues);
    }

    /**
     * Record a gauge metric (can go up or down).
     */
    public function setGauge(
        string $name,
        string $help,
        float $value,
        array $labels = [],
        array $labelValues = []
    ): void {
        $key = $this->getMetricKey($name, $labels);

        if (! isset($this->gauges[$key])) {
            $this->gauges[$key] = $this->registry->getOrRegisterGauge(
                'finaegis',
                $name,
                $help,
                $labels
            );
        }

        $this->gauges[$key]->set($value, $labelValues);
    }

    /**
     * Record a histogram metric (for distributions).
     */
    public function observeHistogram(
        string $name,
        string $help,
        float $value,
        array $labels = [],
        array $labelValues = [],
        ?array $buckets = null
    ): void {
        $key = $this->getMetricKey($name, $labels);

        if (! isset($this->histograms[$key])) {
            $this->histograms[$key] = $this->registry->getOrRegisterHistogram(
                'finaegis',
                $name,
                $help,
                $labels,
                $buckets
            );
        }

        $this->histograms[$key]->observe($value, $labelValues);
    }

    /**
     * Export all metrics in Prometheus format.
     */
    public function export(): string
    {
        $this->collectApplicationMetrics();
        $this->collectBusinessMetrics();
        $this->collectInfrastructureMetrics();

        $renderer = new RenderTextFormat();

        return $renderer->render($this->registry->getMetricFamilySamples());
    }

    /**
     * Collect application-level metrics.
     */
    protected function collectApplicationMetrics(): void
    {
        // Uptime metrics
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $this->setGauge(
            'app_uptime_seconds',
            'Application uptime in seconds',
            microtime(true) - $startTime
        );

        // Request metrics - add labeled metrics for different methods
        $this->incrementCounter(
            'http_requests_total',
            'Total number of HTTP requests',
            ['method'],
            ['GET'],
            (float) Cache::get('metrics:http:requests:GET', 0)
        );

        $this->incrementCounter(
            'http_requests_total',
            'Total number of HTTP requests',
            ['method'],
            ['POST'],
            (float) Cache::get('metrics:http:requests:POST', 0)
        );

        // Response time metrics - create labeled entries
        $this->observeHistogram(
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            (float) Cache::get('metrics:http:requests:duration:GET', 0.001),
            ['method', 'route', 'status'],
            ['GET', 'test', '200']
        );

        $this->observeHistogram(
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            (float) Cache::get('metrics:http:requests:duration:POST', 0.002),
            ['method', 'route', 'status'],
            ['POST', 'test', '200']
        );

        // Error rate
        $this->setGauge(
            'application_errors_total',
            'Total number of application errors',
            (float) Cache::get('metrics:errors:total', 0)
        );

        // Memory usage
        $this->setGauge(
            'app_memory_usage_bytes',
            'Current memory usage in bytes',
            memory_get_usage(true)
        );

        // Cache metrics
        $cacheHits = (float) Cache::get('metrics:cache:hits', 0);
        $cacheMisses = (float) Cache::get('metrics:cache:misses', 0);

        $this->setGauge(
            'app_cache_hits_total',
            'Total number of cache hits',
            $cacheHits
        );

        $this->setGauge(
            'app_cache_misses_total',
            'Total number of cache misses',
            $cacheMisses
        );

        // Workflow metrics
        $workflowsCompleted = (float) Cache::get('metrics:workflows:completed', 0);
        $workflowsFailed = (float) Cache::get('metrics:workflows:failed', 0);

        $this->setGauge(
            'workflow_executions_total',
            'Total number of workflow executions',
            $workflowsCompleted + $workflowsFailed
        );

        // Event metrics
        $eventsProcessed = (float) Cache::get('metrics:events:total', 0);

        $this->setGauge(
            'events_processed_total',
            'Total number of events processed',
            $eventsProcessed
        );
    }

    /**
     * Collect business metrics.
     */
    protected function collectBusinessMetrics(): void
    {
        // User metrics
        $totalUsers = DB::table('users')->count();
        $this->setGauge(
            'app_users_total',
            'Total number of users',
            $totalUsers
        );

        // Total accounts (accounts table doesn't have status column)
        $activeAccounts = DB::table('accounts')
            ->count();

        $this->setGauge(
            'business_active_accounts',
            'Number of active accounts',
            $activeAccounts
        );

        // Transaction volume - use transaction_projections table
        $transactionVolume = DB::table('transaction_projections')
            ->where('created_at', '>=', now()->subHour())
            ->sum('amount');

        $this->setGauge(
            'business_transaction_volume_hourly',
            'Hourly transaction volume in cents',
            (float) ($transactionVolume ?? 0)
        );

        // Treasury metrics
        $treasuryBalance = DB::table('treasury_events')
            ->selectRaw('SUM(JSON_EXTRACT(event_properties, "$.amount")) as total')
            ->where('event_class', 'LIKE', '%CashAllocated%')
            ->value('total');

        $this->setGauge(
            'treasury_total_allocated',
            'Total treasury funds allocated',
            $treasuryBalance ?? 0
        );

        // Loan metrics (count all loans as loans table may not have status column)
        $activeLoans = DB::table('loans')
            ->count();

        $this->setGauge(
            'lending_active_loans',
            'Number of active loans',
            $activeLoans
        );

        // Exchange metrics
        $orderVolume = DB::table('orders')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $this->setGauge(
            'exchange_orders_hourly',
            'Number of orders placed in the last hour',
            $orderVolume
        );
    }

    /**
     * Collect infrastructure metrics.
     */
    protected function collectInfrastructureMetrics(): void
    {
        // Database connections (MySQL-specific, skip for SQLite)
        $dbConnections = 0;
        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                $result = DB::connection()->select('SHOW STATUS LIKE "Threads_connected"');
                $dbConnections = $result[0]->Value ?? 0;
            }
        } catch (\Exception $e) {
            // Ignore errors for unsupported databases
            $dbConnections = 0;
        }

        $this->setGauge(
            'database_connections_active',
            'Number of active database connections',
            (float) $dbConnections
        );

        // Also export with infra_ prefix for backward compatibility
        $this->setGauge(
            'infra_db_connections',
            'Number of active database connections',
            (float) $dbConnections
        );

        // Database queries (placeholder - would need actual query tracking)
        $this->setGauge(
            'infra_db_queries_total',
            'Total number of database queries',
            (float) Cache::get('metrics:db:queries:total', 0)
        );

        // Queue size
        $queueSize = DB::table('jobs')->count();

        $this->setGauge(
            'queue_jobs_pending',
            'Number of pending queue jobs',
            $queueSize
        );

        // Also export with infra_ prefix for backward compatibility
        $this->setGauge(
            'infra_queue_size',
            'Number of pending queue jobs',
            $queueSize
        );

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        $this->setGauge(
            'queue_jobs_failed_hourly',
            'Number of failed jobs in the last hour',
            $failedJobs
        );

        // Also export with infra_ prefix for backward compatibility
        $this->setGauge(
            'infra_queue_failed_total',
            'Total number of failed jobs',
            $failedJobs
        );

        // Cache hit rate
        $cacheHits = (float) Cache::get('metrics:cache:hits', 0);
        $cacheMisses = (float) Cache::get('metrics:cache:misses', 0);
        $total = $cacheHits + $cacheMisses;

        if ($total > 0) {
            $this->setGauge(
                'cache_hit_rate',
                'Cache hit rate percentage',
                ($cacheHits / $total) * 100
            );
        }

        // Event sourcing metrics
        $eventCount = DB::table('stored_events')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $this->setGauge(
            'event_sourcing_events_hourly',
            'Number of events stored in the last hour',
            $eventCount
        );

        // Redis memory (placeholder - would need actual Redis memory tracking)
        $this->setGauge(
            'infra_redis_memory_bytes',
            'Redis memory usage in bytes',
            (float) Cache::get('metrics:redis:memory', 0)
        );
    }

    private function getMetricKey(string $name, array $labels): string
    {
        return $name . ':' . implode(':', $labels);
    }
}
