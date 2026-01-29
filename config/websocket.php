<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting, batching, and performance settings for
    | real-time WebSocket broadcasting to tenant-specific channels.
    |
    */

    'enabled' => env('WEBSOCKET_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Control how frequently broadcast events can be sent per channel.
    | This prevents flooding clients with too many updates.
    |
    */

    'rate_limiting' => [
        // Order book updates - max 10 updates/second per trading pair
        'order_book' => [
            'max_per_second'  => env('WS_ORDER_BOOK_MAX_PER_SECOND', 10),
            'batch_window_ms' => env('WS_ORDER_BOOK_BATCH_WINDOW_MS', 100),
        ],

        // Trade executions - max 50 updates/second
        'trades' => [
            'max_per_second'  => env('WS_TRADES_MAX_PER_SECOND', 50),
            'batch_window_ms' => env('WS_TRADES_BATCH_WINDOW_MS', 50),
        ],

        // Portfolio/NAV updates - max 1 update/second per account
        'portfolio' => [
            'max_per_second'  => env('WS_PORTFOLIO_MAX_PER_SECOND', 1),
            'batch_window_ms' => env('WS_PORTFOLIO_BATCH_WINDOW_MS', 1000),
        ],

        // Balance updates - max 5 updates/second per account
        'balance' => [
            'max_per_second'  => env('WS_BALANCE_MAX_PER_SECOND', 5),
            'batch_window_ms' => env('WS_BALANCE_BATCH_WINDOW_MS', 200),
        ],

        // Transaction notifications - no batching needed
        'transactions' => [
            'max_per_second'  => env('WS_TRANSACTIONS_MAX_PER_SECOND', 20),
            'batch_window_ms' => env('WS_TRANSACTIONS_BATCH_WINDOW_MS', 50),
        ],

        // Compliance alerts (admin only) - no rate limit
        'compliance' => [
            'max_per_second'  => env('WS_COMPLIANCE_MAX_PER_SECOND', 100),
            'batch_window_ms' => env('WS_COMPLIANCE_BATCH_WINDOW_MS', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Batching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for batching multiple updates into single broadcasts.
    |
    */

    'batching' => [
        // Enable update batching to reduce message frequency
        'enabled' => env('WS_BATCHING_ENABLED', true),

        // Maximum events to batch before forcing a broadcast
        'max_batch_size' => env('WS_MAX_BATCH_SIZE', 100),

        // Force broadcast after this many milliseconds regardless of batch size
        'max_delay_ms' => env('WS_MAX_DELAY_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Define the channel structure for tenant-scoped broadcasting.
    |
    */

    'channels' => [
        // Exchange/Trading updates
        'exchange' => [
            'suffix' => 'exchange',
            'events' => [
                'orderbook.updated',
                'trade.executed',
                'order.placed',
                'order.cancelled',
                'order.filled',
            ],
        ],

        // Account updates
        'accounts' => [
            'suffix' => 'accounts',
            'events' => [
                'balance.updated',
                'portfolio.updated',
                'nav.calculated',
            ],
        ],

        // Transaction feed
        'transactions' => [
            'suffix' => 'transactions',
            'events' => [
                'transaction.credited',
                'transaction.debited',
                'transaction.pending',
            ],
        ],

        // Compliance alerts (admin only)
        'compliance' => [
            'suffix' => 'compliance',
            'events' => [
                'alert.created',
                'review.required',
                'threshold.exceeded',
            ],
        ],

        // Multi-sig wallet updates
        'wallet' => [
            'suffix' => 'wallet.multi-sig',
            'events' => [
                'approval.created',
                'signature.submitted',
                'approval.completed',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue for broadcast events.
    |
    */

    'queue' => [
        // Queue name for broadcast events
        'name' => env('WS_QUEUE_NAME', 'broadcasts'),

        // Queue connection
        'connection' => env('WS_QUEUE_CONNECTION', 'redis'),

        // Number of retries for failed broadcasts
        'tries' => env('WS_QUEUE_TRIES', 3),

        // Retry delay in seconds
        'retry_after' => env('WS_QUEUE_RETRY_AFTER', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Soketi Configuration
    |--------------------------------------------------------------------------
    |
    | Soketi-specific settings (Pusher-compatible WebSocket server).
    |
    */

    'soketi' => [
        'host'            => env('SOKETI_HOST', '127.0.0.1'),
        'port'            => env('SOKETI_PORT', 6001),
        'metrics_port'    => env('SOKETI_METRICS_PORT', 9601),
        'metrics_enabled' => env('SOKETI_METRICS_ENABLED', true),
    ],
];
