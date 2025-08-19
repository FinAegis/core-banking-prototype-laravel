<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Aggregates;

use App\Domain\Monitoring\Aggregates\TraceAggregate;
use App\Domain\Monitoring\Events\SpanAttributeUpdated;
use App\Domain\Monitoring\Events\SpanEnded;
use App\Domain\Monitoring\Events\SpanErrorOccurred;
use App\Domain\Monitoring\Events\SpanEventRecorded;
use App\Domain\Monitoring\Events\SpanStarted;
use App\Domain\Monitoring\Events\TraceCompleted;
use App\Domain\Monitoring\ValueObjects\SpanStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TraceAggregateTest extends TestCase
{
    use RefreshDatabase;

    private TraceAggregate $aggregate;

    private string $traceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traceId = Str::uuid()->toString();
        $this->aggregate = TraceAggregate::createNew($this->traceId, 'test-trace');
    }

    public function test_create_new_trace_aggregate(): void
    {
        // Assert
        $this->assertEquals($this->traceId, $this->aggregate->uuid());
        $this->assertEquals('test-trace', $this->aggregate->getTraceName());
        $this->assertEmpty($this->aggregate->getSpans());
        $this->assertFalse($this->aggregate->hasErrors());
        $this->assertNull($this->aggregate->getDuration());
    }

    public function test_record_span_started(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $parentSpanId = null;
        $name = 'test-span';
        $attributes = ['key' => 'value'];
        $timestamp = microtime(true);

        // Act
        $this->aggregate->recordSpanStarted($spanId, $parentSpanId, $name, $attributes, $timestamp);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SpanStarted::class, $events[0]);
        $this->assertEquals($spanId, $events[0]->spanId);
        $this->assertEquals($name, $events[0]->name);
        $this->assertEquals($attributes, $events[0]->attributes);
    }

    public function test_record_span_ended(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $status = SpanStatus::OK;
        $attributes = ['final' => 'value'];
        $timestamp = microtime(true);

        // Act
        $this->aggregate->recordSpanEnded($spanId, $status, $attributes, $timestamp);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SpanEnded::class, $events[0]);
        $this->assertEquals($spanId, $events[0]->spanId);
        $this->assertEquals($status->value, $events[0]->status);
        $this->assertEquals($attributes, $events[0]->attributes);
    }

    public function test_record_span_error(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $message = 'Test error';
        $type = 'Exception';
        $stackTrace = 'Stack trace here';
        $context = ['user' => 'test'];
        $timestamp = microtime(true);

        // Act
        $this->aggregate->recordSpanError($spanId, $message, $type, $stackTrace, $context, $timestamp);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SpanErrorOccurred::class, $events[0]);
        $this->assertEquals($spanId, $events[0]->spanId);
        $this->assertEquals($message, $events[0]->message);
        $this->assertEquals($type, $events[0]->type);
        $this->assertTrue($this->aggregate->hasErrors());
    }

    public function test_record_span_event(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $eventName = 'cache.hit';
        $attributes = ['key' => 'user:123'];
        $timestamp = microtime(true);

        // Act
        $this->aggregate->recordSpanEvent($spanId, $eventName, $attributes, $timestamp);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SpanEventRecorded::class, $events[0]);
        $this->assertEquals($spanId, $events[0]->spanId);
        $this->assertEquals($eventName, $events[0]->eventName);
        $this->assertEquals($attributes, $events[0]->attributes);
    }

    public function test_update_span_attribute(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $key = 'http.status_code';
        $value = 200;

        // Act
        $this->aggregate->updateSpanAttribute($spanId, $key, $value);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SpanAttributeUpdated::class, $events[0]);
        $this->assertEquals($spanId, $events[0]->spanId);
        $this->assertEquals($key, $events[0]->key);
        $this->assertEquals($value, $events[0]->value);
    }

    public function test_complete_trace(): void
    {
        // Arrange
        $timestamp = microtime(true);

        // Add some spans first
        $spanId1 = Str::uuid()->toString();
        $spanId2 = Str::uuid()->toString();

        $this->aggregate->recordSpanStarted($spanId1, null, 'span-1', [], microtime(true));
        $this->aggregate->recordSpanStarted($spanId2, $spanId1, 'span-2', [], microtime(true));

        // Act
        $this->aggregate->completeTrace($timestamp);

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $completedEvent = array_filter($events, fn ($e) => $e instanceof TraceCompleted);
        $this->assertCount(1, $completedEvent);

        $event = array_values($completedEvent)[0];
        $this->assertEquals($this->traceId, $event->traceId);
        $this->assertFalse($event->hasErrors);
        $this->assertEquals(2, $event->spanCount);
    }

    public function test_get_root_spans(): void
    {
        // Arrange
        $rootSpanId = Str::uuid()->toString();
        $childSpanId = Str::uuid()->toString();
        $timestamp = microtime(true);

        // Reconstitute from events
        $fakeAggregate = TraceAggregate::fake($this->traceId)
            ->given([
                new SpanStarted($this->traceId, $rootSpanId, null, 'root-span', [], $timestamp),
                new SpanStarted($this->traceId, $childSpanId, $rootSpanId, 'child-span', [], $timestamp),
            ]);

        // Act
        /** @var TraceAggregate $aggregate */
        $aggregate = $fakeAggregate->aggregateRoot();
        $rootSpans = $aggregate->getRootSpans();

        // Assert
        $this->assertCount(1, $rootSpans);
        $this->assertEquals($rootSpanId, array_values($rootSpans)[0]['id']);
    }

    public function test_get_child_spans(): void
    {
        // Arrange
        $rootSpanId = Str::uuid()->toString();
        $childSpanId1 = Str::uuid()->toString();
        $childSpanId2 = Str::uuid()->toString();
        $timestamp = microtime(true);

        // Reconstitute from events
        $fakeAggregate = TraceAggregate::fake($this->traceId)
            ->given([
                new SpanStarted($this->traceId, $rootSpanId, null, 'root-span', [], $timestamp),
                new SpanStarted($this->traceId, $childSpanId1, $rootSpanId, 'child-1', [], $timestamp),
                new SpanStarted($this->traceId, $childSpanId2, $rootSpanId, 'child-2', [], $timestamp),
            ]);

        // Act
        /** @var TraceAggregate $aggregate */
        $aggregate = $fakeAggregate->aggregateRoot();
        $childSpans = $aggregate->getChildSpans($rootSpanId);

        // Assert
        $this->assertCount(2, $childSpans);
        $spanIds = array_map(fn ($span) => $span['id'], $childSpans);
        $this->assertContains($childSpanId1, $spanIds);
        $this->assertContains($childSpanId2, $spanIds);
    }

    public function test_to_array(): void
    {
        // Arrange
        $spanId = Str::uuid()->toString();
        $timestamp = microtime(true);

        $fakeAggregate = TraceAggregate::fake($this->traceId)
            ->given([
                new SpanStarted($this->traceId, $spanId, null, 'test-span', ['key' => 'value'], $timestamp),
                new SpanEnded($this->traceId, $spanId, 'ok', [], $timestamp + 1),
            ]);

        // Act
        /** @var TraceAggregate $aggregate */
        $aggregate = $fakeAggregate->aggregateRoot();
        $array = $aggregate->toArray();

        // Assert
        $this->assertEquals($this->traceId, $array['trace_id']);
        $this->assertEquals('test-span', $array['name']); // Name comes from the first span
        $this->assertFalse($array['has_errors']);
        $this->assertEquals(1, $array['span_count']);
        $this->assertCount(1, $array['spans']);
    }
}
