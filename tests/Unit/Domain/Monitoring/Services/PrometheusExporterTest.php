<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\PrometheusExporter;
use App\Domain\Monitoring\Services\MetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(\App\Domain\Monitoring\Repositories\MonitoringAggregateRepository::class);
    $this->repository->shouldReceive('findBySessionId')->andReturn(null);
    $this->collector = new MetricsCollector($this->repository);
    $this->exporter = new PrometheusExporter($this->collector);
    Cache::flush();
});

it('exports metrics in Prometheus format', function () {
    // Set up some test metrics
    Cache::put('monitoring:metrics', [
        'http_requests_total:method=GET,status=200' => [
            'type' => 'counter',
            'value' => 1234,
            'description' => 'Total HTTP requests',
        ],
        'memory_usage_bytes:server=app1' => [
            'type' => 'gauge',
            'value' => 134217728,
            'unit' => 'bytes',
            'description' => 'Memory usage in bytes',
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('# HELP http_requests_total Total HTTP requests');
    expect($output)->toContain('# TYPE http_requests_total counter');
    expect($output)->toContain('http_requests_total{method="GET",status="200"} 1234');
    
    expect($output)->toContain('# HELP memory_usage_bytes Memory usage in bytes');
    expect($output)->toContain('# TYPE memory_usage_bytes gauge');
    expect($output)->toContain('memory_usage_bytes{server="app1"} 134217728');
});

it('exports histogram metrics correctly', function () {
    Cache::put('monitoring:metrics', [
        'http_request_duration_seconds:endpoint=/api/users' => [
            'type' => 'histogram',
            'count' => 100,
            'sum' => 50.5,
            'buckets' => [0.1, 0.5, 1.0, 5.0],
            'bucket_counts' => [
                0.1 => 20,
                0.5 => 60,
                1.0 => 90,
                5.0 => 100,
            ],
            'description' => 'HTTP request duration',
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('# HELP http_request_duration_seconds HTTP request duration');
    expect($output)->toContain('# TYPE http_request_duration_seconds histogram');
    expect($output)->toContain('http_request_duration_seconds_bucket{endpoint="/api/users",le="0.1"} 20');
    expect($output)->toContain('http_request_duration_seconds_bucket{endpoint="/api/users",le="0.5"} 60');
    expect($output)->toContain('http_request_duration_seconds_bucket{endpoint="/api/users",le="1"} 90');
    expect($output)->toContain('http_request_duration_seconds_bucket{endpoint="/api/users",le="5"} 100');
    expect($output)->toContain('http_request_duration_seconds_bucket{endpoint="/api/users",le="+Inf"} 100');
    expect($output)->toContain('http_request_duration_seconds_sum{endpoint="/api/users"} 50.5');
    expect($output)->toContain('http_request_duration_seconds_count{endpoint="/api/users"} 100');
});

it('exports summary metrics correctly', function () {
    Cache::put('monitoring:metrics', [
        'response_time_ms:service=api' => [
            'type' => 'summary',
            'count' => 50,
            'sum' => 5000,
            'quantiles' => [0.5, 0.9, 0.99],
            'calculated_quantiles' => [
                0.5 => 95,
                0.9 => 150,
                0.99 => 200,
            ],
            'description' => 'Response time in milliseconds',
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('# HELP response_time_ms Response time in milliseconds');
    expect($output)->toContain('# TYPE response_time_ms summary');
    expect($output)->toContain('response_time_ms{service="api",quantile="0.5"} 95');
    expect($output)->toContain('response_time_ms{service="api",quantile="0.9"} 150');
    expect($output)->toContain('response_time_ms{service="api",quantile="0.99"} 200');
    expect($output)->toContain('response_time_ms_sum{service="api"} 5000');
    expect($output)->toContain('response_time_ms_count{service="api"} 50');
});

it('exports metrics in JSON format', function () {
    Cache::put('monitoring:metrics', [
        'test_metric:env=prod' => [
            'type' => 'gauge',
            'value' => 42.5,
            'description' => 'Test metric',
        ],
    ]);
    
    $json = $this->exporter->exportJson();
    
    expect($json)->toHaveKey('metrics');
    expect($json['metrics'])->toHaveCount(1);
    expect($json['metrics'][0])->toMatchArray([
        'name' => 'test_metric',
        'type' => 'gauge',
        'value' => 42.5,
        'labels' => ['env' => 'prod'],
        'description' => 'Test metric',
    ]);
});

it('collects application metrics', function () {
    // Mock some application state
    Cache::put('test_key', 'value');
    
    $metrics = $this->exporter->collectApplicationMetrics();
    
    expect($metrics)->toHaveKey('php_memory_usage_bytes');
    expect($metrics)->toHaveKey('php_memory_peak_bytes');
    expect($metrics)->toHaveKey('laravel_cache_hits_total');
    expect($metrics)->toHaveKey('laravel_cache_misses_total');
});

it('collects business metrics', function () {
    // Create some test data
    DB::table('accounts')->insert([
        ['id' => 1, 'name' => 'Test Account', 'type' => 'checking', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    ]);
    
    DB::table('transactions')->insert([
        ['id' => 1, 'type' => 'deposit', 'amount' => 100, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
    ]);
    
    $metrics = $this->exporter->collectBusinessMetrics();
    
    expect($metrics)->toHaveKey('total_accounts');
    expect($metrics['total_accounts']['value'])->toBe(1.0);
    expect($metrics)->toHaveKey('total_transactions');
    expect($metrics['total_transactions']['value'])->toBe(1.0);
});

it('collects infrastructure metrics', function () {
    $metrics = $this->exporter->collectInfrastructureMetrics();
    
    expect($metrics)->toHaveKey('database_connections_active');
    expect($metrics)->toHaveKey('redis_connected');
    expect($metrics)->toHaveKey('queue_jobs_pending');
});

it('handles empty metrics gracefully', function () {
    Cache::put('monitoring:metrics', []);
    
    $output = $this->exporter->export();
    
    expect($output)->toBeString();
    expect($output)->not->toBeEmpty(); // Should still have application metrics
});

it('escapes special characters in labels', function () {
    Cache::put('monitoring:metrics', [
        'test_metric:label="value\\with\\special"' => [
            'type' => 'counter',
            'value' => 1,
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('test_metric{label="value\\\\with\\\\special"} 1');
});

it('formats metric names correctly', function () {
    Cache::put('monitoring:metrics', [
        'test.metric-name:' => [
            'type' => 'gauge',
            'value' => 1,
        ],
    ]);
    
    $output = $this->exporter->export();
    
    // Dots and hyphens should be converted to underscores
    expect($output)->toContain('test_metric_name ');
});

it('includes timestamp in export', function () {
    Cache::put('monitoring:metrics', [
        'test_metric:' => [
            'type' => 'gauge',
            'value' => 1,
            'timestamp' => 1234567890,
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('test_metric  1 1234567890');
});

it('aggregates metrics from multiple sources', function () {
    // Set cached metrics
    Cache::put('monitoring:metrics', [
        'cached_metric:' => [
            'type' => 'gauge',
            'value' => 100,
        ],
    ]);
    
    $output = $this->exporter->export();
    
    // Should include both cached and collected metrics
    expect($output)->toContain('cached_metric ');
    expect($output)->toContain('php_memory_usage_bytes'); // Application metric
});

it('handles malformed metrics gracefully', function () {
    Cache::put('monitoring:metrics', [
        'valid_metric:' => [
            'type' => 'gauge',
            'value' => 42,
        ],
        'invalid_metric:' => [
            // Missing required fields
            'type' => 'unknown',
        ],
        'another_valid:' => [
            'type' => 'counter',
            'value' => 10,
        ],
    ]);
    
    $output = $this->exporter->export();
    
    expect($output)->toContain('valid_metric ');
    expect($output)->toContain('another_valid ');
    expect($output)->not->toContain('invalid_metric');
});