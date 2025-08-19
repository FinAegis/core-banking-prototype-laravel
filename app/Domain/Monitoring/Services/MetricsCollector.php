<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MetricsCollector
{
    private MonitoringAggregateRepository $repository;

    private string $sessionId;

    private ?MonitoringAggregate $aggregate = null;

    public function __construct(MonitoringAggregateRepository $repository)
    {
        $this->repository = $repository;
        $this->sessionId = $this->getCurrentSessionId();
    }

    /**
     * Record a counter metric (increments over time).
     */
    public function incrementCounter(
        string $name,
        float $value = 1.0,
        array $labels = [],
        ?string $description = null
    ): void {
        $this->recordMetric($name, $value, 'counter', $labels, null, $description);

        // Also store in cache for quick retrieval
        $cacheKey = $this->getCacheKey('counter', $name, $labels);
        Cache::increment($cacheKey, $value);
    }

    /**
     * Record a gauge metric (can go up and down).
     */
    public function setGauge(
        string $name,
        float $value,
        array $labels = [],
        ?string $unit = null,
        ?string $description = null
    ): void {
        $this->recordMetric($name, $value, 'gauge', $labels, $unit, $description);

        // Store in cache for quick retrieval
        $cacheKey = $this->getCacheKey('gauge', $name, $labels);
        Cache::put($cacheKey, $value, now()->addMinutes(5));
    }

    /**
     * Record a histogram metric (tracks distributions).
     */
    public function recordHistogram(
        string $name,
        float $value,
        array $buckets = [],
        array $labels = [],
        ?string $unit = null,
        ?string $description = null
    ): void {
        $this->recordMetric($name, $value, 'histogram', $labels, $unit, $description);

        // Store histogram data in cache
        $cacheKey = $this->getCacheKey('histogram', $name, $labels);
        $histogramData = Cache::get($cacheKey, [
            'count'   => 0,
            'sum'     => 0,
            'buckets' => array_fill_keys($buckets ?: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10], 0),
        ]);

        $histogramData['count']++;
        $histogramData['sum'] += $value;

        foreach ($histogramData['buckets'] as $bucket => &$count) {
            if ($value <= $bucket) {
                $count++;
            }
        }

        Cache::put($cacheKey, $histogramData, now()->addMinutes(5));
    }

    /**
     * Record a summary metric (similar to histogram but with quantiles).
     */
    public function recordSummary(
        string $name,
        float $value,
        array $quantiles = [0.5, 0.9, 0.95, 0.99],
        array $labels = [],
        ?string $unit = null,
        ?string $description = null
    ): void {
        $this->recordMetric($name, $value, 'summary', $labels, $unit, $description);

        // Store summary data in cache
        $cacheKey = $this->getCacheKey('summary', $name, $labels);
        $summaryData = Cache::get($cacheKey, [
            'count'  => 0,
            'sum'    => 0,
            'values' => [],
        ]);

        $summaryData['count']++;
        $summaryData['sum'] += $value;
        $summaryData['values'][] = $value;

        // Keep only last 1000 values for quantile calculation
        if (count($summaryData['values']) > 1000) {
            array_shift($summaryData['values']);
        }

        Cache::put($cacheKey, $summaryData, now()->addMinutes(5));
    }

    /**
     * Record application metrics.
     */
    public function recordApplicationMetrics(): void
    {
        // Memory usage
        $this->setGauge('app_memory_usage_bytes', memory_get_usage(true), [], 'bytes');
        $this->setGauge('app_memory_peak_bytes', memory_get_peak_usage(true), [], 'bytes');

        // Request metrics
        if (app()->runningInConsole()) {
            $this->incrementCounter('app_console_commands_total', 1, ['command' => $_SERVER['argv'][1] ?? 'unknown']);
        } else {
            $this->incrementCounter('app_http_requests_total', 1, [
                'method' => request()->method(),
                'status' => (string) response()->status(),
                'route'  => request()->route()?->getName() ?? 'unknown',
            ]);
        }

        // Database metrics
        $this->setGauge('app_db_connections_active', \DB::connection()->count());

        // Cache metrics
        $cacheHits = Cache::get('metrics:cache:hits', 0);
        $cacheMisses = Cache::get('metrics:cache:misses', 0);
        $this->setGauge('app_cache_hits_total', $cacheHits);
        $this->setGauge('app_cache_misses_total', $cacheMisses);

        // Queue metrics
        $this->setGauge('app_queue_jobs_pending', $this->getQueueSize());
    }

    /**
     * Record business metrics.
     */
    public function recordBusinessMetrics(): void
    {
        // User metrics
        $this->setGauge('app_users_total', \App\Models\User::count());
        $this->setGauge('app_users_active_daily', \App\Models\User::where('last_login_at', '>=', now()->subDay())->count());

        // Account metrics
        $this->setGauge('app_accounts_total', \App\Domain\Account\Models\Account::count());
        $this->setGauge('app_accounts_active', \App\Domain\Account\Models\Account::where('frozen', false)->count());

        // Transaction metrics
        $this->setGauge('app_transactions_total', \App\Domain\Account\Models\Transaction::count());
        $this->setGauge('app_transactions_daily', \App\Domain\Account\Models\Transaction::whereDate('created_at', today())->count());

        // Asset metrics
        $this->setGauge('app_assets_total', \App\Domain\Asset\Models\Asset::count());
        $this->setGauge('app_assets_active', \App\Domain\Asset\Models\Asset::where('is_active', true)->count());
    }

    /**
     * Get all metrics for export.
     */
    public function getAllMetrics(): array
    {
        $metrics = [];

        // Get metrics from cache
        $keys = Cache::get('metrics:keys', []);
        foreach ($keys as $key) {
            $value = Cache::get($key);
            if ($value !== null) {
                $parts = explode(':', $key);
                $type = $parts[1] ?? 'gauge';
                $name = $parts[2] ?? 'unknown';
                $labels = isset($parts[3]) ? $this->parseLabels($parts[3]) : [];

                $metrics[] = [
                    'name'      => $name,
                    'type'      => $type,
                    'value'     => $value,
                    'labels'    => $labels,
                    'timestamp' => now()->timestamp,
                ];
            }
        }

        return $metrics;
    }

    /**
     * Clear all metrics.
     */
    public function clearMetrics(): void
    {
        $keys = Cache::get('metrics:keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('metrics:keys');
    }

    private function recordMetric(
        string $name,
        float $value,
        string $type,
        array $labels,
        ?string $unit,
        ?string $description
    ): void {
        $aggregate = $this->getOrCreateAggregate();
        $aggregate->recordMetric($name, $value, $type, $labels, $unit);
        $this->repository->store($aggregate);

        // Track metric keys for export
        $cacheKey = $this->getCacheKey($type, $name, $labels);
        $keys = Cache::get('metrics:keys', []);
        if (! in_array($cacheKey, $keys)) {
            $keys[] = $cacheKey;
            Cache::put('metrics:keys', $keys, now()->addHours(24));
        }
    }

    private function getOrCreateAggregate(): MonitoringAggregate
    {
        if (! $this->aggregate) {
            $existingAggregate = $this->repository->findBySessionId($this->sessionId);

            if ($existingAggregate) {
                $this->aggregate = $existingAggregate;
            } else {
                $this->aggregate = MonitoringAggregate::fake(Str::uuid()->toString());
                $this->aggregate->startSession($this->sessionId, [
                    'type'       => 'metrics_collection',
                    'started_at' => now(),
                ]);
            }
        }

        return $this->aggregate;
    }

    private function getCurrentSessionId(): string
    {
        return Cache::get('monitoring:session:id', function () {
            $sessionId = Str::uuid()->toString();
            Cache::put('monitoring:session:id', $sessionId, now()->addHours(24));

            return $sessionId;
        });
    }

    private function getCacheKey(string $type, string $name, array $labels): string
    {
        $labelString = $this->labelsToString($labels);

        return "metrics:{$type}:{$name}" . ($labelString ? ":{$labelString}" : '');
    }

    private function labelsToString(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        ksort($labels);
        $pairs = [];
        foreach ($labels as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return implode(',', $pairs);
    }

    private function parseLabels(string $labelString): array
    {
        if (empty($labelString)) {
            return [];
        }

        $labels = [];
        $pairs = explode(',', $labelString);
        foreach ($pairs as $pair) {
            [$key, $value] = explode('=', $pair, 2);
            $labels[$key] = $value;
        }

        return $labels;
    }

    private function getQueueSize(): int
    {
        try {
            return \Queue::size();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
