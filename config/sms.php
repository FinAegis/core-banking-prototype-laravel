<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the SMS sending service. Currently supports VertexSMS
    | as the provider, with x402/MPP multi-rail payment gating.
    |
    */

    /*
    | Master switch for outbound SMS (POST /api/v1/sms/send and the MCP
    | sms.send tool). Secure by default: on only for APP_ENV=local, testing
    | or demo; every other value (production, staging, unset, a typo) is
    | treated as production and turns it off. An explicit SMS_ENABLED value
    | always wins.
    */
    'enabled' => (bool) env('SMS_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),

    /*
    | Per-user limits for unpaid POST /api/v1/sms/send requests (named rate
    | limiter `sms-send`, keyed by the authenticated user, counts requests).
    | The daily limits count billed SMS parts (GSM-7: 160 characters in one
    | part, 153 per part when split; otherwise 70 and 67). The route also
    | keeps its 60/minute throttle.
    |
    | paid_per_user_per_day: billed parts a paid send may use per UTC day,
    | per user, or per payer (x402 payer address, otherwise client IP) when
    | the paid request has no signed-in user. 429 ERR_SMS_001 over it.
    */
    'rate_limit' => [
        'per_user_per_minute'   => (int) env('SMS_SEND_PER_USER_PER_MINUTE', 5),
        'per_user_per_day'      => (int) env('SMS_SEND_PER_USER_PER_DAY', 50),
        'paid_per_user_per_day' => (int) env('SMS_PAID_SEND_PER_USER_PER_DAY', 500),
    ],

    /*
    | Global cap on unpaid billed parts per UTC day (all users together, REST
    | and the MCP sms.send tool; sends paid through the MPP or x402 gate do
    | not count). Over the cap POST /api/v1/sms/send answers 429 ERR_SMS_001
    | and MCP sms.send is refused until 00:00 UTC. 0 allows no unpaid sends.
    | SMS_SEND_PER_USER_PER_DAY above is also counted across REST and MCP.
    */
    'global_daily_limit' => (int) env('SMS_SEND_GLOBAL_PER_DAY', 500),

    /*
    | Smallest payment (atomic USDC, 6 decimals; 10000 = $0.01) that counts
    | as a paid send on POST /api/v1/sms/send. A smaller settled payment gets
    | the unpaid rules (token, per-user limits, global cap), and an MPP
    | resource priced below it is not offered. Paid sends still count against
    | SMS_PAID_SEND_PER_USER_PER_DAY.
    */
    'min_paid_price_usdc' => (int) env('SMS_MIN_PAID_PRICE_USDC', 10000),

    'default_provider' => env('SMS_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver'  => 'mock',
            'enabled' => true,
        ],
        'vertexsms' => [
            'driver'    => 'vertexsms',
            'api_token' => env('VERTEXSMS_API_TOKEN', ''),
            'base_url'  => env('VERTEXSMS_BASE_URL', 'https://kube-api.vertexsms.com'),
            'sender_id' => env('VERTEXSMS_SENDER_ID', 'Zelta'),
            'enabled'   => (bool) env('VERTEXSMS_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    'pricing' => [
        // Margin applied on top of provider rate (1.15 = 15% margin)
        'margin_multiplier' => (float) env('SMS_PRICING_MARGIN', 1.15),

        // EUR/USD conversion rate (updated periodically or via exchange rate service)
        'eur_usd_rate' => (float) env('SMS_EUR_USD_RATE', 1.08),

        // Fallback price in atomic USDC if rate lookup fails ($0.05)
        'fallback_usdc' => env('SMS_FALLBACK_PRICE_USDC', '50000'),

        // Cache TTL for rate card (seconds)
        'rate_cache_ttl' => (int) env('SMS_RATE_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        // HMAC shared secret for verifying DLR webhook body signatures (optional fallback).
        'secret' => env('VERTEXSMS_WEBHOOK_SECRET', ''),

        // Optional absolute DLR URL override. When empty, the named route `webhooks.vertexsms.dlr`
        // is used (auto-derived from APP_URL). Useful when Zelta sits behind a proxy or has multiple
        // edge domains and Vertex must post to a specific one.
        'dlr_url' => env('VERTEXSMS_DLR_URL', ''),

        // Opaque bearer token appended as `?t=<token>` on the dlrUrl we hand Vertex. Controller
        // accepts either this URL token OR a valid HMAC `X-VertexSMS-Signature` header.
        'dlr_url_token' => env('VERTEXSMS_DLR_URL_TOKEN', ''),

        // Allowed IPs for DLR webhook delivery (VertexSMS IP range)
        'allowed_ips' => array_filter(explode(',', env('VERTEXSMS_WEBHOOK_IPS', '178.33.133.192/28'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'sender_id'        => env('VERTEXSMS_SENDER_ID', 'Zelta'),
        'max_message_len'  => 1600,
        'test_mode'        => (bool) env('SMS_TEST_MODE', false),
        'send_interval_ms' => (int) env('SMS_SEND_INTERVAL_MS', 1000),
        'expire_seconds'   => (int) env('SMS_EXPIRE_SECONDS', 259200),
    ],
];
