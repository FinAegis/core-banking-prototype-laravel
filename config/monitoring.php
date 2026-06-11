<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring, metrics, and tracing for the FinAegis platform.
    |
    */

    'metrics' => [
        'enabled'         => env('MONITORING_METRICS_ENABLED', true),
        'export_interval' => env('MONITORING_METRICS_INTERVAL', 30), // seconds
        'buffer_size'     => env('MONITORING_METRICS_BUFFER', 1000),
        'prometheus'      => [
            'enabled'   => env('PROMETHEUS_ENABLED', true),
            'namespace' => env('PROMETHEUS_NAMESPACE', 'finaegis'),
            'labels'    => [
                'environment' => env('APP_ENV', 'production'),
                'instance'    => env('APP_INSTANCE', gethostname()),
            ],
        ],
    ],

    // Access control for the metrics endpoints (/api/monitoring/{metrics,prometheus},
    // /api/metrics/prometheus). See App\Http\Middleware\MetricsAccessMiddleware.
    'metrics_token'       => env('METRICS_TOKEN'),
    'metrics_allowed_ips' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('METRICS_ALLOWED_IPS', ''))),
        static fn (string $ip): bool => $ip !== ''
    )),

    'health' => [
        'checks' => [
            'database' => true,
            'cache'    => true,
            'queue'    => true,
            'storage'  => true,
            'redis'    => true,
        ],
        'cache_ttl' => env('HEALTH_CHECK_CACHE_TTL', 10), // seconds
    ],

    'tracing' => [
        'enabled'       => env('TRACING_ENABLED', false),
        'otlp_endpoint' => env('OTLP_ENDPOINT', 'http://localhost:4318/v1/traces'),
        'otlp_headers'  => [
            'Authorization' => env('OTLP_AUTH_HEADER', ''),
        ],
        'sample_rate'    => env('TRACING_SAMPLE_RATE', 1.0), // 1.0 = 100%
        'max_attributes' => env('TRACING_MAX_ATTRIBUTES', 128),
        'max_events'     => env('TRACING_MAX_EVENTS', 128),
        'max_links'      => env('TRACING_MAX_LINKS', 128),
        'span_limits'    => [
            'attribute_value_length' => 12000,
            'attribute_count'        => 128,
            'event_count'            => 128,
            'link_count'             => 128,
        ],
    ],

    // Alerting goes through the logging stack: the 'slack' channel in config/logging.php
    // routes Log::critical()/emergency() to Slack when LOG_SLACK_WEBHOOK_URL is set.

    'logging' => [
        'structured'         => env('STRUCTURED_LOGGING', true),
        'include_trace_id'   => env('LOG_TRACE_ID', true),
        'include_span_id'    => env('LOG_SPAN_ID', true),
        'include_request_id' => env('LOG_REQUEST_ID', true),
        'include_domain'     => env('LOG_DOMAIN_CONTEXT', true),
        'format'             => env('LOG_FORMAT', 'json'),
        'elk'                => [
            'enabled'            => env('ELK_ENABLED', false),
            'elasticsearch_host' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
            'logstash_host'      => env('LOGSTASH_HOST', 'localhost'),
            'logstash_port'      => env('LOGSTASH_PORT', 5000),
        ],
    ],

    'dashboards' => [
        'grafana' => [
            'enabled' => env('GRAFANA_ENABLED', false),
            'url'     => env('GRAFANA_URL', 'http://localhost:3000'),
            'api_key' => env('GRAFANA_API_KEY', ''),
        ],
    ],
];
