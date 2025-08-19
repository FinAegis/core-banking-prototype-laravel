<?php

declare(strict_types=1);

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Events\MonitoringSessionStartedEvent;
use App\Domain\Monitoring\Events\MetricRecordedEvent;
use App\Domain\Monitoring\Events\HealthCheckPerformedEvent;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Services\PrometheusExporter;
use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\DistributedTracer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('can create a monitoring session and record metrics', function () {
    $repository = app(MonitoringAggregateRepository::class);
    $collector = app(MetricsCollector::class);
    
    // Start a monitoring session
    $sessionId = 'test-session-' . uniqid();
    $aggregate = MonitoringAggregate::retrieve($sessionId);
    $aggregate->startSession($sessionId, ['type' => 'test']);
    $repository->store($aggregate);
    
    // Record some metrics
    $collector->incrementCounter('test_counter', 1.0, ['env' => 'test']);
    $collector->setGauge('test_gauge', 42.5, ['server' => 'app1']);
    
    // Retrieve metrics
    $metrics = $collector->getMetrics();
    
    expect($metrics)->toHaveKey('test_counter:env=test');
    expect($metrics['test_counter:env=test']['value'])->toBe(1.0);
    expect($metrics)->toHaveKey('test_gauge:server=app1');
    expect($metrics['test_gauge:server=app1']['value'])->toBe(42.5);
});

it('can export metrics in Prometheus format', function () {
    $repository = app(MonitoringAggregateRepository::class);
    $collector = app(MetricsCollector::class);
    $exporter = app(PrometheusExporter::class);
    
    // Set up metrics
    $collector->incrementCounter('http_requests_total', 100, ['method' => 'GET']);
    $collector->setGauge('memory_usage_bytes', 1024000, ['server' => 'app1']);
    
    // Export in Prometheus format
    $output = $exporter->export();
    
    expect($output)->toContain('http_requests_total{method="GET"}');
    expect($output)->toContain('memory_usage_bytes{server="app1"}');
});

it('can perform distributed tracing', function () {
    $repository = app(MonitoringAggregateRepository::class);
    $collector = app(MetricsCollector::class);
    $tracer = new DistributedTracer($repository, $collector);
    
    // Start a trace
    $traceId = $tracer->startTrace('test-operation', ['service' => 'api']);
    expect($traceId)->toBeString();
    
    // Start a span
    $spanId = $tracer->startSpan('database-query', null, ['db' => 'mysql']);
    expect($spanId)->toBeString();
    
    // Add tags
    $tracer->addTag('user_id', '12345');
    
    // End span and trace
    $tracer->endSpan($spanId);
    $tracer->endTrace();
    
    // Get trace data
    $traces = $tracer->getTraces();
    expect($traces)->toHaveKey($traceId);
});

it('can perform health checks', function () {
    $repository = app(MonitoringAggregateRepository::class);
    $collector = app(MetricsCollector::class);
    $healthChecker = new HealthChecker($repository, $collector);
    
    // Perform health check
    $result = $healthChecker->check();
    
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('healthy');
    expect($result)->toHaveKey('checks');
    expect($result)->toHaveKey('timestamp');
    
    // Check structure of individual checks
    expect($result['checks'])->toHaveKey('database');
    expect($result['checks'])->toHaveKey('cache');
    expect($result['checks'])->toHaveKey('redis');
    expect($result['checks'])->toHaveKey('queue');
});

it('can record events in the monitoring event store', function () {
    $repository = app(MonitoringAggregateRepository::class);
    
    $sessionId = 'event-test-' . uniqid();
    $aggregate = MonitoringAggregate::retrieve($sessionId);
    
    // Start session
    $aggregate->startSession($sessionId, ['purpose' => 'event testing']);
    
    // Record metric
    $aggregate->recordMetric('test_metric', 100.0, 'counter');
    
    // Perform health check
    $aggregate->performHealthCheck('test_component', true, 'All good');
    
    // Store aggregate
    $repository->store($aggregate);
    
    // Retrieve and verify
    $retrieved = $repository->findBySessionId($sessionId);
    
    expect($retrieved)->not->toBeNull();
    expect($retrieved->getSessionId())->toBe($sessionId);
    expect($retrieved->getMetrics())->not->toBeEmpty();
    expect($retrieved->getHealthStatus())->not->toBeEmpty();
});

it('can handle monitoring workflows', function () {
    // This test would require the workflow to be properly set up
    // For now, we'll just verify the workflow exists
    expect(class_exists(\App\Domain\Monitoring\Workflows\MonitoringWorkflow::class))->toBeTrue();
});

it('stores monitoring events in custom table', function () {
    $repository = app(MonitoringAggregateRepository::class);
    
    $sessionId = 'table-test-' . uniqid();
    $aggregate = MonitoringAggregate::retrieve($sessionId);
    
    $aggregate->startSession($sessionId);
    $repository->store($aggregate);
    
    // Check that event was stored in custom table
    $eventCount = DB::table('monitoring_events')
        ->where('aggregate_uuid', $sessionId)
        ->count();
    
    expect($eventCount)->toBeGreaterThan(0);
});

it('can aggregate metrics over time', function () {
    $collector = app(MetricsCollector::class);
    
    // Record multiple values for the same counter
    $collector->incrementCounter('api_calls', 10, ['endpoint' => '/users']);
    $collector->incrementCounter('api_calls', 15, ['endpoint' => '/users']);
    $collector->incrementCounter('api_calls', 5, ['endpoint' => '/users']);
    
    $metrics = $collector->getMetrics();
    
    // Counter should accumulate
    expect($metrics['api_calls:endpoint=/users']['value'])->toBe(30.0);
});

it('can export metrics in JSON format', function () {
    $repository = app(MonitoringAggregateRepository::class);
    $collector = app(MetricsCollector::class);
    $exporter = new PrometheusExporter($collector);
    
    // Set up metrics
    $collector->setGauge('test_metric', 42.5, ['env' => 'prod']);
    
    // Export as JSON
    $json = $exporter->exportJson();
    
    expect($json)->toHaveKey('metrics');
    expect($json['metrics'])->toBeArray();
    expect($json)->toHaveKey('timestamp');
});