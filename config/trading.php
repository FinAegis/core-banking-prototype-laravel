<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trading Exchange Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the FinAegis Trading Exchange platform
    |
    */

    'enabled' => env('TRADING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Order Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'min_order' => [
            'BTC' => env('TRADING_MIN_ORDER_BTC', '0.0001'),
            'ETH' => env('TRADING_MIN_ORDER_ETH', '0.001'),
            'EUR' => env('TRADING_MIN_ORDER_FIAT', '10'),
            'USD' => env('TRADING_MIN_ORDER_FIAT', '10'),
            'GBP' => env('TRADING_MIN_ORDER_FIAT', '10'),
            'GCU' => env('TRADING_MIN_ORDER_GCU', '10'),
        ],
        'max_order' => [
            'BTC' => env('TRADING_MAX_ORDER_BTC', '100'),
            'ETH' => env('TRADING_MAX_ORDER_ETH', '1000'),
            'EUR' => env('TRADING_MAX_ORDER_FIAT', '1000000'),
            'USD' => env('TRADING_MAX_ORDER_FIAT', '1000000'),
            'GBP' => env('TRADING_MAX_ORDER_FIAT', '1000000'),
            'GCU' => env('TRADING_MAX_ORDER_GCU', '1000000'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Structure
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'maker' => env('TRADING_MAKER_FEE', '0.001'), // 0.1%
        'taker' => env('TRADING_TAKER_FEE', '0.002'), // 0.2%
        'volume_discounts' => [
            '1000000' => '0.0009', // $1M volume: 0.09% maker
            '10000000' => '0.0008', // $10M volume: 0.08% maker
            '100000000' => '0.0007', // $100M volume: 0.07% maker
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Exchange Connectors
    |--------------------------------------------------------------------------
    */
    'external_connectors' => explode(',', env('TRADING_EXTERNAL_CONNECTORS', 'binance,kraken')),

    /*
    |--------------------------------------------------------------------------
    | Market Making Configuration
    |--------------------------------------------------------------------------
    */
    'market_making' => [
        'enabled' => env('TRADING_MARKET_MAKING_ENABLED', false),
        'system_account_id' => env('TRADING_SYSTEM_ACCOUNT_ID'),
        'spread_percentage' => env('TRADING_MM_SPREAD', '0.002'), // 0.2%
        'order_size' => env('TRADING_MM_ORDER_SIZE', '0.1'),
        'max_exposure' => env('TRADING_MM_MAX_EXPOSURE', '10'), // Max 10 BTC equivalent
    ],

    /*
    |--------------------------------------------------------------------------
    | Arbitrage Configuration
    |--------------------------------------------------------------------------
    */
    'arbitrage' => [
        'enabled' => env('TRADING_ARBITRAGE_ENABLED', false),
        'min_spread_percentage' => env('TRADING_ARB_MIN_SPREAD', '0.005'), // 0.5%
        'check_interval' => env('TRADING_ARB_CHECK_INTERVAL', 60), // seconds
        'max_order_size' => env('TRADING_ARB_MAX_ORDER', '1'), // BTC equivalent
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'orders_per_minute' => env('TRADING_RATE_LIMIT_ORDERS', 60),
        'api_calls_per_minute' => env('TRADING_RATE_LIMIT_API', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Trading Pairs
    |--------------------------------------------------------------------------
    */
    'pairs' => [
        // Crypto pairs
        'BTC/EUR', 'BTC/USD', 'BTC/GBP', 'BTC/GCU',
        'ETH/EUR', 'ETH/USD', 'ETH/GBP', 'ETH/GCU',
        'ETH/BTC',
        
        // Fiat pairs with GCU
        'EUR/GCU', 'USD/GCU', 'GBP/GCU',
        
        // Traditional forex
        'EUR/USD', 'EUR/GBP', 'GBP/USD',
    ],
];