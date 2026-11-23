# Monitoring & Observability

## Overview

FinAegis exports metrics in the Prometheus text format and has health endpoints for Kubernetes probes. It also records request traces through OpenTelemetry and reports projector lag. The code is in `app/Domain/Monitoring/` and `app/Http/Controllers/Api/MonitoringController.php`.

## Architecture

### How metrics are stored

`MetricsMiddleware` runs on every API request. It counts requests by status and method and keeps a running average of the response time. `MetricsCollector` stores these counters in the application cache. `PrometheusExporter` reads the cache and the database when Prometheus scrapes the endpoint.

The counters are not event-sourced. `MetricsAggregate` is an event-sourced aggregate that can record metrics and alerts as events, but the middleware and the collector do not use it.

### Components

1. **MetricsMiddleware** collects HTTP request metrics. It is in the API middleware group in `bootstrap/app.php`.
2. **MetricsCollector** records counters, workflow durations and custom metrics in the cache.
3. **PrometheusExporter** builds the Prometheus text output.
4. **HealthChecker** runs the health, readiness and liveness checks.
5. **TracingService** and `TracingMiddleware` create OpenTelemetry spans.
6. **ProjectorHealthService** reports projectors that lag behind the event store.
7. **MetricsAggregate** records metrics and alerts as events when code calls it directly.

## Metrics Types

The exporter publishes these metrics.

### Application metrics
- `http_requests_total`, by status and by method
- `http_request_duration_seconds`, the average response time as a gauge (not a histogram)
- `app_cache_hits_total` and `app_cache_misses_total`, plus `cache_operations_total` by result
- `app_memory_usage_bytes` and `app_uptime_seconds`

### Business metrics
- `app_users_total`
- `business_accounts_total`
- `business_transactions_total`

### Infrastructure metrics
- `infra_db_connections` and `infra_db_queries_total`
- `infra_queue_size`, `infra_queue_failed_total` and `queue_jobs_total` by status
- `database_queries_total`
- `events_processed_total` and `workflow_executions_total`
- `infra_redis_memory_bytes`, which is always `0` because the exporter does not read Redis yet

## API Endpoints

| Endpoint | Access | Returns |
|---|---|---|
| `GET /api/monitoring/metrics` | Metrics token or IP allowlist | Prometheus text |
| `GET /api/monitoring/prometheus` | Metrics token or IP allowlist | Prometheus text (same output) |
| `GET /api/monitoring/health` | Public | Health of all components |
| `GET /api/monitoring/ready` | Public | Readiness probe (`503` when not ready) |
| `GET /api/monitoring/alive` | Public | Liveness probe |
| `GET /api/monitoring/metrics-json` | Sanctum | Cached counters as JSON |
| `GET /api/monitoring/traces`, `GET /api/monitoring/trace/{traceId}` | Sanctum | Recorded traces |
| `GET /api/monitoring/alerts`, `PUT /api/monitoring/alerts/{alertId}/acknowledge` | Sanctum | Open alerts from `monitoring_alerts` |
| `GET /api/monitoring/projector-health`, `GET /api/monitoring/projector-health/stale` | Sanctum | Projector lag |

`MetricsAccessMiddleware` guards the two metrics endpoints. A request passes when it sends `Authorization: Bearer <METRICS_TOKEN>` or comes from an address in `METRICS_ALLOWED_IPS`. When neither is configured, the endpoints answer `403` in production and stay open in other environments.

### `/api/monitoring/metrics`
Returns metrics in Prometheus text format.

```
# HELP app_users_total Total number of users
# TYPE app_users_total gauge
app_users_total 150

# HELP http_request_duration_seconds HTTP request duration
# TYPE http_request_duration_seconds gauge
http_request_duration_seconds 0.125
```

### `/api/monitoring/health`
Returns the health status of all components.

```json
{
  "status": "healthy",
  "checks": [
    {
      "name": "database",
      "healthy": true,
      "message": "Database connection successful",
      "response_time": 0.012
    },
    {
      "name": "cache",
      "healthy": true,
      "message": "Cache is operational"
    }
  ],
  "timestamp": "2026-11-23T12:00:00Z"
}
```

### `/api/monitoring/ready`
Kubernetes readiness probe endpoint.

```json
{
  "ready": true,
  "checks": [
    {
      "name": "database",
      "healthy": true
    },
    {
      "name": "migrations",
      "healthy": true
    }
  ],
  "timestamp": "2026-11-23T12:00:00Z"
}
```

### `/api/monitoring/alive`
Kubernetes liveness probe endpoint.

```json
{
  "alive": true,
  "timestamp": "2026-11-23T12:00:00Z",
  "uptime": 3600.5,
  "memory_usage": 52428800
}
```

## Configuration

### Prometheus Scraping

Add this to your Prometheus configuration. Set `METRICS_TOKEN` in the app's `.env` and give Prometheus the same token.

```yaml
scrape_configs:
  - job_name: 'finaegis'
    static_configs:
      - targets: ['your-app-url:8000']
    metrics_path: '/api/monitoring/prometheus'
    authorization:
      type: Bearer
      credentials: '<METRICS_TOKEN>'
    scrape_interval: 30s
```

The Helm chart in `helm/finaegis` has a `ServiceMonitor` template that scrapes `/api/monitoring/prometheus`.

### Kubernetes Probes

The Helm chart already sets these probes in `helm/finaegis/values.yaml`. For a hand-written deployment:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: finaegis
spec:
  template:
    spec:
      containers:
      - name: app
        livenessProbe:
          httpGet:
            path: /api/monitoring/alive
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /api/monitoring/ready
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
```

## Grafana Dashboards and Alert Rules

Ready-made Grafana dashboards are in `infrastructure/observability/grafana/dashboards/`. There is one for the platform overview, one for platform health and one each for the accounts, wallet, exchange, compliance, treasury, lending, stablecoin and agent protocol domains, plus a financial reconciliation dashboard.

Prometheus alert rules are in `infrastructure/observability/prometheus/alerting_rules.yml`. Many rules use metric names that the app's exporter does not publish, for example `transaction_settlement_failures_total`, `treasury_nav_deviation` and `horizon_jobs_pending`. Check each rule against the output of `/api/monitoring/prometheus` before you rely on it.

A minimal dashboard built only from exported metrics looks like this:

```json
{
  "dashboard": {
    "title": "FinAegis Monitoring",
    "panels": [
      {
        "title": "Request Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total[5m])"
          }
        ]
      },
      {
        "title": "Average Response Time",
        "targets": [
          {
            "expr": "http_request_duration_seconds"
          }
        ]
      },
      {
        "title": "Active Users",
        "targets": [
          {
            "expr": "app_users_total"
          }
        ]
      }
    ]
  }
}
```

## Custom Metrics

### Recording Custom Metrics

```php
use App\Domain\Monitoring\Services\MetricsCollector;

$collector = app(MetricsCollector::class);

// Record HTTP request
$collector->recordHttpRequest('GET', '/api/users', 200, 0.125);

// Record business event
$collector->recordBusinessEvent('UserRegistered', ['plan' => 'premium']);

// Record workflow execution (name, status, duration in seconds)
$collector->recordWorkflowMetric(
    'LoanApplicationWorkflow',
    'completed',
    300.5
);
```

### Setting Alert Thresholds

```php
use App\Domain\Monitoring\ValueObjects\AlertLevel;

$collector->setAlertThreshold(
    'response_time',
    0.5, // threshold value
    AlertLevel::WARNING,
    '>' // operator
);
```

`setAlertThreshold()` only stores the threshold in the cache. Nothing reads it yet, so it does not raise alerts on its own.

## Alerts

Alerts come from two places. Prometheus raises alerts from its own rules and sends them through Alertmanager. Inside the app, `MetricsAggregate` records an `AlertTriggered` event when code reports a metric over a threshold, and the `monitoring_alerts` table holds alerts that the API lists and acknowledges.

`AlertLevel` defines four levels. An info alert is for information only. A warning needs attention, a critical alert needs action now and an emergency means a failure of the whole system.

Log messages at the `critical` level and above go to Slack when `LOG_SLACK_WEBHOOK_URL` is set. `LOG_SLACK_LEVEL` changes that level.

## Development

### Running Tests

```bash
# Test monitoring components
./vendor/bin/pest tests/Feature/Monitoring/
./vendor/bin/pest tests/Unit/Domain/Monitoring/
```

### Local Prometheus Setup

```bash
# Download Prometheus
wget https://github.com/prometheus/prometheus/releases/download/v2.45.0/prometheus-2.45.0.linux-amd64.tar.gz
tar xvf prometheus-2.45.0.linux-amd64.tar.gz

# Configure
cat > prometheus.yml <<EOF
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'finaegis'
    static_configs:
      - targets: ['localhost:8000']
    metrics_path: '/api/monitoring/prometheus'
EOF

# Run
./prometheus --config.file=prometheus.yml
```

In a local environment without `METRICS_TOKEN` or `METRICS_ALLOWED_IPS`, the metrics endpoints are open, so no token is needed.

### Local Grafana Setup

```bash
# Run with Docker
docker run -d -p 3000:3000 grafana/grafana

# Access at http://localhost:3000
# Default credentials: admin/admin
# Add Prometheus data source: http://host.docker.internal:9090
```

## Best Practices

1. Set alert thresholds that match your service levels.
2. Watch business metrics as well as technical ones.
3. Review the metrics and thresholds regularly.
4. Group related metrics on one dashboard.
5. Keep low-priority alerts few, so important ones are not missed.
6. Use the metrics to plan capacity before you need it.

## Troubleshooting

### Metrics Not Appearing

1. Check that the cache store works, because the counters live in the cache.
2. Check that `MetricsMiddleware` is still in the API middleware group in `bootstrap/app.php`.
3. Check that Prometheus sends the right `METRICS_TOKEN`, or that its address is in `METRICS_ALLOWED_IPS`. A `403` means neither matched.

### Health Check Failures

1. Check database connectivity.
2. Check that Redis is running.
3. Check that all migrations have run.
4. Check storage permissions.

### High Memory Usage

1. Review the Prometheus retention settings.
2. Check for too many label combinations in custom metrics.

## Future Enhancements

- [ ] Publish the metrics that the alert rules expect
- [ ] Read Redis memory for `infra_redis_memory_bytes`
- [ ] Raise alerts from `setAlertThreshold()` values
- [ ] Automated anomaly detection
- [ ] SLA compliance reporting
- [ ] Multi-tenant metrics isolation
