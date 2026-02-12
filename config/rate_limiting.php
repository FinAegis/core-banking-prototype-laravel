<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the comprehensive rate limiting
    | system, including different limits for various endpoint types and
    | user trust levels.
    |
    */

    'enabled' => env('RATE_LIMITING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Basic Rate Limits
    |--------------------------------------------------------------------------
    |
    | Standard rate limits for different types of API endpoints.
    | Limits are specified as requests per window (in seconds).
    |
    */

    'limits' => [
        'auth' => [
            'limit'          => env('RATE_LIMIT_AUTH', 3),
            'window'         => env('RATE_LIMIT_AUTH_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_AUTH_BLOCK', 300),
        ],
        'transaction' => [
            'limit'          => env('RATE_LIMIT_TRANSACTION', 30),
            'window'         => env('RATE_LIMIT_TRANSACTION_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_TRANSACTION_BLOCK', 60),
        ],
        'query' => [
            'limit'          => env('RATE_LIMIT_QUERY', 100),
            'window'         => env('RATE_LIMIT_QUERY_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_QUERY_BLOCK', 30),
        ],
        'admin' => [
            'limit'          => env('RATE_LIMIT_ADMIN', 200),
            'window'         => env('RATE_LIMIT_ADMIN_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_ADMIN_BLOCK', 60),
        ],
        'public' => [
            'limit'          => env('RATE_LIMIT_PUBLIC', 60),
            'window'         => env('RATE_LIMIT_PUBLIC_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_PUBLIC_BLOCK', 30),
        ],
        'webhook' => [
            'limit'          => env('RATE_LIMIT_WEBHOOK', 1000),
            'window'         => env('RATE_LIMIT_WEBHOOK_WINDOW', 60),
            'block_duration' => env('RATE_LIMIT_WEBHOOK_BLOCK', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction-Specific Rate Limits
    |--------------------------------------------------------------------------
    |
    | Enhanced rate limiting for financial transactions, including
    | count limits, amount limits, and progressive delays.
    |
    */

    'transaction_limits' => [
        'deposit' => [
            'hourly_limit'        => env('TRANSACTION_DEPOSIT_HOURLY', 10),
            'daily_limit'         => env('TRANSACTION_DEPOSIT_DAILY', 50),
            'amount_limit_hourly' => env('TRANSACTION_DEPOSIT_AMOUNT_HOURLY', 100000), // $1000 in cents
            'amount_limit_daily'  => env('TRANSACTION_DEPOSIT_AMOUNT_DAILY', 1000000), // $10000 in cents
            'progressive_delay'   => env('TRANSACTION_DEPOSIT_DELAY', true),
        ],
        'withdraw' => [
            'hourly_limit'        => env('TRANSACTION_WITHDRAW_HOURLY', 5),
            'daily_limit'         => env('TRANSACTION_WITHDRAW_DAILY', 20),
            'amount_limit_hourly' => env('TRANSACTION_WITHDRAW_AMOUNT_HOURLY', 50000), // $500 in cents
            'amount_limit_daily'  => env('TRANSACTION_WITHDRAW_AMOUNT_DAILY', 500000), // $5000 in cents
            'progressive_delay'   => env('TRANSACTION_WITHDRAW_DELAY', true),
        ],
        'transfer' => [
            'hourly_limit'        => env('TRANSACTION_TRANSFER_HOURLY', 15),
            'daily_limit'         => env('TRANSACTION_TRANSFER_DAILY', 100),
            'amount_limit_hourly' => env('TRANSACTION_TRANSFER_AMOUNT_HOURLY', 200000), // $2000 in cents
            'amount_limit_daily'  => env('TRANSACTION_TRANSFER_AMOUNT_DAILY', 2000000), // $20000 in cents
            'progressive_delay'   => env('TRANSACTION_TRANSFER_DELAY', true),
        ],
        'convert' => [
            'hourly_limit'        => env('TRANSACTION_CONVERT_HOURLY', 20),
            'daily_limit'         => env('TRANSACTION_CONVERT_DAILY', 200),
            'amount_limit_hourly' => env('TRANSACTION_CONVERT_AMOUNT_HOURLY', 500000), // $5000 in cents
            'amount_limit_daily'  => env('TRANSACTION_CONVERT_AMOUNT_DAILY', 5000000), // $50000 in cents
            'progressive_delay'   => env('TRANSACTION_CONVERT_DELAY', false),
        ],
        'vote' => [
            'hourly_limit'      => env('TRANSACTION_VOTE_HOURLY', 50),
            'daily_limit'       => env('TRANSACTION_VOTE_DAILY', 100),
            'progressive_delay' => env('TRANSACTION_VOTE_DELAY', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for dynamic rate limiting that adjusts based on
    | system load, user trust level, and time of day.
    |
    */

    'dynamic' => [
        'enabled' => env('DYNAMIC_RATE_LIMITING_ENABLED', true),

        'system_load' => [
            'check_interval' => env('SYSTEM_LOAD_CHECK_INTERVAL', 30), // seconds
            'thresholds'     => [
                'low'      => env('SYSTEM_LOAD_LOW', 0.3),
                'medium'   => env('SYSTEM_LOAD_MEDIUM', 0.6),
                'high'     => env('SYSTEM_LOAD_HIGH', 0.8),
                'critical' => env('SYSTEM_LOAD_CRITICAL', 1.0),
            ],
            'multipliers' => [
                'low'      => env('SYSTEM_LOAD_MULTIPLIER_LOW', 1.5),
                'medium'   => env('SYSTEM_LOAD_MULTIPLIER_MEDIUM', 1.0),
                'high'     => env('SYSTEM_LOAD_MULTIPLIER_HIGH', 0.7),
                'critical' => env('SYSTEM_LOAD_MULTIPLIER_CRITICAL', 0.4),
            ],
        ],

        'user_trust' => [
            'cache_duration' => env('USER_TRUST_CACHE_DURATION', 3600), // 1 hour
            'multipliers'    => [
                'new'      => env('USER_TRUST_NEW', 0.5),
                'basic'    => env('USER_TRUST_BASIC', 1.0),
                'verified' => env('USER_TRUST_VERIFIED', 1.5),
                'premium'  => env('USER_TRUST_PREMIUM', 2.0),
                'vip'      => env('USER_TRUST_VIP', 3.0),
            ],
            'criteria' => [
                'basic_age_days'             => env('USER_TRUST_BASIC_AGE', 30),
                'basic_transaction_count'    => env('USER_TRUST_BASIC_TRANSACTIONS', 10),
                'verified_age_days'          => env('USER_TRUST_VERIFIED_AGE', 90),
                'verified_transaction_count' => env('USER_TRUST_VERIFIED_TRANSACTIONS', 100),
                'premium_age_days'           => env('USER_TRUST_PREMIUM_AGE', 180),
                'premium_transaction_count'  => env('USER_TRUST_PREMIUM_TRANSACTIONS', 500),
                'vip_age_days'               => env('USER_TRUST_VIP_AGE', 365),
                'vip_transaction_count'      => env('USER_TRUST_VIP_TRANSACTIONS', 1000),
                'max_violations'             => env('USER_TRUST_MAX_VIOLATIONS', 5),
            ],
        ],

        'time_of_day' => [
            'business_hours_start'      => env('BUSINESS_HOURS_START', 9),
            'business_hours_end'        => env('BUSINESS_HOURS_END', 17),
            'business_hours_multiplier' => env('BUSINESS_HOURS_MULTIPLIER', 1.2),
            'evening_hours_multiplier'  => env('EVENING_HOURS_MULTIPLIER', 1.0),
            'night_hours_multiplier'    => env('NIGHT_HOURS_MULTIPLIER', 0.8),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for caching rate limit data and system metrics.
    |
    */

    'cache' => [
        'driver'      => env('RATE_LIMIT_CACHE_DRIVER', 'redis'),
        'prefix'      => env('RATE_LIMIT_CACHE_PREFIX', 'rate_limit:'),
        'default_ttl' => env('RATE_LIMIT_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring rate limit violations and system health.
    |
    */

    'monitoring' => [
        'enabled'          => env('RATE_LIMIT_MONITORING_ENABLED', true),
        'log_violations'   => env('RATE_LIMIT_LOG_VIOLATIONS', true),
        'alert_thresholds' => [
            'violation_rate' => env('RATE_LIMIT_ALERT_VIOLATION_RATE', 0.1), // 10%
            'system_load'    => env('RATE_LIMIT_ALERT_SYSTEM_LOAD', 0.8), // 80%
        ],
        'metrics_retention' => env('RATE_LIMIT_METRICS_RETENTION', 86400), // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Features
    |--------------------------------------------------------------------------
    |
    | Enhanced security features for rate limiting system.
    |
    */

    'security' => [
        'ip_whitelist'              => array_filter(explode(',', env('RATE_LIMIT_IP_WHITELIST', ''))),
        'user_whitelist'            => array_filter(explode(',', env('RATE_LIMIT_USER_WHITELIST', ''))),
        'strict_mode'               => env('RATE_LIMIT_STRICT_MODE', false),
        'block_suspicious_patterns' => env('RATE_LIMIT_BLOCK_SUSPICIOUS', true),
        'max_violations_per_day'    => env('RATE_LIMIT_MAX_VIOLATIONS_DAY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limit response headers.
    |
    */

    'headers' => [
        'include_headers'    => env('RATE_LIMIT_INCLUDE_HEADERS', true),
        'header_prefix'      => env('RATE_LIMIT_HEADER_PREFIX', 'X-RateLimit-'),
        'include_debug_info' => env('RATE_LIMIT_DEBUG_HEADERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | BaaS Partner Tier Rate Limiting
    |--------------------------------------------------------------------------
    |
    | When a request carries a BaaS partner binding (set by PartnerAuthMiddleware),
    | per-minute limits are derived from the partner's PartnerTier enum
    | (60 / 300 / 1000 req/min) multiplied by the type multiplier below.
    | Monthly limits are enforced via PartnerUsageMeteringService.
    |
    */

    'partner_tiers' => [
        'enabled' => env('PARTNER_TIER_RATE_LIMITING', true),
        'type_multipliers' => [
            'query'       => 1.0,
            'transaction' => 0.5,
            'auth'        => 0.1,
            'webhook'     => 2.0,
            'admin'       => 1.0,
            'public'      => 1.0,
        ],
        'enforce_monthly_limits' => env('PARTNER_ENFORCE_MONTHLY_LIMITS', true),
    ],

];
