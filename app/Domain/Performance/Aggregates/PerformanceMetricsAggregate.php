<?php

declare(strict_types=1);

namespace App\Domain\Performance\Aggregates;

use App\Domain\Performance\Events\MetricRecorded;
use App\Domain\Performance\Events\PerformanceAlertTriggered;
use App\Domain\Performance\Events\PerformanceReportGenerated;
use App\Domain\Performance\Events\ThresholdExceeded;
use App\Domain\Performance\Repositories\PerformanceEventRepository;
use App\Domain\Performance\Repositories\PerformanceSnapshotRepository;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class PerformanceMetricsAggregate extends AggregateRoot
{
    private string $systemId = '';

    private array $metrics = [];

    private array $thresholds = [];

    private array $alerts = [];

    private array $statistics = [];

    /**
     * Get the stored event repository.
     */
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(PerformanceEventRepository::class);
    }

    /**
     * Get the snapshot repository.
     */
    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(PerformanceSnapshotRepository::class);
    }

    /**
     * Record a performance metric.
     */
    public function recordMetric(
        string $systemId,
        string $name,
        float $value,
        string $type,
        array $tags = [],
        ?DateTimeImmutable $timestamp = null
    ): self {
        $timestamp = $timestamp ?? new DateTimeImmutable();

        // Check thresholds
        if (isset($this->thresholds[$name])) {
            $threshold = $this->thresholds[$name];
            if ($this->exceedsThreshold($value, $threshold)) {
                $this->recordThat(new ThresholdExceeded(
                    systemId: $systemId,
                    metricName: $name,
                    value: $value,
                    threshold: $threshold['value'],
                    severity: $threshold['severity'] ?? 'warning'
                ));
            }
        }

        $this->recordThat(new MetricRecorded(
            systemId: $systemId,
            name: $name,
            value: $value,
            type: $type,
            tags: $tags,
            timestamp: $timestamp
        ));

        return $this;
    }

    /**
     * Set a performance threshold.
     */
    public function setThreshold(
        string $metricName,
        float $value,
        string $comparison = 'gt', // gt, lt, gte, lte, eq
        string $severity = 'warning'
    ): self {
        $this->thresholds[$metricName] = [
            'value'      => $value,
            'comparison' => $comparison,
            'severity'   => $severity,
        ];

        return $this;
    }

    /**
     * Trigger a performance alert.
     */
    public function triggerAlert(
        string $systemId,
        string $alertType,
        string $message,
        string $severity = 'warning',
        array $context = []
    ): self {
        $this->recordThat(new PerformanceAlertTriggered(
            systemId: $systemId,
            alertType: $alertType,
            message: $message,
            severity: $severity,
            context: $context,
            triggeredAt: new DateTimeImmutable()
        ));

        return $this;
    }

    /**
     * Generate a performance report.
     */
    public function generateReport(
        string $systemId,
        string $reportType,
        DateTimeImmutable $fromDate,
        DateTimeImmutable $toDate,
        array $reportData
    ): self {
        $this->recordThat(new PerformanceReportGenerated(
            systemId: $systemId,
            reportType: $reportType,
            fromDate: $fromDate,
            toDate: $toDate,
            reportData: $reportData,
            generatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    /**
     * Apply metric recorded event.
     */
    protected function applyMetricRecorded(MetricRecorded $event): void
    {
        $this->systemId = $event->systemId;

        if (! isset($this->metrics[$event->name])) {
            $this->metrics[$event->name] = [];
        }

        // Store last 100 values for each metric
        $this->metrics[$event->name][] = [
            'value'     => $event->value,
            'timestamp' => $event->timestamp,
            'tags'      => $event->tags,
        ];

        if (count($this->metrics[$event->name]) > 100) {
            array_shift($this->metrics[$event->name]);
        }

        // Update statistics
        $this->updateStatistics($event->name, $event->value);
    }

    /**
     * Apply threshold exceeded event.
     */
    protected function applyThresholdExceeded(ThresholdExceeded $event): void
    {
        $this->alerts[] = [
            'metric'    => $event->metricName,
            'value'     => $event->value,
            'threshold' => $event->threshold,
            'severity'  => $event->severity,
            'timestamp' => new DateTimeImmutable(),
        ];

        // Keep only last 50 alerts
        if (count($this->alerts) > 50) {
            array_shift($this->alerts);
        }
    }

    /**
     * Apply performance alert triggered event.
     */
    protected function applyPerformanceAlertTriggered(PerformanceAlertTriggered $event): void
    {
        $this->alerts[] = [
            'type'      => $event->alertType,
            'message'   => $event->message,
            'severity'  => $event->severity,
            'context'   => $event->context,
            'timestamp' => $event->triggeredAt,
        ];

        // Keep only last 50 alerts
        if (count($this->alerts) > 50) {
            array_shift($this->alerts);
        }
    }

    /**
     * Apply performance report generated event.
     */
    protected function applyPerformanceReportGenerated(PerformanceReportGenerated $event): void
    {
        // Reports are stored but not kept in aggregate state
        // They can be queried from projections
    }

    /**
     * Check if value exceeds threshold.
     */
    private function exceedsThreshold(float $value, array $threshold): bool
    {
        return match ($threshold['comparison']) {
            'gt'    => $value > $threshold['value'],
            'lt'    => $value < $threshold['value'],
            'gte'   => $value >= $threshold['value'],
            'lte'   => $value <= $threshold['value'],
            'eq'    => $value == $threshold['value'],
            default => false,
        };
    }

    /**
     * Update statistics for a metric.
     */
    private function updateStatistics(string $metricName, float $value): void
    {
        if (! isset($this->statistics[$metricName])) {
            $this->statistics[$metricName] = [
                'count' => 0,
                'sum'   => 0,
                'min'   => $value,
                'max'   => $value,
                'avg'   => $value,
            ];
        }

        $stats = &$this->statistics[$metricName];
        $stats['count']++;
        $stats['sum'] += $value;
        $stats['min'] = min($stats['min'], $value);
        $stats['max'] = max($stats['max'], $value);
        $stats['avg'] = $stats['sum'] / $stats['count'];
    }

    /**
     * Get current metrics.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get current alerts.
     */
    public function getAlerts(): array
    {
        return $this->alerts;
    }

    /**
     * Get statistics for metrics.
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Get metric average.
     */
    public function getMetricAverage(string $metricName): ?float
    {
        return $this->statistics[$metricName]['avg'] ?? null;
    }

    /**
     * Get metric percentile.
     */
    public function getMetricPercentile(string $metricName, int $percentile): ?float
    {
        if (! isset($this->metrics[$metricName]) || empty($this->metrics[$metricName])) {
            return null;
        }

        $values = array_column($this->metrics[$metricName], 'value');
        sort($values);

        $index = (int) ceil(count($values) * ($percentile / 100)) - 1;

        return $values[$index] ?? null;
    }
}
