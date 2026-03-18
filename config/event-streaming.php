<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Event Streaming Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how domain events are published to Redis Streams for
    | real-time processing and consumer group consumption.
    |
    */

    'enabled' => env('EVENT_STREAMING_ENABLED', false),

    'connection' => env('EVENT_STREAMING_REDIS_CONNECTION', 'default'),

    /*
     * Stream key prefix for all domain event streams.
     */
    'prefix' => env('EVENT_STREAMING_PREFIX', 'finaegis:events'),

    /*
     * Maximum stream length (MAXLEN). Older entries are trimmed.
     * Set to 0 for unlimited (not recommended in production).
     */
    'max_stream_length' => env('EVENT_STREAMING_MAX_LENGTH', 100000),

    /*
     * Default consumer group for event processing.
     */
    'consumer_group' => env('EVENT_STREAMING_CONSUMER_GROUP', 'finaegis-consumers'),

    /*
     * Consumer idle timeout in milliseconds.
     * After this time, pending messages can be claimed by other consumers.
     */
    'consumer_idle_timeout' => env('EVENT_STREAMING_IDLE_TIMEOUT', 30000),

    /*
     * Block timeout for XREADGROUP in milliseconds.
     */
    'block_timeout' => env('EVENT_STREAMING_BLOCK_TIMEOUT', 5000),

    /*
     * Number of messages to read per batch.
     */
    'batch_size' => env('EVENT_STREAMING_BATCH_SIZE', 100),

    /*
     * Domain-specific stream mappings.
     * Each domain gets its own stream for isolated processing.
     */
    'streams' => [
        'account'        => 'account-events',
        'exchange'       => 'exchange-events',
        'wallet'         => 'wallet-events',
        'compliance'     => 'compliance-events',
        'lending'        => 'lending-events',
        'treasury'       => 'treasury-events',
        'payment'        => 'payment-events',
        'fraud'          => 'fraud-events',
        'mobile'         => 'mobile-events',
        'mobile-payment' => 'mobile-payment-events',
        'trust-cert'     => 'trust-cert-events',
        'cross-chain'    => 'cross-chain-events',
        'defi'           => 'defi-events',
        'stablecoin'     => 'stablecoin-events',
        'privacy'        => 'privacy-events',
    ],

    /*
     * Retention policy for stream data.
     */
    'retention' => [
        'strategy'  => env('EVENT_STREAMING_RETENTION', 'maxlen'),
        'ttl_hours' => env('EVENT_STREAMING_TTL_HOURS', 168), // 7 days
    ],

    /*
     * Dead letter queue configuration.
     * Failed messages are routed to per-domain DLQ streams.
     */
    'dead_letter' => [
        'max_retries' => env('EVENT_STREAMING_DLQ_MAX_RETRIES', 3),
        'max_length'  => env('EVENT_STREAMING_DLQ_MAX_LENGTH', 10000),
    ],

    /*
     * Backpressure handling configuration.
     * Controls when consumers are paused/resumed based on pending message count.
     */
    'backpressure' => [
        'warning_threshold'  => env('EVENT_STREAMING_BACKPRESSURE_WARNING', 1000),
        'critical_threshold' => env('EVENT_STREAMING_BACKPRESSURE_CRITICAL', 5000),
        'resume_threshold'   => env('EVENT_STREAMING_BACKPRESSURE_RESUME', 500),
    ],

    /*
     * Schema validation for published events.
     */
    'schema_validation' => [
        'enabled' => env('EVENT_STREAMING_SCHEMA_VALIDATION', true),
    ],
];
