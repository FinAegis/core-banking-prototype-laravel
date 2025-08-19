<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\Cache;

class PrometheusExporter
{
    private MetricsCollector $collector;

    private array $metrics = [];

    public function __construct(MetricsCollector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * Export metrics in Prometheus format.
     */
    public function export(): string
    {
        $this->collectAllMetrics();

        $output = [];

        // Add help and type information for each metric
        $processedMetrics = [];

        foreach ($this->metrics as $metric) {
            $name = $metric['name'];

            if (! isset($processedMetrics[$name])) {
                $processedMetrics[$name] = [
                    'type'    => $metric['type'],
                    'help'    => $metric['help'] ?? "Metric {$name}",
                    'samples' => [],
                ];
            }

            $processedMetrics[$name]['samples'][] = [
                'labels' => $metric['labels'],
                'value'  => $metric['value'],
            ];
        }

        // Format output
        foreach ($processedMetrics as $name => $data) {
            $output[] = "# HELP {$name} {$data['help']}";
            $output[] = "# TYPE {$name} {$data['type']}";

            foreach ($data['samples'] as $sample) {
                $labelString = $this->formatLabels($sample['labels']);
                $output[] = "{$name}{$labelString} {$sample['value']}";
            }

            $output[] = ''; // Empty line between metrics
        }

        return implode("\n", $output);
    }

    /**
     * Export metrics in JSON format.
     */
    public function exportJson(): array
    {
        $this->collectAllMetrics();

        return $this->metrics;
    }

    private function collectAllMetrics(): void
    {
        $this->metrics = [];

        // Collect application metrics
        $this->collectApplicationMetrics();

        // Collect business metrics
        $this->collectBusinessMetrics();

        // Collect infrastructure metrics
        $this->collectInfrastructureMetrics();

        // Collect custom metrics from cache
        $this->collectCachedMetrics();
    }

    private function collectApplicationMetrics(): void
    {
        // HTTP metrics
        $httpRequests = Cache::get('metrics:http:requests:total', 0);
        $this->addMetric('app_http_requests_total', $httpRequests, 'counter', [], 'Total HTTP requests');

        $httpDuration = Cache::get('metrics:http:duration:average', 0);
        $this->addMetric('app_http_duration_seconds', $httpDuration, 'gauge', [], 'Average HTTP request duration');

        // Memory metrics
        $this->addMetric('app_memory_usage_bytes', memory_get_usage(true), 'gauge', [], 'Current memory usage');
        $this->addMetric('app_memory_peak_bytes', memory_get_peak_usage(true), 'gauge', [], 'Peak memory usage');

        // Cache metrics
        $cacheHits = Cache::get('metrics:cache:hits', 0);
        $cacheMisses = Cache::get('metrics:cache:misses', 0);
        $this->addMetric('app_cache_hits_total', $cacheHits, 'counter', [], 'Total cache hits');
        $this->addMetric('app_cache_misses_total', $cacheMisses, 'counter', [], 'Total cache misses');

        if ($cacheHits + $cacheMisses > 0) {
            $hitRate = $cacheHits / ($cacheHits + $cacheMisses);
            $this->addMetric('app_cache_hit_rate', $hitRate, 'gauge', [], 'Cache hit rate');
        }
    }

    private function collectBusinessMetrics(): void
    {
        // User metrics
        $totalUsers = \App\Models\User::count();
        $this->addMetric('app_users_total', $totalUsers, 'gauge', [], 'Total number of users');

        $activeUsers = \App\Models\User::where('last_login_at', '>=', now()->subDay())->count();
        $this->addMetric('app_users_active_daily', $activeUsers, 'gauge', [], 'Daily active users');

        // Account metrics
        $totalAccounts = \App\Domain\Account\Models\Account::count();
        $this->addMetric('app_accounts_total', $totalAccounts, 'gauge', [], 'Total number of accounts');

        $activeAccounts = \App\Domain\Account\Models\Account::where('frozen', false)->count();
        $this->addMetric('app_accounts_active', $activeAccounts, 'gauge', [], 'Active accounts');

        $frozenAccounts = \App\Domain\Account\Models\Account::where('frozen', true)->count();
        $this->addMetric('app_accounts_frozen', $frozenAccounts, 'gauge', [], 'Frozen accounts');

        // Transaction metrics
        $totalTransactions = Cache::get('metrics:transactions:total', 0);
        $this->addMetric('app_transactions_total', $totalTransactions, 'counter', [], 'Total transactions');

        $dailyTransactions = Cache::get('metrics:transactions:daily', 0);
        $this->addMetric('app_transactions_daily', $dailyTransactions, 'gauge', [], 'Daily transactions');

        // Event sourcing metrics
        $eventsProcessed = Cache::get('metrics:events:processed', 0);
        $this->addMetric('app_events_processed_total', $eventsProcessed, 'counter', [], 'Total events processed');

        // Workflow metrics
        $workflowsStarted = Cache::get('metrics:workflows:started', 0);
        $workflowsCompleted = Cache::get('metrics:workflows:completed', 0);
        $workflowsFailed = Cache::get('metrics:workflows:failed', 0);

        $this->addMetric('app_workflows_started_total', $workflowsStarted, 'counter', [], 'Total workflows started');
        $this->addMetric('app_workflows_completed_total', $workflowsCompleted, 'counter', [], 'Total workflows completed');
        $this->addMetric('app_workflows_failed_total', $workflowsFailed, 'counter', [], 'Total workflows failed');
    }

    private function collectInfrastructureMetrics(): void
    {
        // Database metrics
        try {
            $dbConnections = \DB::connection()->count();
            $this->addMetric('app_db_connections_active', $dbConnections, 'gauge', [], 'Active database connections');
        } catch (\Exception $e) {
            $this->addMetric('app_db_connections_active', 0, 'gauge', [], 'Active database connections');
        }

        // Queue metrics
        try {
            $queueSize = \Queue::size();
            $this->addMetric('app_queue_jobs_pending', $queueSize, 'gauge', [], 'Pending queue jobs');
        } catch (\Exception $e) {
            $this->addMetric('app_queue_jobs_pending', 0, 'gauge', [], 'Pending queue jobs');
        }

        $queueProcessed = Cache::get('metrics:queue:processed', 0);
        $queueFailed = Cache::get('metrics:queue:failed', 0);

        $this->addMetric('app_queue_jobs_processed_total', $queueProcessed, 'counter', [], 'Total queue jobs processed');
        $this->addMetric('app_queue_jobs_failed_total', $queueFailed, 'counter', [], 'Total queue jobs failed');

        // Redis metrics
        $redisMemory = Cache::get('metrics:redis:memory', 0);
        $this->addMetric('app_redis_memory_bytes', $redisMemory, 'gauge', [], 'Redis memory usage');

        $redisConnections = Cache::get('metrics:redis:connections', 0);
        $this->addMetric('app_redis_connections', $redisConnections, 'gauge', [], 'Redis connections');
    }

    private function collectCachedMetrics(): void
    {
        $keys = Cache::get('metrics:keys', []);

        foreach ($keys as $key) {
            $value = Cache::get($key);
            if ($value === null) {
                continue;
            }

            $parts = explode(':', $key);
            if (count($parts) < 3) {
                continue;
            }

            $type = $parts[1];
            $name = $parts[2];
            $labelString = $parts[3] ?? '';

            // Skip metrics we've already collected
            if (str_starts_with($name, 'app_')) {
                continue;
            }

            $labels = $this->parseLabels($labelString);

            if ($type === 'histogram') {
                $this->addHistogramMetric($name, $value, $labels);
            } elseif ($type === 'summary') {
                $this->addSummaryMetric($name, $value, $labels);
            } else {
                $this->addMetric($name, $value, $type, $labels);
            }
        }
    }

    private function addMetric(
        string $name,
        float $value,
        string $type,
        array $labels = [],
        ?string $help = null
    ): void {
        $this->metrics[] = [
            'name'      => $name,
            'value'     => $value,
            'type'      => $type,
            'labels'    => $labels,
            'help'      => $help ?? "Metric {$name}",
            'timestamp' => now()->timestamp * 1000,
        ];
    }

    private function addHistogramMetric(string $name, array $data, array $labels = []): void
    {
        // Add bucket metrics
        foreach ($data['buckets'] ?? [] as $bucket => $count) {
            $bucketLabels = array_merge($labels, ['le' => (string) $bucket]);
            $this->addMetric("{$name}_bucket", $count, 'counter', $bucketLabels);
        }

        // Add +Inf bucket
        $infLabels = array_merge($labels, ['le' => '+Inf']);
        $this->addMetric("{$name}_bucket", $data['count'] ?? 0, 'counter', $infLabels);

        // Add sum and count
        $this->addMetric("{$name}_sum", $data['sum'] ?? 0, 'counter', $labels);
        $this->addMetric("{$name}_count", $data['count'] ?? 0, 'counter', $labels);
    }

    private function addSummaryMetric(string $name, array $data, array $labels = []): void
    {
        $values = $data['values'] ?? [];
        if (! empty($values)) {
            sort($values);
            $count = count($values);

            // Calculate quantiles
            $quantiles = [0.5, 0.9, 0.95, 0.99];
            foreach ($quantiles as $quantile) {
                $index = (int) ceil($quantile * $count) - 1;
                $quantileLabels = array_merge($labels, ['quantile' => (string) $quantile]);
                $this->addMetric($name, $values[$index] ?? 0, 'gauge', $quantileLabels);
            }
        }

        // Add sum and count
        $this->addMetric("{$name}_sum", $data['sum'] ?? 0, 'counter', $labels);
        $this->addMetric("{$name}_count", $data['count'] ?? 0, 'counter', $labels);
    }

    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $pairs = [];
        foreach ($labels as $key => $value) {
            $value = str_replace('"', '\\"', $value);
            $pairs[] = "{$key}=\"{$value}\"";
        }

        return '{' . implode(',', $pairs) . '}';
    }

    private function parseLabels(string $labelString): array
    {
        if (empty($labelString)) {
            return [];
        }

        $labels = [];
        $pairs = explode(',', $labelString);
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $labels[$parts[0]] = $parts[1];
            }
        }

        return $labels;
    }
}
