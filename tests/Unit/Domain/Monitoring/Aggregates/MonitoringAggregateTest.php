<?php

declare(strict_types=1);

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Events\AlertTriggeredEvent;
use App\Domain\Monitoring\Events\HealthCheckPerformedEvent;
use App\Domain\Monitoring\Events\MetricRecordedEvent;
use App\Domain\Monitoring\Events\MonitoringSessionStartedEvent;
use App\Domain\Monitoring\Events\ThresholdBreachedEvent;
use App\Domain\Monitoring\Events\TracingSpanRecordedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can start a monitoring session', function () {
    $aggregate = MonitoringAggregate::fake();
    
    $aggregate->startSession('test-session-id', ['type' => 'test']);
    
    $aggregate->assertRecorded(MonitoringSessionStartedEvent::class);
});

it('can record metrics', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->recordMetric(
        'test_metric',
        42.5,
        'gauge',
        ['environment' => 'test'],
        'units'
    );
    
    $aggregate->assertRecorded([
        MonitoringSessionStartedEvent::class,
        MetricRecordedEvent::class,
    ]);
    
    $metrics = $aggregate->getMetrics();
    expect($metrics)->toHaveKey('test_metric:environment=test');
});

it('can perform health checks', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->performHealthCheck(
        'database',
        true,
        'Database is healthy',
        ['connections' => 10]
    );
    
    $aggregate->assertRecorded([
        MonitoringSessionStartedEvent::class,
        HealthCheckPerformedEvent::class,
    ]);
    
    $healthStatus = $aggregate->getHealthStatus();
    expect($healthStatus)->toHaveKey('database');
    expect($healthStatus['database']['isHealthy'])->toBeTrue();
});

it('can record tracing spans', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->recordTracingSpan(
        'trace-123',
        'span-456',
        'test-operation',
        100.5,
        ['service' => 'test-service'],
        'parent-span-123'
    );
    
    $aggregate->assertRecorded([
        MonitoringSessionStartedEvent::class,
        TracingSpanRecordedEvent::class,
    ]);
    
    $traces = $aggregate->getTraces();
    expect($traces)->toHaveKey('span-456');
});

it('can check thresholds and trigger breach events', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->checkThreshold('cpu_usage', 85.5, 80.0, '>');
    
    $aggregate->assertRecorded([
        MonitoringSessionStartedEvent::class,
        ThresholdBreachedEvent::class,
    ]);
    
    $alerts = $aggregate->getAlerts();
    expect($alerts)->toHaveCount(1);
    expect($alerts[0]['type'])->toBe('threshold_breach');
});

it('does not trigger breach event when threshold is not exceeded', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->checkThreshold('cpu_usage', 75.0, 80.0, '>');
    
    $aggregate->assertNotRecorded(ThresholdBreachedEvent::class);
});

it('can trigger alerts', function () {
    $aggregate = MonitoringAggregate::fake();
    $aggregate->startSession('test-session-id');
    
    $aggregate->triggerAlert(
        'high_cpu_usage',
        'critical',
        'CPU usage is above 90%',
        ['cpu_usage' => 92.5, 'threshold' => 90]
    );
    
    $aggregate->assertRecorded([
        MonitoringSessionStartedEvent::class,
        AlertTriggeredEvent::class,
    ]);
    
    $alerts = $aggregate->getAlerts();
    expect($alerts)->toHaveCount(1);
    expect($alerts[0]['severity'])->toBe('critical');
});

it('does not start a session twice', function () {
    $aggregate = MonitoringAggregate::fake();
    
    $aggregate->startSession('test-session-id');
    $aggregate->startSession('another-session-id');
    
    // Check that only one session started event was recorded
    $events = $aggregate->getRecordedEvents();
    $sessionStartedEvents = array_filter($events, function ($event) {
        return $event instanceof MonitoringSessionStartedEvent;
    });
    expect(count($sessionStartedEvents))->toBe(1);
});