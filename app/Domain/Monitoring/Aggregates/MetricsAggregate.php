<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Aggregates;

use App\Domain\Monitoring\Events\AlertTriggered;
use App\Domain\Monitoring\Events\MetricRecorded;
use App\Domain\Monitoring\Events\ThresholdExceeded;
use App\Domain\Monitoring\ValueObjects\AlertLevel;
use App\Domain\Monitoring\ValueObjects\MetricType;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class MetricsAggregate extends AggregateRoot
{
    protected array $metrics = [];

    protected array $thresholds = [];

    protected array $activeAlerts = [];

    public function recordMetric(
        string $metricId,
        MetricType $type,
        string $name,
        float $value,
        array $labels = [],
        ?string $unit = null
    ): void {
        $this->recordThat(new MetricRecorded(
            aggregateId: $this->uuid(),
            metricId: $metricId,
            type: $type->value,
            name: $name,
            value: $value,
            labels: $labels,
            unit: $unit,
            recordedAt: now()->toDateTimeImmutable()
        ));

        // Store the metric
        $this->metrics[$name][] = [
            'id'        => $metricId,
            'type'      => $type,
            'value'     => $value,
            'labels'    => $labels,
            'unit'      => $unit,
            'timestamp' => now(),
        ];

        // Check thresholds
        $this->checkThresholds($name, $value);
    }

    public function setThreshold(
        string $metricName,
        float $threshold,
        AlertLevel $alertLevel,
        string $operator = '>'
    ): void {
        $this->thresholds[$metricName] = [
            'value'    => $threshold,
            'level'    => $alertLevel,
            'operator' => $operator,
        ];
    }

    protected function checkThresholds(string $metricName, float $value): void
    {
        if (! isset($this->thresholds[$metricName])) {
            return;
        }

        $threshold = $this->thresholds[$metricName];
        $operator = $threshold['operator'] ?? '>';

        $exceeds = match ($operator) {
            '>'     => $value > $threshold['value'],
            '<'     => $value < $threshold['value'],
            '>='    => $value >= $threshold['value'],
            '<='    => $value <= $threshold['value'],
            default => false,
        };

        if ($exceeds) {
            $this->recordThat(new ThresholdExceeded(
                aggregateId: $this->uuid(),
                metricName: $metricName,
                value: $value,
                threshold: $threshold['value'],
                level: $threshold['level']->value,
                exceededAt: now()->toDateTimeImmutable()
            ));
        }
    }

    public function triggerAlert(
        string $alertId,
        AlertLevel $level,
        string $message,
        array $context = []
    ): void {
        $this->recordThat(new AlertTriggered(
            aggregateId: $this->uuid(),
            alertId: $alertId,
            level: $level->value,
            message: $message,
            context: $context,
            triggeredAt: now()->toDateTimeImmutable()
        ));

        $this->activeAlerts[$alertId] = [
            'level'        => $level,
            'message'      => $message,
            'context'      => $context,
            'triggered_at' => now(),
        ];
    }

    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    public function getMetricsHistory(): array
    {
        $history = [];
        foreach ($this->metrics as $name => $records) {
            foreach ($records as $record) {
                $history[] = [
                    'name'      => $name,
                    'value'     => $record['value'],
                    'timestamp' => $record['timestamp'],
                ];
            }
        }

        return $history;
    }

    protected function applyMetricRecorded(MetricRecorded $event): void
    {
        $this->metrics[$event->name][] = [
            'value'     => $event->value,
            'timestamp' => $event->recordedAt,
            'labels'    => $event->labels,
        ];
    }

    protected function applyAlertTriggered(AlertTriggered $event): void
    {
        $this->activeAlerts[$event->alertId] = [
            'level'        => $event->level,
            'message'      => $event->message,
            'context'      => $event->context,
            'triggered_at' => $event->triggeredAt,
        ];
    }
}
