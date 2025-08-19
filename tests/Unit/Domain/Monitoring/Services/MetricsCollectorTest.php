<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(MonitoringAggregateRepository::class);
    $this->repository->shouldReceive('findBySessionId')->andReturn(null);
    $this->collector = new MetricsCollector($this->repository);
    Cache::flush();
});

it('can increment a counter metric', function () {
    $this->collector->incrementCounter('test_counter', 1.0, ['env' => 'test']);
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics)->toHaveKey('test_counter:env=test');
    expect($metrics['test_counter:env=test']['type'])->toBe('counter');
    expect($metrics['test_counter:env=test']['value'])->toBe(1.0);
});

it('accumulates counter increments', function () {
    $this->collector->incrementCounter('test_counter', 1.0, ['env' => 'test']);
    $this->collector->incrementCounter('test_counter', 2.0, ['env' => 'test']);
    $this->collector->incrementCounter('test_counter', 3.0, ['env' => 'test']);
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics['test_counter:env=test']['value'])->toBe(6.0);
});

it('can set a gauge metric', function () {
    $this->collector->setGauge('memory_usage', 1024.5, ['server' => 'app1'], 'MB');
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics)->toHaveKey('memory_usage:server=app1');
    expect($metrics['memory_usage:server=app1']['type'])->toBe('gauge');
    expect($metrics['memory_usage:server=app1']['value'])->toBe(1024.5);
    expect($metrics['memory_usage:server=app1']['unit'])->toBe('MB');
});

it('overwrites gauge values', function () {
    $this->collector->setGauge('cpu_usage', 50.0, ['server' => 'app1']);
    $this->collector->setGauge('cpu_usage', 75.0, ['server' => 'app1']);
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics['cpu_usage:server=app1']['value'])->toBe(75.0);
});

it('can record histogram values', function () {
    $buckets = [0.1, 0.5, 1.0, 2.0, 5.0];
    
    $this->collector->recordHistogram('request_duration', 0.3, $buckets, ['endpoint' => '/api/users'], 'seconds');
    $this->collector->recordHistogram('request_duration', 0.7, $buckets, ['endpoint' => '/api/users'], 'seconds');
    $this->collector->recordHistogram('request_duration', 1.5, $buckets, ['endpoint' => '/api/users'], 'seconds');
    
    $metrics = Cache::get('monitoring:metrics', []);
    $histogram = $metrics['request_duration:endpoint=/api/users'];
    
    expect($histogram['type'])->toBe('histogram');
    expect($histogram['count'])->toBe(3);
    expect($histogram['sum'])->toBe(2.5);
    expect($histogram['buckets'])->toBe($buckets);
    expect($histogram['bucket_counts'])->toBe([
        0.1 => 0,
        0.5 => 1,
        1.0 => 2,
        2.0 => 3,
        5.0 => 3,
    ]);
});

it('can record summary values', function () {
    $quantiles = [0.5, 0.9, 0.99];
    
    $this->collector->recordSummary('response_time', 100, $quantiles, ['service' => 'api'], 'ms');
    $this->collector->recordSummary('response_time', 200, $quantiles, ['service' => 'api'], 'ms');
    $this->collector->recordSummary('response_time', 150, $quantiles, ['service' => 'api'], 'ms');
    
    $metrics = Cache::get('monitoring:metrics', []);
    $summary = $metrics['response_time:service=api'];
    
    expect($summary['type'])->toBe('summary');
    expect($summary['count'])->toBe(3);
    expect($summary['sum'])->toBe(450);
    expect($summary['values'])->toHaveCount(3);
    expect($summary['values'])->toContain(100, 150, 200);
});

it('can retrieve all metrics', function () {
    $this->collector->incrementCounter('counter1', 1.0);
    $this->collector->setGauge('gauge1', 42.0);
    $this->collector->recordHistogram('histogram1', 0.5, [0.1, 1.0, 10.0]);
    
    $metrics = $this->collector->getMetrics();
    
    expect($metrics)->toHaveCount(3);
    expect($metrics)->toHaveKeys(['counter1:', 'gauge1:', 'histogram1:']);
});

it('can clear all metrics', function () {
    $this->collector->incrementCounter('test_counter', 1.0);
    $this->collector->setGauge('test_gauge', 42.0);
    
    $this->collector->clearMetrics();
    
    $metrics = $this->collector->getMetrics();
    expect($metrics)->toBeEmpty();
});

it('generates correct metric keys with labels', function () {
    $this->collector->incrementCounter('test', 1.0, ['env' => 'prod', 'region' => 'us-east']);
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics)->toHaveKey('test:env=prod,region=us-east');
});

it('handles empty labels correctly', function () {
    $this->collector->incrementCounter('test', 1.0, []);
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics)->toHaveKey('test:');
});

it('stores metric descriptions', function () {
    $this->collector->incrementCounter('api_requests', 1.0, [], 'Total number of API requests');
    
    $metrics = Cache::get('monitoring:metrics', []);
    
    expect($metrics['api_requests:']['description'])->toBe('Total number of API requests');
});

it('calculates histogram bucket counts correctly', function () {
    $buckets = [1.0, 5.0, 10.0, 50.0, 100.0];
    
    $this->collector->recordHistogram('test', 3.0, $buckets);
    $this->collector->recordHistogram('test', 7.0, $buckets);
    $this->collector->recordHistogram('test', 25.0, $buckets);
    $this->collector->recordHistogram('test', 75.0, $buckets);
    $this->collector->recordHistogram('test', 150.0, $buckets);
    
    $metrics = Cache::get('monitoring:metrics', []);
    $bucketCounts = $metrics['test:']['bucket_counts'];
    
    expect($bucketCounts[1.0])->toBe(0);
    expect($bucketCounts[5.0])->toBe(1);
    expect($bucketCounts[10.0])->toBe(2);
    expect($bucketCounts[50.0])->toBe(3);
    expect($bucketCounts[100.0])->toBe(4);
});

it('calculates summary quantiles correctly', function () {
    $quantiles = [0.5, 0.9, 0.99];
    
    // Add 10 values
    for ($i = 1; $i <= 10; $i++) {
        $this->collector->recordSummary('test', $i * 10, $quantiles);
    }
    
    $metrics = Cache::get('monitoring:metrics', []);
    $summary = $metrics['test:'];
    
    expect($summary['count'])->toBe(10);
    expect($summary['sum'])->toBe(550); // 10+20+30+...+100
    expect($summary['values'])->toHaveCount(10);
    
    // Calculate quantiles
    $calculated = $this->collector->calculateQuantiles($summary['values'], $quantiles);
    expect($calculated[0.5])->toBeGreaterThanOrEqual(40);
    expect($calculated[0.5])->toBeLessThanOrEqual(60);
    expect($calculated[0.9])->toBeGreaterThanOrEqual(80);
    expect($calculated[0.99])->toBeGreaterThanOrEqual(90);
});