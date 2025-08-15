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
        // Request metrics
        $this->setGauge(
            'http_requests_total',
            'Total number of HTTP requests',
            Cache::get('metrics:requests:total', 0)
        );

        // Response time metrics
        $this->observeHistogram(
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            Cache::get('metrics:requests:duration', 0),
            ['method', 'route', 'status'],
            [
                request()->method(),
                request()->route()?->getName() ?? 'unknown',
                '200', // Default status code for metrics
            ]
        );

        // Error rate
        $this->setGauge(
            'application_errors_total',
            'Total number of application errors',
            Cache::get('metrics:errors:total', 0)
        );
    }

    /**
     * Collect business metrics.
     */
    protected function collectBusinessMetrics(): void
    {
        // Active accounts
        $activeAccounts = DB::table('accounts')
            ->where('status', 'active')
            ->count();

        $this->setGauge(
            'business_active_accounts',
            'Number of active accounts',
            $activeAccounts
        );

        // Transaction volume
        $transactionVolume = DB::table('transactions')
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

        // Loan metrics
        $activeLoans = DB::table('loans')
            ->where('status', 'active')
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
        // Database connections
        $dbConnections = DB::connection()->select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;

        $this->setGauge(
            'database_connections_active',
            'Number of active database connections',
            (float) $dbConnections
        );

        // Queue size
        $queueSize = DB::table('jobs')->count();

        $this->setGauge(
            'queue_jobs_pending',
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
    }

    private function getMetricKey(string $name, array $labels): string
    {
        return $name . ':' . implode(':', $labels);
    }
}
