<?php

declare(strict_types=1);

namespace App\Domain\Performance\Aggregates;

use App\Domain\Performance\Events\MetricRecorded;
use App\Domain\Performance\Events\PerformanceAlertTriggered;
use App\Domain\Performance\Events\PerformanceReportGenerated;
use App\Domain\Performance\Events\ThresholdExceeded;
use App\Domain\Performance\ValueObjects\MetricType;
use App\Domain\Performance\ValueObjects\PerformanceThreshold;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class PerformanceMetrics extends AggregateRoot
{
    private string $metricId = '';

    private string $systemId = '';

    private array $metrics = [];

    private array $thresholds = [];

    private array $alerts = [];

    public static function createNew(string $metricId, string $systemId): self
    {
        $metrics = (new self())->loadUuid($metricId);

        // Record initial creation event if needed
        // Since we're not recording a creation event, just set properties
        $metrics->metricId = $metricId;
        $metrics->systemId = $systemId;

        return $metrics;
    }

    public function recordMetric(
        string $name,
        float $value,
        MetricType $type,
        array $tags = [],
        ?DateTimeImmutable $timestamp = null
    ): self {
        // Initialize properties if empty (for reconstituted aggregates)
        if (empty($this->metricId)) {
            $this->metricId = $this->uuid();
        }
        if (empty($this->systemId)) {
            $this->systemId = 'default';
        }

        $timestamp = $timestamp ?? new DateTimeImmutable();

        $this->recordThat(new MetricRecorded(
            metricId: $this->metricId,
            systemId: $this->systemId,
            name: $name,
            value: $value,
            type: $type->value,
            tags: $tags,
            timestamp: $timestamp
        ));

        // Check thresholds
        $this->checkThresholds($name, $value, $timestamp);

        return $this;
    }

    public function setThreshold(string $metricName, PerformanceThreshold $threshold): self
    {
        $this->thresholds[$metricName] = $threshold;

        return $this;
    }

    private function checkThresholds(string $name, float $value, DateTimeImmutable $timestamp): void
    {
        if (! isset($this->thresholds[$name])) {
            return;
        }

        $threshold = $this->thresholds[$name];

        if ($threshold->isExceeded($value)) {
            $this->recordThat(new ThresholdExceeded(
                metricId: $this->metricId,
                systemId: $this->systemId,
                metricName: $name,
                value: $value,
                threshold: $threshold->getValue(),
                severity: $threshold->getSeverity(),
                timestamp: $timestamp
            ));

            if ($threshold->shouldTriggerAlert()) {
                $this->triggerAlert($name, $value, $threshold, $timestamp);
            }
        }
    }

    private function triggerAlert(
        string $metricName,
        float $value,
        PerformanceThreshold $threshold,
        DateTimeImmutable $timestamp
    ): void {
        $this->recordThat(new PerformanceAlertTriggered(
            metricId: $this->metricId,
            systemId: $this->systemId,
            alertType: 'threshold_exceeded',
            metricName: $metricName,
            value: $value,
            threshold: $threshold->getValue(),
            severity: $threshold->getSeverity(),
            message: "Metric {$metricName} exceeded threshold: {$value} > {$threshold->getValue()}",
            timestamp: $timestamp
        ));
    }

    public function generateReport(string $reportType, DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        // Initialize properties if empty (for reconstituted aggregates)
        if (empty($this->metricId)) {
            $this->metricId = $this->uuid();
        }
        if (empty($this->systemId)) {
            $this->systemId = 'default';
        }

        $reportData = $this->aggregateMetrics($from, $to);

        $this->recordThat(new PerformanceReportGenerated(
            metricId: $this->metricId,
            systemId: $this->systemId,
            reportType: $reportType,
            reportData: $reportData,
            from: $from,
            to: $to,
            generatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    private function aggregateMetrics(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $aggregated = [];

        foreach ($this->metrics as $metric) {
            if ($metric['timestamp'] >= $from && $metric['timestamp'] <= $to) {
                $name = $metric['name'];

                if (! isset($aggregated[$name])) {
                    $aggregated[$name] = [
                        'count'  => 0,
                        'sum'    => 0,
                        'min'    => PHP_FLOAT_MAX,
                        'max'    => PHP_FLOAT_MIN,
                        'values' => [],
                    ];
                }

                $aggregated[$name]['count']++;
                $aggregated[$name]['sum'] += $metric['value'];
                $aggregated[$name]['min'] = min($aggregated[$name]['min'], $metric['value']);
                $aggregated[$name]['max'] = max($aggregated[$name]['max'], $metric['value']);
                $aggregated[$name]['values'][] = $metric['value'];
            }
        }

        // Calculate averages and percentiles
        foreach ($aggregated as $name => &$data) {
            $data['average'] = $data['sum'] / $data['count'];
            sort($data['values']);
            $data['p50'] = $this->percentile($data['values'], 0.5);
            $data['p95'] = $this->percentile($data['values'], 0.95);
            $data['p99'] = $this->percentile($data['values'], 0.99);
            unset($data['values']); // Remove raw values to save space
        }

        return $aggregated;
    }

    private function percentile(array $values, float $percentile): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $index = ($count - 1) * $percentile;
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($upper >= $count) {
            return $values[$count - 1];
        }

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    // Apply methods
    protected function applyMetricRecorded(MetricRecorded $event): void
    {
        $this->metricId = $event->metricId;
        $this->systemId = $event->systemId;

        $this->metrics[] = [
            'name'      => $event->name,
            'value'     => $event->value,
            'type'      => $event->type,
            'tags'      => $event->tags,
            'timestamp' => $event->timestamp,
        ];
    }

    protected function applyThresholdExceeded(ThresholdExceeded $event): void
    {
        $this->metricId = $event->metricId;
        $this->systemId = $event->systemId;

        // Track threshold violations
        $this->alerts[] = [
            'type'      => 'threshold_exceeded',
            'metric'    => $event->metricName,
            'value'     => $event->value,
            'threshold' => $event->threshold,
            'timestamp' => $event->timestamp,
        ];
    }

    protected function applyPerformanceAlertTriggered(PerformanceAlertTriggered $event): void
    {
        $this->metricId = $event->metricId;
        $this->systemId = $event->systemId;

        $this->alerts[] = [
            'type'      => $event->alertType,
            'metric'    => $event->metricName,
            'message'   => $event->message,
            'severity'  => $event->severity,
            'timestamp' => $event->timestamp,
        ];
    }

    protected function applyPerformanceReportGenerated(PerformanceReportGenerated $event): void
    {
        $this->metricId = $event->metricId;
        $this->systemId = $event->systemId;

        // Report generation is tracked but doesn't modify state
    }

    public function getMetricId(): string
    {
        return $this->metricId;
    }

    public function getSystemId(): string
    {
        return $this->systemId;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }
}
