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

    'enabled' => (bool) env('SMS_ENABLED', false),

    'default_provider' => env('SMS_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver'  => 'mock',
            'enabled' => true,
        ],
        'vertexsms' => [
            'driver'    => 'vertexsms',
            'api_token' => env('VERTEXSMS_API_TOKEN', ''),
            'base_url'  => env('VERTEXSMS_BASE_URL', 'https://api.vertexsms.com'),
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
        // Shared secret for verifying DLR webhook signatures
        'secret' => env('VERTEXSMS_WEBHOOK_SECRET', ''),

        // Allowed IPs for DLR webhook delivery (VertexSMS IP range)
        'allowed_ips' => array_filter(explode(',', env('VERTEXSMS_WEBHOOK_IPS', '178.33.133.192/28'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'sender_id'       => env('VERTEXSMS_SENDER_ID', 'Zelta'),
        'max_message_len' => 1600,
        'test_mode'       => (bool) env('SMS_TEST_MODE', false),
    ],
];
