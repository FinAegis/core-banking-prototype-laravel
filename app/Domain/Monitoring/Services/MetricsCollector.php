<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use Illuminate\Support\Facades\Cache;

class MetricsCollector
{
    /**
     * Record an HTTP request metric.
     */
    public function recordHttpRequest(string $method, string $path, int $statusCode, float $duration): void
    {
        $this->increment('metrics:http:requests:total');
        $this->increment("metrics:http:requests:status:{$statusCode}");
        $this->increment("metrics:http:methods:{$method}");
        $this->updateAverage('metrics:http:duration:average', $duration);

        // Track success/error counts
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->increment('metrics:http:requests:success');
        } elseif ($statusCode >= 400) {
            $this->increment('metrics:http:requests:errors');
        }
    }

    /**
     * Record a business event metric.
     */
    public function recordBusinessEvent(string $eventName, array $metadata = []): void
    {
        $this->increment("metrics:events:{$eventName}:total");
        $this->increment('metrics:events:total');
    }

    /**
     * Record an aggregate metric.
     */
    public function recordAggregateMetric(string $aggregateType, string $action, float $duration): void
    {
        $this->increment("metrics:aggregates:{$aggregateType}:{$action}:total");
        $this->updateAverage("metrics:aggregates:{$aggregateType}:duration", $duration);
    }

    /**
     * Record a workflow metric.
     */
    public function recordWorkflowMetric(string $workflowName, string $status, float $duration): void
    {
        $this->increment("metrics:workflows:{$workflowName}:{$status}");

        if ($duration > 0) {
            Cache::put("metrics:workflows:{$workflowName}:duration", (string) $duration);
        }
    }

    /**
     * Record a cache metric.
     */
    public function recordCacheMetric(string $key, bool $hit): void
    {
        if ($hit) {
            $this->increment('metrics:cache:hits');
        } else {
            $this->increment('metrics:cache:misses');
        }
    }

    /**
     * Record a queue metric.
     */
    public function recordQueueMetric(string $queue, string $job, string $status, float $duration): void
    {
        $this->increment("metrics:queue:{$status}");
        $this->updateAverage('metrics:queue:duration', $duration);
    }

    /**
     * Batch record multiple metrics.
     */
    public function batchRecord(array $metrics): void
    {
        foreach ($metrics as $metric) {
            if (isset($metric['name']) && isset($metric['value'])) {
                Cache::put("metrics:custom:{$metric['name']}", $metric['value']);
            }
        }
    }

    /**
     * Increment a counter metric.
     */
    private function increment(string $key): void
    {
        $current = (int) Cache::get($key, 0);
        Cache::put($key, (string) ($current + 1));
    }

    /**
     * Update a running average.
     */
    private function updateAverage(string $key, float $value): void
    {
        $countKey = "{$key}:count";
        $sumKey = "{$key}:sum";

        $count = Cache::get($countKey, 0);
        $sum = Cache::get($sumKey, 0.0);

        $count++;
        $sum += $value;

        Cache::put($countKey, $count);
        Cache::put($sumKey, $sum);
        Cache::put($key, $sum / $count);
    }
}
