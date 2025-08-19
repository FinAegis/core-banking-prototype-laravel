<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Aggregates\MonitoringAggregate;
use App\Domain\Monitoring\Repositories\MonitoringAggregateRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DistributedTracer
{
    private MonitoringAggregateRepository $repository;

    private MetricsCollector $metricsCollector;

    private array $activeSpans = [];

    private ?string $currentTraceId = null;

    public function __construct(
        MonitoringAggregateRepository $repository,
        MetricsCollector $metricsCollector
    ) {
        $this->repository = $repository;
        $this->metricsCollector = $metricsCollector;
    }

    /**
     * Start a new trace.
     */
    public function startTrace(string $operationName, array $tags = []): string
    {
        $traceId = $this->generateTraceId();
        $spanId = $this->generateSpanId();

        $this->currentTraceId = $traceId;

        $span = [
            'traceId'       => $traceId,
            'spanId'        => $spanId,
            'parentSpanId'  => null,
            'operationName' => $operationName,
            'startTime'     => microtime(true),
            'tags'          => array_merge($tags, [
                'service'     => config('app.name'),
                'environment' => config('app.env'),
                'version'     => config('app.version', '1.0.0'),
            ]),
            'logs'    => [],
            'baggage' => [],
        ];

        $this->activeSpans[$spanId] = $span;

        // Store trace context for distributed tracing
        Cache::put("trace:{$traceId}", [
            'traceId'       => $traceId,
            'rootSpanId'    => $spanId,
            'startTime'     => $span['startTime'],
            'operationName' => $operationName,
        ], now()->addHours(1));

        return $spanId;
    }

    /**
     * Start a new span within the current trace.
     */
    public function startSpan(string $operationName, ?string $parentSpanId = null, array $tags = []): string
    {
        if (! $this->currentTraceId) {
            // Start a new trace if none exists
            return $this->startTrace($operationName, $tags);
        }

        $spanId = $this->generateSpanId();

        // Find parent span if not specified
        if (! $parentSpanId && ! empty($this->activeSpans)) {
            $parentSpanId = array_key_last($this->activeSpans);
        }

        $span = [
            'traceId'       => $this->currentTraceId,
            'spanId'        => $spanId,
            'parentSpanId'  => $parentSpanId,
            'operationName' => $operationName,
            'startTime'     => microtime(true),
            'tags'          => $tags,
            'logs'          => [],
            'baggage'       => $parentSpanId && isset($this->activeSpans[$parentSpanId])
                ? $this->activeSpans[$parentSpanId]['baggage']
                : [],
        ];

        $this->activeSpans[$spanId] = $span;

        return $spanId;
    }

    /**
     * Finish a span.
     */
    public function finishSpan(string $spanId, array $tags = []): void
    {
        if (! isset($this->activeSpans[$spanId])) {
            return;
        }

        $span = $this->activeSpans[$spanId];
        $duration = microtime(true) - $span['startTime'];

        // Merge additional tags
        $span['tags'] = array_merge($span['tags'], $tags);

        // Record span in event store
        $aggregate = $this->getOrCreateAggregate();
        $aggregate->recordTracingSpan(
            $span['traceId'],
            $span['spanId'],
            $span['operationName'],
            $duration * 1000, // Convert to milliseconds
            $span['tags'],
            $span['parentSpanId']
        );
        $this->repository->store($aggregate);

        // Record metrics
        $this->metricsCollector->recordHistogram(
            'trace_span_duration_ms',
            $duration * 1000,
            [0.5, 1, 5, 10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000],
            [
                'operation' => $span['operationName'],
                'service'   => $span['tags']['service'] ?? 'unknown',
            ]
        );

        // Store span data for analysis
        $this->storeSpanData($span, $duration);

        // Remove from active spans
        unset($this->activeSpans[$spanId]);

        // If this was the root span, clear the current trace
        $traceContext = Cache::get("trace:{$span['traceId']}");
        if ($traceContext && $traceContext['rootSpanId'] === $spanId) {
            $this->currentTraceId = null;
        }
    }

    /**
     * Add a log entry to a span.
     */
    public function log(string $spanId, string $message, array $fields = []): void
    {
        if (! isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['logs'][] = [
            'timestamp' => microtime(true),
            'message'   => $message,
            'fields'    => $fields,
        ];
    }

    /**
     * Add a tag to a span.
     */
    public function addTag(string $spanId, string $key, $value): void
    {
        if (! isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['tags'][$key] = $value;

        // Special handling for error tags
        if ($key === 'error' && $value === true) {
            $this->metricsCollector->incrementCounter('trace_span_errors_total', 1, [
                'operation' => $this->activeSpans[$spanId]['operationName'],
            ]);
        }
    }

    /**
     * Set baggage item for a span (propagated to child spans).
     */
    public function setBaggageItem(string $spanId, string $key, $value): void
    {
        if (! isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['baggage'][$key] = $value;
    }

    /**
     * Get baggage item from a span.
     */
    public function getBaggageItem(string $spanId, string $key)
    {
        if (! isset($this->activeSpans[$spanId])) {
            return null;
        }

        return $this->activeSpans[$spanId]['baggage'][$key] ?? null;
    }

    /**
     * Inject trace context for distributed tracing.
     */
    public function inject(array &$carrier): void
    {
        if (! $this->currentTraceId || empty($this->activeSpans)) {
            return;
        }

        $currentSpan = end($this->activeSpans);

        $carrier['X-Trace-Id'] = $currentSpan['traceId'];
        $carrier['X-Span-Id'] = $currentSpan['spanId'];
        $carrier['X-Parent-Span-Id'] = $currentSpan['parentSpanId'] ?? '';

        // Include baggage
        foreach ($currentSpan['baggage'] as $key => $value) {
            $carrier["X-Baggage-{$key}"] = $value;
        }
    }

    /**
     * Extract trace context from carrier.
     */
    public function extract(array $carrier): ?array
    {
        if (! isset($carrier['X-Trace-Id'])) {
            return null;
        }

        $context = [
            'traceId'      => $carrier['X-Trace-Id'],
            'spanId'       => $carrier['X-Span-Id'] ?? null,
            'parentSpanId' => $carrier['X-Parent-Span-Id'] ?? null,
            'baggage'      => [],
        ];

        // Extract baggage
        foreach ($carrier as $key => $value) {
            if (str_starts_with($key, 'X-Baggage-')) {
                $baggageKey = substr($key, 10);
                $context['baggage'][$baggageKey] = $value;
            }
        }

        return $context;
    }

    /**
     * Continue a trace from extracted context.
     */
    public function continueTrace(array $context, string $operationName, array $tags = []): string
    {
        $this->currentTraceId = $context['traceId'];

        $spanId = $this->generateSpanId();

        $span = [
            'traceId'       => $context['traceId'],
            'spanId'        => $spanId,
            'parentSpanId'  => $context['spanId'],
            'operationName' => $operationName,
            'startTime'     => microtime(true),
            'tags'          => $tags,
            'logs'          => [],
            'baggage'       => $context['baggage'] ?? [],
        ];

        $this->activeSpans[$spanId] = $span;

        return $spanId;
    }

    /**
     * Get trace summary for a given trace ID.
     */
    public function getTraceSummary(string $traceId): array
    {
        $spans = Cache::get("trace:spans:{$traceId}", []);

        if (empty($spans)) {
            return [
                'traceId'    => $traceId,
                'spanCount'  => 0,
                'duration'   => 0,
                'services'   => [],
                'operations' => [],
                'errorCount' => 0,
            ];
        }

        $minTime = PHP_FLOAT_MAX;
        $maxTime = 0;
        $services = [];
        $operations = [];
        $errorCount = 0;

        foreach ($spans as $span) {
            $startTime = $span['startTime'];
            $endTime = $startTime + $span['duration'] / 1000;

            $minTime = min($minTime, $startTime);
            $maxTime = max($maxTime, $endTime);

            $services[$span['tags']['service'] ?? 'unknown'] = true;
            $operations[$span['operationName']] = true;

            if (($span['tags']['error'] ?? false) === true) {
                $errorCount++;
            }
        }

        return [
            'traceId'    => $traceId,
            'spanCount'  => count($spans),
            'duration'   => ($maxTime - $minTime) * 1000, // Convert to milliseconds
            'services'   => array_keys($services),
            'operations' => array_keys($operations),
            'errorCount' => $errorCount,
        ];
    }

    private function generateTraceId(): string
    {
        return Str::uuid()->toString();
    }

    private function generateSpanId(): string
    {
        return Str::random(16);
    }

    private function storeSpanData(array $span, float $duration): void
    {
        $traceId = $span['traceId'];

        $spans = Cache::get("trace:spans:{$traceId}", []);
        $spans[$span['spanId']] = array_merge($span, [
            'duration' => $duration * 1000, // Store in milliseconds
            'endTime'  => microtime(true),
        ]);

        Cache::put("trace:spans:{$traceId}", $spans, now()->addHours(24));
    }

    private function getOrCreateAggregate(): MonitoringAggregate
    {
        $sessionId = Cache::get('monitoring:session:id', Str::uuid()->toString());

        $aggregate = $this->repository->findBySessionId($sessionId);

        if (! $aggregate) {
            $aggregate = MonitoringAggregate::fake(Str::uuid()->toString());
            $aggregate->startSession($sessionId, [
                'type'       => 'distributed_tracing',
                'started_at' => now(),
            ]);
        }

        return $aggregate;
    }
}
