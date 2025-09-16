# Monitoring & Observability

## Overview

The FinAegis platform includes comprehensive monitoring and observability features using Prometheus-compatible metrics export and health check endpoints for Kubernetes deployment.

## Architecture

### Event Sourcing with Metrics

All metrics are recorded as domain events using event sourcing, providing:
- Complete audit trail of all metrics
- Time-series data storage
- Replay capability for historical analysis
- Alert history tracking

### Components

1. **MetricsAggregate**: Event-sourced aggregate for recording metrics
2. **PrometheusExporter**: Exports metrics in Prometheus format
3. **MetricsCollector**: Collects various types of metrics
4. **HealthChecker**: Provides health, readiness, and liveness checks
5. **MetricsMiddleware**: Automatically collects HTTP request metrics

## Metrics Types

### Application Metrics
- HTTP request count and duration
- Error rates
- Cache hit/miss rates
- Memory usage
- Uptime

### Business Metrics
- Active accounts
- Transaction volumes
- Treasury allocations
- Active loans
- Exchange order volume

### Infrastructure Metrics
- Database connections
- Queue size and failed jobs
- Event sourcing events
- Redis memory usage

## API Endpoints

### `/api/monitoring/metrics`
Returns metrics in Prometheus text format.

```
# HELP app_users_total Total number of users
# TYPE app_users_total gauge
app_users_total 150

# HELP http_request_duration_seconds HTTP request duration
# TYPE http_request_duration_seconds histogram
http_request_duration_seconds{method="GET",route="api.users",status="200"} 0.125
```

### `/api/monitoring/health`
Returns comprehensive health status of all components.

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
  "timestamp": "2024-09-15T12:00:00Z"
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
  "timestamp": "2024-09-15T12:00:00Z"
}
```

### `/api/monitoring/alive`
Kubernetes liveness probe endpoint.

```json
{
  "alive": true,
  "timestamp": "2024-09-15T12:00:00Z",
  "uptime": 3600.5,
  "memory_usage": 52428800
}
```

## Configuration

### Prometheus Scraping

Add to your Prometheus configuration:

```yaml
scrape_configs:
  - job_name: 'finaegis'
    static_configs:
      - targets: ['your-app-url:8000']
    metrics_path: '/api/monitoring/metrics'
    scrape_interval: 30s
```

### Kubernetes Probes

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

## Grafana Dashboard

Import the following dashboard configuration for visualization:

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
        "title": "Response Time",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, http_request_duration_seconds)"
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

// Record workflow execution
$collector->recordWorkflowMetric(
    'LoanApplicationWorkflow',
    'completed',
    300.5,
    ['loan_amount' => 50000]
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

## Alerts

The system automatically triggers alerts when thresholds are exceeded:

1. **Warning Level**: Non-critical issues requiring attention
2. **Critical Level**: Immediate action required
3. **Emergency Level**: System-wide critical failures

Alerts are recorded as events and can be integrated with external alerting systems.

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
    metrics_path: '/api/monitoring/metrics'
EOF

# Run
./prometheus --config.file=prometheus.yml
```

### Local Grafana Setup

```bash
# Run with Docker
docker run -d -p 3000:3000 grafana/grafana

# Access at http://localhost:3000
# Default credentials: admin/admin
# Add Prometheus data source: http://host.docker.internal:9090
```

## Best Practices

1. **Use Event Sourcing**: All metrics are recorded as events for complete auditability
2. **Set Appropriate Thresholds**: Configure alerts based on your SLAs
3. **Monitor Business Metrics**: Track domain-specific metrics, not just technical ones
4. **Regular Review**: Periodically review metrics and adjust thresholds
5. **Dashboard Organization**: Group related metrics for easier analysis
6. **Alert Fatigue Prevention**: Avoid too many low-priority alerts
7. **Capacity Planning**: Use metrics for proactive scaling decisions

## Troubleshooting

### Metrics Not Appearing

1. Check Redis connection for Prometheus registry
2. Verify MetricsMiddleware is registered in kernel
3. Ensure cache driver is configured correctly

### Health Check Failures

1. Check database connectivity
2. Verify Redis is running
3. Ensure all migrations are run
4. Check storage permissions

### High Memory Usage

1. Review Prometheus retention settings
2. Check for metric cardinality explosion
3. Verify buffer sizes in MetricsCollector

## Future Enhancements

- [ ] Distributed tracing with OpenTelemetry
- [ ] Custom Grafana dashboards per domain
- [ ] Automated anomaly detection
- [ ] SLA compliance reporting
- [ ] Cost attribution metrics
- [ ] Multi-tenant metrics isolation