<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Aggregates;

use App\Domain\Monitoring\Events\AlertTriggeredEvent;
use App\Domain\Monitoring\Events\HealthCheckPerformedEvent;
use App\Domain\Monitoring\Events\MetricRecordedEvent;
use App\Domain\Monitoring\Events\MonitoringSessionStartedEvent;
use App\Domain\Monitoring\Events\ThresholdBreachedEvent;
use App\Domain\Monitoring\Events\TracingSpanRecordedEvent;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

final class MonitoringAggregate extends AggregateRoot
{
    protected string $sessionId;

    protected array $metrics = [];

    protected array $healthChecks = [];

    protected array $alerts = [];

    protected array $traces = [];

    protected bool $isActive = false;

    public function startSession(string $sessionId, array $metadata = []): self
    {
        if ($this->isActive) {
            return $this;
        }

        $this->recordThat(
            new MonitoringSessionStartedEvent(
                sessionId: $sessionId,
                startedAt: now(),
                metadata: $metadata
            )
        );

        return $this;
    }

    public function recordMetric(
        string $name,
        float $value,
        string $type = 'gauge',
        array $labels = [],
        ?string $unit = null
    ): self {
        $this->recordThat(
            new MetricRecordedEvent(
                sessionId: $this->sessionId,
                metricName: $name,
                value: $value,
                type: $type,
                labels: $labels,
                unit: $unit,
                timestamp: now()
            )
        );

        return $this;
    }

    public function performHealthCheck(
        string $component,
        bool $isHealthy,
        ?string $message = null,
        array $details = []
    ): self {
        $this->recordThat(
            new HealthCheckPerformedEvent(
                sessionId: $this->sessionId,
                component: $component,
                isHealthy: $isHealthy,
                message: $message,
                details: $details,
                checkedAt: now()
            )
        );

        return $this;
    }

    public function recordTracingSpan(
        string $traceId,
        string $spanId,
        string $operationName,
        float $duration,
        array $tags = [],
        ?string $parentSpanId = null
    ): self {
        $this->recordThat(
            new TracingSpanRecordedEvent(
                sessionId: $this->sessionId,
                traceId: $traceId,
                spanId: $spanId,
                parentSpanId: $parentSpanId,
                operationName: $operationName,
                duration: $duration,
                tags: $tags,
                timestamp: now()
            )
        );

        return $this;
    }

    public function checkThreshold(
        string $metricName,
        float $currentValue,
        float $threshold,
        string $comparison = '>'
    ): self {
        $breached = match ($comparison) {
            '>'     => $currentValue > $threshold,
            '>='    => $currentValue >= $threshold,
            '<'     => $currentValue < $threshold,
            '<='    => $currentValue <= $threshold,
            '=='    => $currentValue == $threshold,
            default => false,
        };

        if ($breached) {
            $this->recordThat(
                new ThresholdBreachedEvent(
                    sessionId: $this->sessionId,
                    metricName: $metricName,
                    currentValue: $currentValue,
                    threshold: $threshold,
                    comparison: $comparison,
                    breachedAt: now()
                )
            );
        }

        return $this;
    }

    public function triggerAlert(
        string $alertName,
        string $severity,
        string $message,
        array $context = []
    ): self {
        $this->recordThat(
            new AlertTriggeredEvent(
                sessionId: $this->sessionId,
                alertName: $alertName,
                severity: $severity,
                message: $message,
                context: $context,
                triggeredAt: now()
            )
        );

        return $this;
    }

    // Event Handlers
    protected function applyMonitoringSessionStartedEvent(MonitoringSessionStartedEvent $event): void
    {
        $this->sessionId = $event->sessionId;
        $this->isActive = true;
    }

    protected function applyMetricRecordedEvent(MetricRecordedEvent $event): void
    {
        $key = $event->metricName . ':' . implode(',', $event->labels);
        $this->metrics[$key] = [
            'name'      => $event->metricName,
            'value'     => $event->value,
            'type'      => $event->type,
            'labels'    => $event->labels,
            'unit'      => $event->unit,
            'timestamp' => $event->timestamp,
        ];
    }

    protected function applyHealthCheckPerformedEvent(HealthCheckPerformedEvent $event): void
    {
        $this->healthChecks[$event->component] = [
            'isHealthy' => $event->isHealthy,
            'message'   => $event->message,
            'details'   => $event->details,
            'checkedAt' => $event->checkedAt,
        ];
    }

    protected function applyTracingSpanRecordedEvent(TracingSpanRecordedEvent $event): void
    {
        $this->traces[$event->spanId] = [
            'traceId'       => $event->traceId,
            'parentSpanId'  => $event->parentSpanId,
            'operationName' => $event->operationName,
            'duration'      => $event->duration,
            'tags'          => $event->tags,
            'timestamp'     => $event->timestamp,
        ];
    }

    protected function applyThresholdBreachedEvent(ThresholdBreachedEvent $event): void
    {
        $this->alerts[] = [
            'type'       => 'threshold_breach',
            'metric'     => $event->metricName,
            'value'      => $event->currentValue,
            'threshold'  => $event->threshold,
            'comparison' => $event->comparison,
            'timestamp'  => $event->breachedAt,
        ];
    }

    protected function applyAlertTriggeredEvent(AlertTriggeredEvent $event): void
    {
        $this->alerts[] = [
            'name'      => $event->alertName,
            'severity'  => $event->severity,
            'message'   => $event->message,
            'context'   => $event->context,
            'timestamp' => $event->triggeredAt,
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getHealthStatus(): array
    {
        return $this->healthChecks;
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    public function getTraces(): array
    {
        return $this->traces;
    }
}
