<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\DistributedTracer;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(MonitoringAggregateRepository::class);
    $this->metricsCollector = Mockery::mock(MetricsCollector::class);
    $this->tracer = new DistributedTracer($this->repository, $this->metricsCollector);
    Cache::flush();
});

it('can start a new trace', function () {
    $traceId = $this->tracer->startTrace('test-operation', ['service' => 'test']);
    
    expect($traceId)->toBeString();
    expect($traceId)->not->toBeEmpty();
    
    $context = $this->tracer->getCurrentContext();
    expect($context)->toHaveKey('trace_id');
    expect($context['trace_id'])->toBe($traceId);
});

it('generates unique trace IDs', function () {
    $traceId1 = $this->tracer->startTrace('operation1');
    $traceId2 = $this->tracer->startTrace('operation2');
    
    expect($traceId1)->not->toBe($traceId2);
});

it('can start a span within a trace', function () {
    $traceId = $this->tracer->startTrace('parent-operation');
    $spanId = $this->tracer->startSpan('child-operation', null, ['component' => 'database']);
    
    expect($spanId)->toBeString();
    expect($spanId)->not->toBeEmpty();
    
    $context = $this->tracer->getCurrentContext();
    expect($context['span_id'])->toBe($spanId);
    expect($context['trace_id'])->toBe($traceId);
});

it('can start a child span with parent', function () {
    $traceId = $this->tracer->startTrace('root-operation');
    $parentSpanId = $this->tracer->startSpan('parent-span');
    $childSpanId = $this->tracer->startSpan('child-span', $parentSpanId);
    
    $spans = Cache::get("tracing:trace:{$traceId}:spans", []);
    
    expect($spans)->toHaveKey($childSpanId);
    expect($spans[$childSpanId]['parent_span_id'])->toBe($parentSpanId);
});

it('can end a span and calculate duration', function () {
    $traceId = $this->tracer->startTrace('test-operation');
    $spanId = $this->tracer->startSpan('test-span');
    
    // Simulate some time passing
    sleep(0.1);
    
    $this->tracer->endSpan($spanId);
    
    $spans = Cache::get("tracing:trace:{$traceId}:spans", []);
    
    expect($spans[$spanId])->toHaveKey('end_time');
    expect($spans[$spanId])->toHaveKey('duration');
    expect($spans[$spanId]['duration'])->toBeGreaterThan(0);
});

it('can end a trace', function () {
    $traceId = $this->tracer->startTrace('test-operation');
    $spanId = $this->tracer->startSpan('test-span');
    
    $this->tracer->endSpan($spanId);
    $this->tracer->endTrace();
    
    $context = $this->tracer->getCurrentContext();
    expect($context)->toBeEmpty();
});

it('can add tags to the current span', function () {
    $this->tracer->startTrace('test-operation');
    $spanId = $this->tracer->startSpan('test-span');
    
    $this->tracer->addTag('user_id', '12345');
    $this->tracer->addTag('request_id', 'abc-123');
    
    $context = $this->tracer->getCurrentContext();
    $traceId = $context['trace_id'];
    
    $spans = Cache::get("tracing:trace:{$traceId}:spans", []);
    
    expect($spans[$spanId]['tags'])->toHaveKey('user_id');
    expect($spans[$spanId]['tags']['user_id'])->toBe('12345');
    expect($spans[$spanId]['tags'])->toHaveKey('request_id');
    expect($spans[$spanId]['tags']['request_id'])->toBe('abc-123');
});

it('can log events to the current span', function () {
    $this->tracer->startTrace('test-operation');
    $spanId = $this->tracer->startSpan('test-span');
    
    $this->tracer->logEvent('cache_hit', ['key' => 'user:123']);
    $this->tracer->logEvent('database_query', ['query' => 'SELECT * FROM users']);
    
    $context = $this->tracer->getCurrentContext();
    $traceId = $context['trace_id'];
    
    $spans = Cache::get("tracing:trace:{$traceId}:spans", []);
    
    expect($spans[$spanId]['logs'])->toHaveCount(2);
    expect($spans[$spanId]['logs'][0]['event'])->toBe('cache_hit');
    expect($spans[$spanId]['logs'][0]['fields'])->toEqual(['key' => 'user:123']);
});

it('can inject trace context into carrier', function () {
    $traceId = $this->tracer->startTrace('test-operation');
    $spanId = $this->tracer->startSpan('test-span');
    
    $carrier = [];
    $this->tracer->inject($carrier);
    
    expect($carrier)->toHaveKey('X-Trace-Id');
    expect($carrier['X-Trace-Id'])->toBe($traceId);
    expect($carrier)->toHaveKey('X-Span-Id');
    expect($carrier['X-Span-Id'])->toBe($spanId);
});

it('can extract trace context from carrier', function () {
    $carrier = [
        'X-Trace-Id' => 'extracted-trace-id',
        'X-Span-Id' => 'extracted-span-id',
        'X-Parent-Span-Id' => 'parent-span-id',
    ];
    
    $context = $this->tracer->extract($carrier);
    
    expect($context)->toHaveKey('trace_id');
    expect($context['trace_id'])->toBe('extracted-trace-id');
    expect($context)->toHaveKey('span_id');
    expect($context['span_id'])->toBe('extracted-span-id');
    expect($context)->toHaveKey('parent_span_id');
    expect($context['parent_span_id'])->toBe('parent-span-id');
});

it('returns null when extracting from empty carrier', function () {
    $carrier = [];
    
    $context = $this->tracer->extract($carrier);
    
    expect($context)->toBeNull();
});

it('can get all traces', function () {
    $traceId1 = $this->tracer->startTrace('operation1');
    $this->tracer->startSpan('span1');
    $this->tracer->endTrace();
    
    $traceId2 = $this->tracer->startTrace('operation2');
    $this->tracer->startSpan('span2');
    $this->tracer->endTrace();
    
    $traces = $this->tracer->getTraces();
    
    expect($traces)->toHaveCount(2);
    expect($traces)->toHaveKey($traceId1);
    expect($traces)->toHaveKey($traceId2);
});

it('can clear all traces', function () {
    $this->tracer->startTrace('operation1');
    $this->tracer->startSpan('span1');
    $this->tracer->endTrace();
    
    $this->tracer->clearTraces();
    
    $traces = $this->tracer->getTraces();
    expect($traces)->toBeEmpty();
});

it('handles concurrent traces correctly', function () {
    // Start first trace
    $traceId1 = $this->tracer->startTrace('operation1');
    $span1 = $this->tracer->startSpan('span1');
    
    // Store first context
    $context1 = $this->tracer->getCurrentContext();
    
    // Start second trace (simulating another request)
    $traceId2 = $this->tracer->startTrace('operation2');
    $span2 = $this->tracer->startSpan('span2');
    
    // Second trace should have different context
    $context2 = $this->tracer->getCurrentContext();
    
    expect($context1['trace_id'])->not->toBe($context2['trace_id']);
    expect($context1['span_id'])->not->toBe($context2['span_id']);
});

it('preserves span hierarchy', function () {
    $traceId = $this->tracer->startTrace('root');
    $rootSpan = $this->tracer->startSpan('root-span');
    
    $childSpan1 = $this->tracer->startSpan('child-1', $rootSpan);
    $grandchildSpan = $this->tracer->startSpan('grandchild', $childSpan1);
    $childSpan2 = $this->tracer->startSpan('child-2', $rootSpan);
    
    $spans = Cache::get("tracing:trace:{$traceId}:spans", []);
    
    expect($spans[$childSpan1]['parent_span_id'])->toBe($rootSpan);
    expect($spans[$grandchildSpan]['parent_span_id'])->toBe($childSpan1);
    expect($spans[$childSpan2]['parent_span_id'])->toBe($rootSpan);
});

it('includes baggage in context propagation', function () {
    $this->tracer->startTrace('test-operation');
    $this->tracer->startSpan('test-span');
    
    // Add baggage items
    $this->tracer->setBaggageItem('user_id', '12345');
    $this->tracer->setBaggageItem('session_id', 'xyz789');
    
    $carrier = [];
    $this->tracer->inject($carrier);
    
    expect($carrier)->toHaveKey('X-Baggage-user_id');
    expect($carrier['X-Baggage-user_id'])->toBe('12345');
    expect($carrier)->toHaveKey('X-Baggage-session_id');
    expect($carrier['X-Baggage-session_id'])->toBe('xyz789');
});

it('extracts baggage from carrier', function () {
    $carrier = [
        'X-Trace-Id' => 'trace-123',
        'X-Span-Id' => 'span-456',
        'X-Baggage-user_id' => '12345',
        'X-Baggage-tenant_id' => 'tenant-1',
    ];
    
    $context = $this->tracer->extract($carrier);
    
    expect($context)->toHaveKey('baggage');
    expect($context['baggage'])->toHaveKey('user_id');
    expect($context['baggage']['user_id'])->toBe('12345');
    expect($context['baggage'])->toHaveKey('tenant_id');
    expect($context['baggage']['tenant_id'])->toBe('tenant-1');
});