<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Aggregates;

use App\Domain\Monitoring\Events\SpanAttributeUpdated;
use App\Domain\Monitoring\Events\SpanEnded;
use App\Domain\Monitoring\Events\SpanErrorOccurred;
use App\Domain\Monitoring\Events\SpanEventRecorded;
use App\Domain\Monitoring\Events\SpanStarted;
use App\Domain\Monitoring\Events\TraceCompleted;
use App\Domain\Monitoring\ValueObjects\SpanStatus;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class TraceAggregate extends AggregateRoot
{
    protected string $traceName = '';

    protected array $spans = [];

    protected float $startTime;

    protected ?float $endTime = null;

    protected array $rootAttributes = [];

    protected bool $hasErrors = false;

    public static function createNew(string $uuid, string $name): self
    {
        $aggregate = static::retrieve($uuid);
        $aggregate->traceName = $name;
        $aggregate->startTime = microtime(true);

        return $aggregate;
    }

    public function recordSpanStarted(
        string $spanId,
        ?string $parentSpanId,
        string $name,
        array $attributes,
        float $timestamp
    ): self {
        $this->recordThat(new SpanStarted(
            $this->uuid(),
            $spanId,
            $parentSpanId,
            $name,
            $attributes,
            $timestamp
        ));

        return $this;
    }

    public function recordSpanEnded(
        string $spanId,
        SpanStatus $status,
        array $attributes,
        float $timestamp
    ): self {
        $this->recordThat(new SpanEnded(
            $this->uuid(),
            $spanId,
            $status->value,
            $attributes,
            $timestamp
        ));

        return $this;
    }

    public function recordSpanError(
        string $spanId,
        string $message,
        string $type,
        string $stackTrace,
        array $context,
        float $timestamp
    ): self {
        $this->recordThat(new SpanErrorOccurred(
            $this->uuid(),
            $spanId,
            $message,
            $type,
            $stackTrace,
            $context,
            $timestamp
        ));

        $this->hasErrors = true;

        return $this;
    }

    public function recordSpanEvent(
        string $spanId,
        string $eventName,
        array $attributes,
        float $timestamp
    ): self {
        $this->recordThat(new SpanEventRecorded(
            $this->uuid(),
            $spanId,
            $eventName,
            $attributes,
            $timestamp
        ));

        return $this;
    }

    public function updateSpanAttribute(
        string $spanId,
        string $key,
        mixed $value
    ): self {
        $this->recordThat(new SpanAttributeUpdated(
            $this->uuid(),
            $spanId,
            $key,
            $value
        ));

        return $this;
    }

    public function completeTrace(float $timestamp): self
    {
        $this->recordThat(new TraceCompleted(
            $this->uuid(),
            $this->calculateDuration($timestamp),
            $this->hasErrors,
            $this->getSpanCount(),
            $timestamp
        ));

        $this->endTime = $timestamp;

        return $this;
    }

    protected function applySpanStarted(SpanStarted $event): void
    {
        // If this is the first span and we're reconstituting, set the trace name and start time
        if (empty($this->spans) && ! $event->parentSpanId) {
            $this->traceName = $event->name;
            $this->startTime = $event->timestamp;
        }

        $this->spans[$event->spanId] = [
            'id'         => $event->spanId,
            'parent_id'  => $event->parentSpanId,
            'name'       => $event->name,
            'attributes' => $event->attributes,
            'start_time' => $event->timestamp,
            'end_time'   => null,
            'status'     => null,
            'events'     => [],
            'errors'     => [],
        ];
    }

    protected function applySpanEnded(SpanEnded $event): void
    {
        if (isset($this->spans[$event->spanId])) {
            $this->spans[$event->spanId]['end_time'] = $event->timestamp;
            $this->spans[$event->spanId]['status'] = $event->status;
            $this->spans[$event->spanId]['attributes'] = array_merge(
                $this->spans[$event->spanId]['attributes'] ?? [],
                $event->attributes
            );
        }
    }

    protected function applySpanErrorOccurred(SpanErrorOccurred $event): void
    {
        if (isset($this->spans[$event->spanId])) {
            $this->spans[$event->spanId]['errors'][] = [
                'message'     => $event->message,
                'type'        => $event->type,
                'stack_trace' => $event->stackTrace,
                'context'     => $event->context,
                'timestamp'   => $event->timestamp,
            ];
        }
        $this->hasErrors = true;
    }

    protected function applySpanEventRecorded(SpanEventRecorded $event): void
    {
        if (isset($this->spans[$event->spanId])) {
            $this->spans[$event->spanId]['events'][] = [
                'name'       => $event->eventName,
                'attributes' => $event->attributes,
                'timestamp'  => $event->timestamp,
            ];
        }
    }

    protected function applySpanAttributeUpdated(SpanAttributeUpdated $event): void
    {
        if (isset($this->spans[$event->spanId])) {
            $this->spans[$event->spanId]['attributes'][$event->key] = $event->value;
        }
    }

    protected function applyTraceCompleted(TraceCompleted $event): void
    {
        $this->endTime = $event->timestamp;
    }

    private function calculateDuration(float $endTime): float
    {
        return $endTime - $this->startTime;
    }

    private function getSpanCount(): int
    {
        return count($this->spans);
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    public function getRootSpans(): array
    {
        return array_filter($this->spans, fn ($span) => $span['parent_id'] === null);
    }

    public function getChildSpans(string $parentId): array
    {
        return array_filter($this->spans, fn ($span) => $span['parent_id'] === $parentId);
    }

    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    public function getDuration(): ?float
    {
        if ($this->endTime === null) {
            return null;
        }

        return $this->endTime - $this->startTime;
    }

    public function getTraceName(): string
    {
        return $this->traceName;
    }

    public function toArray(): array
    {
        return [
            'trace_id'   => $this->uuid(),
            'name'       => $this->traceName,
            'start_time' => $this->startTime,
            'end_time'   => $this->endTime,
            'duration'   => $this->getDuration(),
            'has_errors' => $this->hasErrors,
            'span_count' => count($this->spans),
            'spans'      => $this->spans,
        ];
    }
}
