<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Services\PrometheusExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear only monitoring-related cache keys to avoid conflicts in parallel tests
    $keysToForget = [
        'metrics:http:requests:total',
        'metrics:http:requests:status:200',
        'metrics:http:requests:status:201',
        'metrics:http:requests:status:500',
        'metrics:http:methods:GET',
        'metrics:http:methods:POST',
        'metrics:http:duration:average',
        'metrics:http:duration:average:count',
        'metrics:http:duration:average:sum',
        'metrics:cache:hits',
        'metrics:cache:misses',
        'metrics:events:total',
        'metrics:events:user_registered:total',
        'metrics:events:order_placed:total',
        'metrics:workflows:order_processing:started',
        'metrics:workflows:order_processing:completed',
        'metrics:workflows:order_processing:duration',
    ];

    foreach ($keysToForget as $key) {
        Cache::forget($key);
    }
});

it('can record and retrieve HTTP metrics', function () {
    $collector = app(MetricsCollector::class);

    // Record some HTTP requests
    $collector->recordHttpRequest('GET', '/api/users', 200, 0.125);
    $collector->recordHttpRequest('POST', '/api/orders', 201, 0.250);
    $collector->recordHttpRequest('GET', '/api/products', 500, 1.5);

    // Check metrics in cache
    expect(Cache::get('metrics:http:requests:total'))->toBe('3');
    expect(Cache::get('metrics:http:requests:status:200'))->toBe('1');
    expect(Cache::get('metrics:http:requests:status:201'))->toBe('1');
    expect(Cache::get('metrics:http:requests:status:500'))->toBe('1');
    expect(Cache::get('metrics:http:methods:GET'))->toBe('2');
    expect(Cache::get('metrics:http:methods:POST'))->toBe('1');
});

it('can export metrics in Prometheus format', function () {
    $collector = app(MetricsCollector::class);
    $exporter = app(PrometheusExporter::class);

    // Record some metrics
    $collector->recordHttpRequest('GET', '/api/test', 200, 0.100);
    $collector->recordBusinessEvent('order_placed', ['customer' => '123']);

    // Export metrics
    $output = $exporter->export();

    expect($output)->toBeString();
    expect($output)->toContain('# HELP');
    expect($output)->toContain('# TYPE');
    expect($output)->toContain('http_requests_total');
});

it('can perform health checks', function () {
    $healthChecker = app(HealthChecker::class);

    $result = $healthChecker->check();

    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('timestamp');
    expect($result)->toHaveKey('checks');
    expect($result['status'])->toBeIn(['healthy', 'unhealthy']);
});

it('can track workflow metrics', function () {
    $collector = app(MetricsCollector::class);

    // Record workflow metrics
    $collector->recordWorkflowMetric('order_processing', 'started', 0);
    $collector->recordWorkflowMetric('order_processing', 'completed', 2.5);

    // Check metrics
    expect(Cache::get('metrics:workflows:order_processing:started'))->toBe('1');
    expect(Cache::get('metrics:workflows:order_processing:completed'))->toBe('1');
    expect(Cache::get('metrics:workflows:order_processing:duration'))->toBe('2.5');
});

it('can track business events', function () {
    $collector = app(MetricsCollector::class);

    // Record business events
    $collector->recordBusinessEvent('user_registered');
    $collector->recordBusinessEvent('order_placed');
    $collector->recordBusinessEvent('order_placed');

    // Check metrics
    expect(Cache::get('metrics:events:user_registered:total'))->toBe('1');
    expect(Cache::get('metrics:events:order_placed:total'))->toBe('2');
    expect(Cache::get('metrics:events:total'))->toBe('3');
});

it('calculates average duration correctly', function () {
    $collector = app(MetricsCollector::class);

    // Record multiple requests
    $collector->recordHttpRequest('GET', '/api/test', 200, 0.100);
    $collector->recordHttpRequest('GET', '/api/test', 200, 0.200);
    $collector->recordHttpRequest('GET', '/api/test', 200, 0.300);

    // Check average (should be 0.200)
    $average = Cache::get('metrics:http:duration:average');
    expect($average)->toBeGreaterThan(0);
    expect($average)->toBeLessThanOrEqual(0.300);
});

it('exports metrics with proper Prometheus formatting', function () {
    $exporter = app(PrometheusExporter::class);

    // Set some known metrics
    Cache::put('metrics:http:requests:total', 100);
    Cache::put('metrics:http:requests:status:200', 95);
    Cache::put('metrics:http:requests:status:500', 5);

    $output = $exporter->export();

    // Check for proper Prometheus format
    expect($output)->toContain('# HELP http_requests_total');
    expect($output)->toContain('# TYPE http_requests_total counter');
    expect($output)->toContain('http_requests_total 100');
});

it('health checker reports database status', function () {
    $healthChecker = app(HealthChecker::class);

    $result = $healthChecker->check();

    expect($result['checks'])->toHaveKey('database');
    expect($result['checks']['database'])->toHaveKey('healthy');
    expect($result['checks']['database'])->toHaveKey('message');
});

it('health checker reports cache status', function () {
    $healthChecker = app(HealthChecker::class);

    $result = $healthChecker->check();

    expect($result['checks'])->toHaveKey('cache');
    expect($result['checks']['cache'])->toHaveKey('healthy');
    expect($result['checks']['cache'])->toHaveKey('message');
});

it('health checker reports queue status', function () {
    $healthChecker = app(HealthChecker::class);

    $result = $healthChecker->check();

    expect($result['checks'])->toHaveKey('queue');
    expect($result['checks']['queue'])->toHaveKey('healthy');
    expect($result['checks']['queue'])->toHaveKey('message');
});

it('exports application metrics', function () {
    $exporter = app(PrometheusExporter::class);

    $output = $exporter->export();

    // Check for application metrics
    expect($output)->toContain('app_uptime_seconds');
    expect($output)->toContain('app_memory_usage_bytes');
    expect($output)->toContain('app_users_total');
});

it('exports infrastructure metrics', function () {
    $exporter = app(PrometheusExporter::class);

    $output = $exporter->export();

    // Check for infrastructure metrics
    expect($output)->toContain('infra_db_connections');
    expect($output)->toContain('infra_queue_size');
    expect($output)->toContain('infra_redis_memory_bytes');
});
