# Distributed Tracing Implementation - FinAegis Platform

## Overview
Implemented comprehensive distributed tracing using OpenTelemetry with event sourcing, following DDD patterns.

## Key Components

### 1. Domain Layer (app/Domain/Monitoring/)
- **TracingService**: Core tracing service with OpenTelemetry integration
- **TraceAggregate**: Event-sourced aggregate for trace management
- **DistributedTracingSaga**: Saga for monitoring and alerting on trace events
- **Value Objects**: SpanStatus, SpanContext for type safety

### 2. Events (Event Sourcing)
- SpanStarted: Records when a span begins
- SpanEnded: Records span completion with status
- SpanErrorOccurred: Captures errors within spans
- SpanEventRecorded: Custom events within spans
- SpanAttributeUpdated: Dynamic attribute updates
- TraceCompleted: Marks trace completion

### 3. Infrastructure
- **TracingMiddleware**: Automatic HTTP request tracing
- **TracingServiceProvider**: Service registration and configuration
- **OpenTelemetry Integration**: OTLP export support

## Configuration (config/monitoring.php)
```php
'tracing' => [
    'enabled' => env('TRACING_ENABLED', false),
    'otlp_endpoint' => env('OTLP_ENDPOINT', 'http://localhost:4318/v1/traces'),
    'sample_rate' => env('TRACING_SAMPLE_RATE', 1.0),
]
```

## Usage Patterns

### Manual Tracing
```php
$tracingService = app(TracingService::class);

// Start a trace
$traceId = $tracingService->startTrace('payment-processing', [
    'user_id' => $userId,
    'amount' => $amount,
]);

// Start child spans
$validationSpan = $tracingService->startSpan('validate-payment', $traceId);
// ... validation logic
$tracingService->endSpan($validationSpan);

// Handle errors
try {
    // ... processing
} catch (\Exception $e) {
    $tracingService->recordError($traceId, $e, ['context' => 'payment']);
    $tracingService->endSpan($traceId, 'error');
}
```

### Automatic HTTP Tracing
Applied via middleware to all API routes:
```php
Route::middleware(['tracing'])->group(function () {
    // Routes automatically traced
});
```

### Saga-Based Monitoring
The DistributedTracingSaga automatically:
- Tracks active spans
- Monitors for slow operations (>5s threshold)
- Alerts on high error rates (>10%)
- Cleans up orphaned spans
- Records metrics for Prometheus export

## Event Sourcing Integration
All traces are stored as events, providing:
- Complete audit trail
- Replay capability
- Time-travel debugging
- Performance analysis

## Testing
Comprehensive test coverage includes:
- Unit tests for TracingService
- Aggregate tests for TraceAggregate
- Integration tests for middleware
- Saga behavior tests

## Best Practices
1. Always end spans explicitly to avoid orphans
2. Use structured attributes for better filtering
3. Record errors with context for debugging
4. Set appropriate sampling rates for production
5. Monitor trace duration and error rates
6. Use parent-child relationships for distributed operations

## Integration Points
- Works with existing MetricsCollector for unified monitoring
- Compatible with PrometheusExporter for metrics
- Integrates with HealthChecker for system status
- Supports all domain workflows and sagas

## Future Enhancements
- Add trace sampling strategies
- Implement trace context propagation for async jobs
- Add support for W3C Trace Context standard
- Create Grafana dashboards for trace visualization
- Add anomaly detection for trace patterns