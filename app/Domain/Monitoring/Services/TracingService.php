<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Aggregates\TraceAggregate;
use App\Domain\Monitoring\ValueObjects\SpanStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Throwable;

class TracingService
{
    private ?TracerInterface $tracer = null;

    private array $activeSpans = [];

    private ?TraceAggregate $currentTrace = null;

    public function __construct(
        ?TracerInterface $telemetryTracer = null
    ) {
        $this->tracer = $telemetryTracer;
    }

    public function startTrace(string $name, array $attributes = []): string
    {
        $traceId = Str::uuid()->toString();

        // Create event-sourced trace aggregate
        $this->currentTrace = TraceAggregate::createNew($traceId, $name);

        // Start OpenTelemetry span if available
        if ($this->tracer && $name !== '') {
            $span = $this->tracer->spanBuilder($name)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setAttributes($attributes)
                ->startSpan();

            $this->activeSpans[$traceId] = $span;

            // Make span active in context
            $scope = $span->activate();
        }

        // Record event
        $this->currentTrace->recordSpanStarted(
            $traceId,
            null,
            $name,
            $attributes,
            microtime(true)
        );

        return $traceId;
    }

    public function startSpan(string $name, ?string $parentSpanId = null, array $attributes = []): string
    {
        $spanId = Str::uuid()->toString();

        // Start OpenTelemetry span if available
        if ($this->tracer && $name !== '') {
            $builder = $this->tracer->spanBuilder($name)
                ->setAttributes($attributes);

            if ($parentSpanId && isset($this->activeSpans[$parentSpanId])) {
                $parentSpan = $this->activeSpans[$parentSpanId];
                $parentContext = $parentSpan->storeInContext(Context::getCurrent());
                $builder->setParent($parentContext);
            }

            $span = $builder->startSpan();
            $this->activeSpans[$spanId] = $span;
        }

        // Record event if we have an active trace
        if ($this->currentTrace) {
            $this->currentTrace->recordSpanStarted(
                $spanId,
                $parentSpanId,
                $name,
                $attributes,
                microtime(true)
            );
        }

        return $spanId;
    }

    public function endSpan(string $spanId, ?string $status = null, array $attributes = []): void
    {
        // End OpenTelemetry span if available
        if (isset($this->activeSpans[$spanId])) {
            $span = $this->activeSpans[$spanId];

            if ($attributes) {
                $span->setAttributes($attributes);
            }

            if ($status === 'error') {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            $span->end();
            unset($this->activeSpans[$spanId]);
        }

        // Record event if we have an active trace
        if ($this->currentTrace) {
            $this->currentTrace->recordSpanEnded(
                $spanId,
                SpanStatus::from($status ?? 'ok'),
                $attributes,
                microtime(true)
            );
        }
    }

    public function recordError(string $spanId, Throwable $error, array $context = []): void
    {
        // Record in OpenTelemetry span if available
        if (isset($this->activeSpans[$spanId])) {
            $span = $this->activeSpans[$spanId];
            $span->recordException($error, [
                'exception.escaped' => true,
                'exception.context' => json_encode($context),
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $error->getMessage());
        }

        // Record event if we have an active trace
        if ($this->currentTrace) {
            $this->currentTrace->recordSpanError(
                $spanId,
                $error->getMessage(),
                get_class($error),
                $error->getTraceAsString(),
                $context,
                microtime(true)
            );
        }

        // Log for debugging
        Log::error('Span error recorded', [
            'span_id' => $spanId,
            'error'   => $error->getMessage(),
            'context' => $context,
        ]);
    }

    public function addEvent(string $spanId, string $name, array $attributes = []): void
    {
        // Add event to OpenTelemetry span if available
        if (isset($this->activeSpans[$spanId])) {
            $span = $this->activeSpans[$spanId];
            $span->addEvent($name, $attributes);
        }

        // Could also record as domain event if needed
        if ($this->currentTrace) {
            $this->currentTrace->recordSpanEvent(
                $spanId,
                $name,
                $attributes,
                microtime(true)
            );
        }
    }

    public function setAttribute(string $spanId, string $key, mixed $value): void
    {
        // Set attribute on OpenTelemetry span if available
        if (isset($this->activeSpans[$spanId])) {
            $span = $this->activeSpans[$spanId];
            $span->setAttribute($key, $value);
        }

        // Could also update in aggregate if needed
        if ($this->currentTrace) {
            $this->currentTrace->updateSpanAttribute(
                $spanId,
                $key,
                $value
            );
        }
    }

    public function getCurrentTraceId(): ?string
    {
        if ($this->currentTrace) {
            return $this->currentTrace->uuid();
        }

        // Fallback to OpenTelemetry context if available
        if ($this->tracer) {
            $span = \OpenTelemetry\API\Trace\Span::getCurrent();
            if ($span->getContext()->isValid()) {
                return $span->getContext()->getTraceId();
            }
        }

        return null;
    }

    public function getCurrentSpanId(): ?string
    {
        // Get from OpenTelemetry context if available
        if ($this->tracer) {
            $span = \OpenTelemetry\API\Trace\Span::getCurrent();
            if ($span->getContext()->isValid()) {
                return $span->getContext()->getSpanId();
            }
        }

        // Fallback to last active span
        if (! empty($this->activeSpans)) {
            return array_key_last($this->activeSpans);
        }

        return null;
    }

    public function injectContext(array &$carrier): void
    {
        // Inject trace context for distributed tracing
        if ($this->tracer) {
            $propagator = \OpenTelemetry\API\Globals::propagator();
            $propagator->inject($carrier);
        }
    }

    public function extractContext(array $carrier): void
    {
        // Extract trace context from incoming request
        if ($this->tracer) {
            $propagator = \OpenTelemetry\API\Globals::propagator();
            $context = $propagator->extract($carrier);
            Context::storage()->attach($context);
        }
    }

    public function endTrace(): void
    {
        // End all remaining spans
        foreach (array_reverse($this->activeSpans) as $spanId => $span) {
            $this->endSpan($spanId);
        }

        // Persist the trace aggregate
        if ($this->currentTrace) {
            $this->currentTrace->persist();
            $this->currentTrace = null;
        }
    }
}
