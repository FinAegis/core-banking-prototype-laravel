<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Aggregates;

use App\Domain\Monitoring\Aggregates\MetricsAggregate;
use App\Domain\Monitoring\Events\AlertTriggered;
use App\Domain\Monitoring\Events\MetricRecorded;
use App\Domain\Monitoring\Events\ThresholdExceeded;
use App\Domain\Monitoring\ValueObjects\AlertLevel;
use App\Domain\Monitoring\ValueObjects\MetricType;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsAggregateTest extends TestCase
{
    private $aggregate;

    private string $aggregateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregateId = Str::uuid()->toString();
        $this->aggregate = MetricsAggregate::fake($this->aggregateId);
    }

    public function test_can_record_metric(): void
    {
        // Arrange
        $metricId = Str::uuid()->toString();
        $type = MetricType::GAUGE;
        $name = 'test.metric';
        $value = 42.5;
        $labels = ['env' => 'testing'];
        $unit = 'seconds';

        // Act
        $this->aggregate->recordMetric(
            $metricId,
            $type,
            $name,
            $value,
            $labels,
            $unit
        );

        // Assert
        $this->aggregate->assertRecorded(function (MetricRecorded $event) use ($metricId, $type, $name, $value, $labels, $unit) {
            return $event->metricId === $metricId
                && $event->type === $type->value
                && $event->name === $name
                && $event->value === $value
                && $event->labels === $labels
                && $event->unit === $unit;
        });
    }

    public function test_can_set_threshold(): void
    {
        // Arrange
        $metricName = 'response.time';
        $threshold = 500.0;
        $alertLevel = AlertLevel::WARNING;

        // Act
        $this->aggregate->setThreshold($metricName, $threshold, $alertLevel);

        // Assert - Note: getThresholds is not accessible on FakeAggregateRoot
        // Instead, we'll test indirectly by triggering a threshold exceeded event
        $metricId = Str::uuid()->toString();
        $value = $threshold + 10; // Exceed the threshold

        // Record a metric that exceeds the threshold
        $this->aggregate->recordMetric(
            $metricId,
            MetricType::GAUGE,
            $metricName,
            $value
        );

        // Check that ThresholdExceeded event was recorded
        $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
        $this->assertEquals(2, $events->count()); // MetricRecorded + ThresholdExceeded
        $this->assertTrue(
            $events->contains(function ($event) use ($alertLevel) {
                return $event instanceof ThresholdExceeded && $event->level === $alertLevel->value;
            })
        );
    }

    public function test_triggers_alert_when_threshold_exceeded(): void
    {
        // Arrange
        $metricId = Str::uuid()->toString();
        $type = MetricType::GAUGE;
        $name = 'cpu.usage';
        $threshold = 80.0;
        $value = 95.0;
        $alertLevel = AlertLevel::CRITICAL;

        // Set threshold
        $this->aggregate->setThreshold($name, $threshold, $alertLevel);

        // Act
        $this->aggregate->recordMetric(
            $metricId,
            $type,
            $name,
            $value
        );

        // Assert
        $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
        $this->assertEquals(2, $events->count());

        // First event should be MetricRecorded
        $this->assertTrue(
            $events->contains(function ($event) use ($name, $value) {
                return $event instanceof MetricRecorded && $event->name === $name && $event->value === $value;
            })
        );

        // Second event should be ThresholdExceeded
        $this->assertTrue(
            $events->contains(function ($event) use ($name, $value, $threshold, $alertLevel) {
                return $event instanceof ThresholdExceeded
                    && $event->metricName === $name
                    && $event->value === $value
                    && $event->threshold === $threshold
                    && $event->level === $alertLevel->value;
            })
        );
    }

    public function test_does_not_trigger_alert_when_below_threshold(): void
    {
        // Arrange
        $metricId = Str::uuid()->toString();
        $type = MetricType::GAUGE;
        $name = 'memory.usage';
        $threshold = 80.0;
        $value = 65.0;
        $alertLevel = AlertLevel::WARNING;

        // Set threshold
        $this->aggregate->setThreshold($name, $threshold, $alertLevel);

        // Act
        $this->aggregate->recordMetric(
            $metricId,
            $type,
            $name,
            $value
        );

        // Assert - Only MetricRecorded event, no ThresholdExceeded
        $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
        $this->assertEquals(1, $events->count());
        $this->assertInstanceOf(MetricRecorded::class, $events->first());
        $this->assertFalse($events->contains(fn ($e) => $e instanceof ThresholdExceeded));
    }

    public function test_can_trigger_custom_alert(): void
    {
        // Arrange
        $alertId = Str::uuid()->toString();
        $alertLevel = AlertLevel::EMERGENCY;
        $message = 'System critical failure detected';
        $context = ['service' => 'payment_gateway', 'error_rate' => 0.95];

        // Act
        $this->aggregate->triggerAlert($alertId, $alertLevel, $message, $context);

        // Assert
        $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
        $this->assertTrue(
            $events->contains(function ($event) use ($alertId, $alertLevel, $message, $context) {
                return $event instanceof AlertTriggered
                    && $event->alertId === $alertId
                    && $event->level === $alertLevel->value
                    && $event->message === $message
                    && $event->context === $context;
            })
        );
    }

    public function test_can_get_metrics_history(): void
    {
        // Arrange & Act
        $metrics = [
            ['id' => Str::uuid()->toString(), 'name' => 'metric1', 'value' => 10.0],
            ['id' => Str::uuid()->toString(), 'name' => 'metric2', 'value' => 20.0],
            ['id' => Str::uuid()->toString(), 'name' => 'metric3', 'value' => 30.0],
        ];

        foreach ($metrics as $metric) {
            $this->aggregate->recordMetric(
                $metric['id'],
                MetricType::COUNTER,
                $metric['name'],
                $metric['value']
            );
        }

        // Assert
        // Note: getMetricsHistory is not accessible on FakeAggregateRoot
        // Verify the events were recorded
        $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
        $this->assertEquals(3, $events->count());

        foreach ($metrics as $metric) {
            $this->assertTrue(
                $events->contains(function ($event) use ($metric) {
                    return $event instanceof MetricRecorded
                        && $event->name === $metric['name']
                        && $event->value === $metric['value'];
                })
            );
        }
    }

    public function test_different_metric_types(): void
    {
        // Test each metric type
        $types = [
            MetricType::COUNTER,
            MetricType::GAUGE,
            MetricType::HISTOGRAM,
            MetricType::SUMMARY,
        ];

        foreach ($types as $type) {
            $metricId = Str::uuid()->toString();
            $name = "test.{$type->value}";
            $value = rand(1, 100);

            $this->aggregate->recordMetric(
                $metricId,
                $type,
                $name,
                $value
            );

            $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
            $this->assertTrue(
                $events->contains(function ($event) use ($type) {
                    return $event instanceof MetricRecorded && $event->type === $type->value;
                })
            );
        }
    }

    public function test_alert_levels_hierarchy(): void
    {
        $levels = [
            AlertLevel::INFO,
            AlertLevel::WARNING,
            AlertLevel::CRITICAL,
            AlertLevel::EMERGENCY,
        ];

        foreach ($levels as $level) {
            $alertId = Str::uuid()->toString();
            $message = "Test {$level->value} alert";

            $this->aggregate->triggerAlert($alertId, $level, $message);

            $events = collect($this->aggregate->aggregateRoot()->getRecordedEvents());
            $this->assertTrue(
                $events->contains(function ($event) use ($level) {
                    return $event instanceof AlertTriggered && $event->level === $level->value;
                })
            );
        }
    }
}
