<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Sagas;

use App\Domain\Monitoring\Events\SpanEnded;
use App\Domain\Monitoring\Events\SpanErrorOccurred;
use App\Domain\Monitoring\Events\SpanStarted;
use App\Domain\Monitoring\Events\TraceCompleted;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\ValueObjects\AlertLevel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

class DistributedTracingSaga extends Reactor
{
    private const ALERT_THRESHOLD_DURATION = 5.0; // 5 seconds

    private const ALERT_THRESHOLD_ERROR_RATE = 0.1; // 10% error rate

    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly MetricsCollector $metricsCollector
    ) {
    }

    public function onSpanStarted(SpanStarted $event): void
    {
        // Track active spans
        $activeSpans = Cache::get('tracing:active_spans', []);
        $activeSpans[$event->spanId] = [
            'trace_id'   => $event->traceId,
            'parent_id'  => $event->parentSpanId,
            'name'       => $event->name,
            'start_time' => $event->timestamp,
        ];
        Cache::put('tracing:active_spans', $activeSpans, self::CACHE_TTL);

        // Record metric
        $this->metricsCollector->recordCustomMetric(
            'tracing.spans.started',
            1,
            ['span_name' => $event->name]
        );

        // Log for debugging
        Log::debug('Span started', [
            'trace_id' => $event->traceId,
            'span_id'  => $event->spanId,
            'name'     => $event->name,
        ]);
    }

    public function onSpanEnded(SpanEnded $event): void
    {
        // Remove from active spans
        $activeSpans = Cache::get('tracing:active_spans', []);
        $span = $activeSpans[$event->spanId] ?? null;

        if ($span) {
            $duration = $event->timestamp - $span['start_time'];

            // Check for slow spans
            if ($duration > self::ALERT_THRESHOLD_DURATION) {
                $this->triggerSlowSpanAlert($event->spanId, $span['name'], $duration);
            }

            // Record metrics
            $this->metricsCollector->recordCustomMetric(
                'tracing.spans.duration',
                $duration,
                [
                    'span_name' => $span['name'],
                    'status'    => $event->status,
                ]
            );

            unset($activeSpans[$event->spanId]);
            Cache::put('tracing:active_spans', $activeSpans, self::CACHE_TTL);
        }

        // Record completion metric
        $this->metricsCollector->recordCustomMetric(
            'tracing.spans.completed',
            1,
            [
                'status' => $event->status,
            ]
        );
    }

    public function onSpanErrorOccurred(SpanErrorOccurred $event): void
    {
        // Track error counts
        $errorKey = "tracing:errors:{$event->type}";
        $errorCount = (int) Cache::increment($errorKey);
        Cache::put($errorKey, $errorCount, self::CACHE_TTL);

        // Check error rate
        $this->checkErrorRate($event->type, $errorCount);

        // Record error metric
        $this->metricsCollector->recordCustomMetric(
            'tracing.errors',
            1,
            [
                'error_type' => $event->type,
                'span_id'    => $event->spanId,
            ]
        );

        // Log error
        Log::error('Span error occurred', [
            'trace_id' => $event->traceId,
            'span_id'  => $event->spanId,
            'error'    => $event->message,
            'type'     => $event->type,
        ]);

        // Send alert for critical errors
        if ($this->isCriticalError($event->type)) {
            $this->sendCriticalErrorAlert($event);
        }
    }

    public function onTraceCompleted(TraceCompleted $event): void
    {
        // Record trace metrics
        $this->metricsCollector->recordCustomMetric(
            'tracing.traces.completed',
            1,
            [
                'has_errors' => $event->hasErrors ? 'true' : 'false',
            ]
        );

        $this->metricsCollector->recordCustomMetric(
            'tracing.traces.duration',
            $event->duration,
            []
        );

        $this->metricsCollector->recordCustomMetric(
            'tracing.traces.span_count',
            $event->spanCount,
            []
        );

        // Check for anomalies
        if ($event->duration > self::ALERT_THRESHOLD_DURATION * 2) {
            $this->triggerSlowTraceAlert($event->traceId, $event->duration);
        }

        // Clean up any orphaned spans
        $this->cleanupOrphanedSpans($event->traceId);
    }

    private function triggerSlowSpanAlert(string $spanId, string $spanName, float $duration): void
    {
        $this->metricsCollector->setAlertThreshold(
            'span_duration',
            self::ALERT_THRESHOLD_DURATION,
            AlertLevel::WARNING,
            '>'
        );

        Log::warning('Slow span detected', [
            'span_id'   => $spanId,
            'span_name' => $spanName,
            'duration'  => $duration,
            'threshold' => self::ALERT_THRESHOLD_DURATION,
        ]);
    }

    private function triggerSlowTraceAlert(string $traceId, float $duration): void
    {
        $this->metricsCollector->setAlertThreshold(
            'trace_duration',
            self::ALERT_THRESHOLD_DURATION * 2,
            AlertLevel::CRITICAL,
            '>'
        );

        Log::warning('Slow trace detected', [
            'trace_id'  => $traceId,
            'duration'  => $duration,
            'threshold' => self::ALERT_THRESHOLD_DURATION * 2,
        ]);
    }

    private function checkErrorRate(string $errorType, int $errorCount): void
    {
        // Get total span count for the time window
        $totalSpans = Cache::get('tracing:total_spans', 0);

        if ($totalSpans > 0) {
            $errorRate = $errorCount / $totalSpans;

            if ($errorRate > self::ALERT_THRESHOLD_ERROR_RATE) {
                $this->metricsCollector->setAlertThreshold(
                    'error_rate',
                    self::ALERT_THRESHOLD_ERROR_RATE,
                    AlertLevel::CRITICAL,
                    '>'
                );

                Log::error('High error rate detected', [
                    'error_type' => $errorType,
                    'error_rate' => $errorRate,
                    'threshold'  => self::ALERT_THRESHOLD_ERROR_RATE,
                ]);
            }
        }
    }

    private function isCriticalError(string $errorType): bool
    {
        $criticalErrors = [
            'DatabaseException',
            'FatalError',
            'SecurityException',
            'PaymentException',
            'AuthenticationException',
        ];

        return in_array($errorType, $criticalErrors, true);
    }

    private function sendCriticalErrorAlert(SpanErrorOccurred $event): void
    {
        // In a production system, this would send to an alerting service
        Log::critical('Critical error in distributed trace', [
            'trace_id'   => $event->traceId,
            'span_id'    => $event->spanId,
            'error_type' => $event->type,
            'message'    => $event->message,
            'context'    => $event->context,
        ]);

        // Record critical alert
        $this->metricsCollector->recordCustomMetric(
            'alerts.critical',
            1,
            [
                'type'       => 'tracing_error',
                'error_type' => $event->type,
            ]
        );
    }

    private function cleanupOrphanedSpans(string $traceId): void
    {
        $activeSpans = Cache::get('tracing:active_spans', []);
        $orphaned = array_filter($activeSpans, fn ($span) => $span['trace_id'] === $traceId);

        if (! empty($orphaned)) {
            Log::warning('Orphaned spans detected', [
                'trace_id'       => $traceId,
                'orphaned_count' => count($orphaned),
                'span_ids'       => array_keys($orphaned),
            ]);

            // Remove orphaned spans
            foreach (array_keys($orphaned) as $spanId) {
                unset($activeSpans[$spanId]);
            }

            Cache::put('tracing:active_spans', $activeSpans, self::CACHE_TTL);
        }
    }
}
