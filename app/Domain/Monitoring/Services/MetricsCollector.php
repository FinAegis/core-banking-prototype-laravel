<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Aggregates\MetricsAggregate;
use App\Domain\Monitoring\ValueObjects\AlertLevel;
use App\Domain\Monitoring\ValueObjects\MetricType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MetricsCollector
{
    private array $buffer = [];

    private int $bufferSize = 100;

    /**
     * Record HTTP request metrics.
     */
    public function recordHttpRequest(
        string $method,
        string $route,
        int $statusCode,
        float $duration
    ): void {
        $labels = [
            'method' => $method,
            'route'  => $route,
            'status' => (string) $statusCode,
        ];

        // Record to aggregate
        $aggregate = MetricsAggregate::retrieve('system-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::HISTOGRAM,
            name: 'http_request_duration',
            value: $duration,
            labels: $labels,
            unit: 'seconds'
        );

        $aggregate->persist();

        // Update cache for Prometheus
        $this->updateCache('http:requests:duration', $duration);
        $this->incrementCache("http:requests:status:{$statusCode}");
        $this->incrementCache('http:requests:total');
        $this->incrementCache("http:methods:{$method}");
        $this->updateCache("http:requests:duration:{$method}", $duration);

        // Update average duration
        $currentCount = Cache::get('metrics:http:requests:total', 0);
        $currentAverage = Cache::get('metrics:http:duration:average', 0);
        $newAverage = (($currentAverage * ($currentCount - 1)) + $duration) / $currentCount;
        Cache::put('metrics:http:duration:average', $newAverage, 60);

        // Track success/error counts
        if ($statusCode >= 200 && $statusCode < 400) {
            $this->incrementCache('http:requests:success');
        } else {
            $this->incrementCache('http:requests:errors');
        }
    }

    /**
     * Record business event metrics.
     */
    public function recordBusinessEvent(string $eventType, array $metadata = []): void
    {
        $aggregate = MetricsAggregate::retrieve('business-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::COUNTER,
            name: "business_event_{$eventType}",
            value: 1,
            labels: $metadata
        );

        $aggregate->persist();

        $this->incrementCache("events:{$eventType}:total");
        $this->incrementCache('events:total');
    }

    /**
     * Record domain aggregate metrics.
     */
    public function recordAggregateMetric(
        string $aggregateType,
        string $operation,
        float $duration,
        bool $success = true
    ): void {
        $labels = [
            'aggregate' => $aggregateType,
            'operation' => $operation,
            'success'   => $success ? 'true' : 'false',
        ];

        $aggregate = MetricsAggregate::retrieve('domain-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::HISTOGRAM,
            name: 'aggregate_operation_duration',
            value: $duration,
            labels: $labels,
            unit: 'seconds'
        );

        if (! $success) {
            $aggregate->recordMetric(
                metricId: Str::uuid()->toString(),
                type: MetricType::COUNTER,
                name: 'aggregate_operation_failures',
                value: 1,
                labels: ['aggregate' => $aggregateType, 'operation' => $operation]
            );
        }

        $aggregate->persist();

        // Update cache for tests
        $this->incrementCache("aggregates:{$aggregateType}:{$operation}:total");
        $this->updateCache("aggregates:{$aggregateType}:duration", $duration);
    }

    /**
     * Record workflow metrics.
     */
    public function recordWorkflowMetric(
        string $workflowType,
        string $status,
        float $duration,
        array $metadata = []
    ): void {
        $labels = array_merge([
            'workflow' => $workflowType,
            'status'   => $status,
        ], $metadata);

        $aggregate = MetricsAggregate::retrieve('workflow-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::HISTOGRAM,
            name: 'workflow_execution_duration',
            value: $duration,
            labels: $labels,
            unit: 'seconds'
        );

        if ($status === 'failed') {
            $aggregate->recordMetric(
                metricId: Str::uuid()->toString(),
                type: MetricType::COUNTER,
                name: 'workflow_failures',
                value: 1,
                labels: ['workflow' => $workflowType]
            );
        }

        $aggregate->persist();

        // Update cache for tests
        $this->incrementCache("workflows:{$workflowType}:{$status}");
        $this->updateCache("workflows:{$workflowType}:duration", $duration);
    }

    /**
     * Record cache metrics.
     */
    public function recordCacheMetric(string $operation, bool $hit): void
    {
        $aggregate = MetricsAggregate::retrieve('cache-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::COUNTER,
            name: $hit ? 'cache_hits' : 'cache_misses',
            value: 1,
            labels: ['operation' => $operation]
        );

        $aggregate->persist();

        // Only increment cache if not already existing
        $key = $hit ? 'cache:hits' : 'cache:misses';
        $this->incrementCache($key);
    }

    /**
     * Record queue metrics.
     */
    public function recordQueueMetric(
        string $queue,
        string $job,
        string $status,
        float $duration
    ): void {
        $labels = [
            'queue'  => $queue,
            'job'    => $job,
            'status' => $status,
        ];

        $aggregate = MetricsAggregate::retrieve('queue-metrics');

        $aggregate->recordMetric(
            metricId: Str::uuid()->toString(),
            type: MetricType::HISTOGRAM,
            name: 'queue_job_duration',
            value: $duration,
            labels: $labels,
            unit: 'seconds'
        );

        if ($status === 'failed') {
            $aggregate->recordMetric(
                metricId: Str::uuid()->toString(),
                type: MetricType::COUNTER,
                name: 'queue_job_failures',
                value: 1,
                labels: ['queue' => $queue, 'job' => $job]
            );
        }

        $aggregate->persist();

        // Update cache for tests
        $this->incrementCache("queue:{$status}");
        $this->updateCache('queue:duration', $duration);
    }

    /**
     * Batch record metrics for efficiency.
     */
    public function batchRecord(array $metrics): void
    {
        foreach ($metrics as $metric) {
            $this->buffer[] = $metric;

            // Also update cache for custom metrics
            if (isset($metric['name']) && isset($metric['value'])) {
                Cache::put("metrics:custom:{$metric['name']}", $metric['value'], 60);
            }

            if (count($this->buffer) >= $this->bufferSize) {
                $this->flush();
            }
        }
    }

    /**
     * Flush buffered metrics.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $aggregate = MetricsAggregate::retrieve('batch-metrics');

        foreach ($this->buffer as $metric) {
            $aggregate->recordMetric(
                metricId: $metric['id'] ?? Str::uuid()->toString(),
                type: MetricType::from($metric['type']),
                name: $metric['name'],
                value: $metric['value'],
                labels: $metric['labels'] ?? [],
                unit: $metric['unit'] ?? null
            );
        }

        $aggregate->persist();
        $this->buffer = [];
    }

    /**
     * Set alert thresholds.
     */
    public function setAlertThreshold(
        string $metricName,
        float $threshold,
        AlertLevel $alertLevel,
        string $operator = '>'
    ): void {
        $aggregate = MetricsAggregate::retrieve('system-metrics');
        $aggregate->setThreshold($metricName, $threshold, $alertLevel, $operator);
        $aggregate->persist();
    }

    private function incrementCache(string $key): void
    {
        Cache::increment("metrics:{$key}");
    }

    private function updateCache(string $key, $value): void
    {
        Cache::put("metrics:{$key}", $value, 60);
    }
}
