<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\TracingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Tests\TestCase;

class TracingServiceTest extends TestCase
{
    use RefreshDatabase;

    private TracingService $service;

    private MockInterface $tracer;

    private MockInterface $span;

    private MockInterface $spanBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tracer = Mockery::mock(TracerInterface::class);
        $this->span = Mockery::mock(SpanInterface::class);
        $this->spanBuilder = Mockery::mock(SpanBuilderInterface::class);

        /** @var TracerInterface $tracer */
        $tracer = $this->tracer;
        $this->service = new TracingService($tracer);
    }

    public function test_start_trace_creates_new_trace_aggregate(): void
    {
        // Arrange
        $traceName = 'test-trace';
        $attributes = ['key' => 'value'];

        $this->spanBuilder->shouldReceive('setSpanKind')->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('setAttributes')->with($attributes)->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($traceName)
            ->andReturn($this->spanBuilder);

        $scope = Mockery::mock(\OpenTelemetry\Context\ScopeInterface::class);
        $this->span->shouldReceive('activate')->andReturn($scope);

        // Act
        $traceId = $this->service->startTrace($traceName, $attributes);

        // Assert
        $this->assertNotEmpty($traceId);
    }

    public function test_start_span_creates_child_span(): void
    {
        // Arrange
        $spanName = 'child-span';
        $parentSpanId = 'parent-123';
        $attributes = ['operation' => 'test'];

        $this->spanBuilder->shouldReceive('setAttributes')->with($attributes)->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($spanName)
            ->andReturn($this->spanBuilder);

        // Act
        $spanId = $this->service->startSpan($spanName, $parentSpanId, $attributes);

        // Assert
        $this->assertNotEmpty($spanId);
    }

    public function test_end_span_sets_status_and_ends_span(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $spanName = 'test-span';
        $attributes = ['final' => 'value'];

        $this->spanBuilder->shouldReceive('setAttributes')->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($spanName)
            ->andReturn($this->spanBuilder);

        $spanId = $this->service->startSpan($spanName);

        $this->span->shouldReceive('setAttributes')->with($attributes);
        $this->span->shouldReceive('setStatus')->with(StatusCode::STATUS_OK);
        $this->span->shouldReceive('end');

        // Act
        $this->service->endSpan($spanId, 'ok', $attributes);
    }

    public function test_record_error_records_exception_in_span(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $spanName = 'error-span';

        $this->spanBuilder->shouldReceive('setAttributes')->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($spanName)
            ->andReturn($this->spanBuilder);

        $spanId = $this->service->startSpan($spanName);

        $exception = new \Exception('Test error');
        $context = ['user' => 'test'];

        $this->span->shouldReceive('recordException')
            ->with($exception, Mockery::any());
        $this->span->shouldReceive('setStatus')
            ->with(StatusCode::STATUS_ERROR, 'Test error');

        // Act
        $this->service->recordError($spanId, $exception, $context);
    }

    public function test_add_event_adds_event_to_span(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $spanName = 'event-span';

        $this->spanBuilder->shouldReceive('setAttributes')->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($spanName)
            ->andReturn($this->spanBuilder);

        $spanId = $this->service->startSpan($spanName);

        $eventName = 'cache.hit';
        $eventAttributes = ['key' => 'user:123'];

        $this->span->shouldReceive('addEvent')
            ->with($eventName, $eventAttributes);

        // Act
        $this->service->addEvent($spanId, $eventName, $eventAttributes);
    }

    public function test_set_attribute_updates_span_attribute(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $spanName = 'attribute-span';

        $this->spanBuilder->shouldReceive('setAttributes')->andReturn($this->spanBuilder);
        $this->spanBuilder->shouldReceive('startSpan')->andReturn($this->span);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($spanName)
            ->andReturn($this->spanBuilder);

        $spanId = $this->service->startSpan($spanName);

        $this->span->shouldReceive('setAttribute')
            ->with('http.status_code', 200);

        // Act
        $this->service->setAttribute($spanId, 'http.status_code', 200);
    }

    public function test_end_trace_ends_all_active_spans(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $span1Name = 'span-1';
        $span2Name = 'span-2';

        $span1 = Mockery::mock(SpanInterface::class);
        $span2 = Mockery::mock(SpanInterface::class);

        $builder1 = Mockery::mock(SpanBuilderInterface::class);
        $builder2 = Mockery::mock(SpanBuilderInterface::class);

        $builder1->shouldReceive('setAttributes')->andReturn($builder1);
        $builder1->shouldReceive('startSpan')->andReturn($span1);

        $builder2->shouldReceive('setAttributes')->andReturn($builder2);
        $builder2->shouldReceive('startSpan')->andReturn($span2);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($span1Name)
            ->andReturn($builder1);

        $this->tracer->shouldReceive('spanBuilder')
            ->with($span2Name)
            ->andReturn($builder2);

        $spanId1 = $this->service->startSpan($span1Name);
        $spanId2 = $this->service->startSpan($span2Name);

        $span2->shouldReceive('setStatus')->with(StatusCode::STATUS_OK);
        $span2->shouldReceive('end');

        $span1->shouldReceive('setStatus')->with(StatusCode::STATUS_OK);
        $span1->shouldReceive('end');

        // Act
        $this->service->endTrace();
    }

    public function test_service_works_without_tracer(): void
    {
        // Arrange
        $service = new TracingService(null);

        // Act
        $traceId = $service->startTrace('test-trace');
        $spanId = $service->startSpan('test-span');
        $service->addEvent($spanId, 'test-event', []);
        $service->setAttribute($spanId, 'key', 'value');
        $service->recordError($spanId, new \Exception('test'), []);
        $service->endSpan($spanId);
        $service->endTrace();

        // Assert - no exceptions thrown
        $this->assertNotEmpty($traceId);
        $this->assertNotEmpty($spanId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
