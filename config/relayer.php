<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Gas Relayer Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the ERC-4337 gas relayer (meta-transaction service).
    | This allows users to execute transactions without holding native gas tokens.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Network
    |--------------------------------------------------------------------------
    */

    'default_network' => env('RELAYER_DEFAULT_NETWORK', 'polygon'),

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    */

    'fees' => [
        // Supported tokens for fee payment
        'supported_tokens' => ['USDC', 'USDT'],

        // Default token for fee payment
        'default_token' => env('RELAYER_FEE_TOKEN', 'USDC'),

        // Fee markup percentage (e.g., 0.1 = 10% markup over actual gas cost)
        'markup_percentage' => env('RELAYER_FEE_MARKUP', 0.1),

        // Minimum fee in USD
        'minimum_fee' => env('RELAYER_MIN_FEE', 0.01),
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Configurations
    |--------------------------------------------------------------------------
    */

    'networks' => [
        'polygon' => [
            'chain_id'    => 137,
            'rpc_url'     => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'paymaster'   => env('POLYGON_PAYMASTER_ADDRESS'),
            'bundler_url' => env('POLYGON_BUNDLER_URL'),
        ],

        'arbitrum' => [
            'chain_id'    => 42161,
            'rpc_url'     => env('ARBITRUM_RPC_URL', 'https://arb1.arbitrum.io/rpc'),
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'paymaster'   => env('ARBITRUM_PAYMASTER_ADDRESS'),
            'bundler_url' => env('ARBITRUM_BUNDLER_URL'),
        ],

        'optimism' => [
            'chain_id'    => 10,
            'rpc_url'     => env('OPTIMISM_RPC_URL', 'https://mainnet.optimism.io'),
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'paymaster'   => env('OPTIMISM_PAYMASTER_ADDRESS'),
            'bundler_url' => env('OPTIMISM_BUNDLER_URL'),
        ],

        'base' => [
            'chain_id'    => 8453,
            'rpc_url'     => env('BASE_RPC_URL', 'https://mainnet.base.org'),
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'paymaster'   => env('BASE_PAYMASTER_ADDRESS'),
            'bundler_url' => env('BASE_BUNDLER_URL'),
        ],

        'ethereum' => [
            'chain_id'    => 1,
            'rpc_url'     => env('ETHEREUM_RPC_URL', 'https://eth.llamarpc.com'),
            'entry_point' => '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789',
            'paymaster'   => env('ETHEREUM_PAYMASTER_ADDRESS'),
            'bundler_url' => env('ETHEREUM_BUNDLER_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bundler Configuration
    |--------------------------------------------------------------------------
    */

    'bundler' => [
        // Use demo bundler for development
        'driver' => env('BUNDLER_DRIVER', 'demo'),

        // External bundler providers
        'providers' => [
            'pimlico' => [
                'api_key' => env('PIMLICO_API_KEY'),
            ],
            'stackup' => [
                'api_key' => env('STACKUP_API_KEY'),
            ],
            'alchemy' => [
                'api_key' => env('ALCHEMY_API_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        // Max sponsored transactions per user per day
        'per_user_daily' => env('RELAYER_DAILY_LIMIT', 100),

        // Max sponsored value per user per day (in USD)
        'per_user_daily_value' => env('RELAYER_DAILY_VALUE_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Account Configuration (v2.6.0)
    |--------------------------------------------------------------------------
    |
    | Configuration for ERC-4337 smart account factories.
    |
    */

    'smart_accounts' => [
        // Factory contract addresses per network (SimpleAccountFactory or custom)
        'factory_addresses' => [
            'polygon'  => env('POLYGON_FACTORY_ADDRESS'),
            'base'     => env('BASE_FACTORY_ADDRESS'),
            'arbitrum' => env('ARBITRUM_FACTORY_ADDRESS'),
        ],

        // Paymaster contract addresses per network
        'paymaster_addresses' => [
            'polygon'  => env('POLYGON_PAYMASTER_ADDRESS'),
            'base'     => env('BASE_PAYMASTER_ADDRESS'),
            'arbitrum' => env('ARBITRUM_PAYMASTER_ADDRESS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pimlico Integration (v2.6.0)
    |--------------------------------------------------------------------------
    |
    | Configuration for Pimlico bundler service.
    |
    */

    'pimlico' => [
        'api_key'     => env('PIMLICO_API_KEY'),
        'bundler_url' => env('PIMLICO_BUNDLER_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Balance Checking Configuration (v2.7.0)
    |--------------------------------------------------------------------------
    |
    | Configuration for ERC-20 balance checking via RPC providers.
    |
    */

    'balance_checking' => [
        // Provider: 'demo', 'alchemy', 'infura', 'custom'
        'provider' => env('BALANCE_PROVIDER', 'demo'),

        // Cache TTL in seconds
        'cache_ttl_seconds' => (int) env('BALANCE_CACHE_TTL', 30),

        // Alchemy configuration
        'alchemy_api_key' => env('ALCHEMY_API_KEY'),

        // Infura configuration
        'infura_project_id' => env('INFURA_PROJECT_ID'),

        // Custom RPC URLs per network
        'custom_rpc' => [
            'polygon'  => env('CUSTOM_RPC_POLYGON'),
            'base'     => env('CUSTOM_RPC_BASE'),
            'arbitrum' => env('CUSTOM_RPC_ARBITRUM'),
            'optimism' => env('CUSTOM_RPC_OPTIMISM'),
            'ethereum' => env('CUSTOM_RPC_ETHEREUM'),
        ],

        // Token contract addresses (can override defaults)
        'tokens' => [
            'USDC' => [
                'polygon'  => env('USDC_POLYGON', '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359'),
                'base'     => env('USDC_BASE', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'),
                'arbitrum' => env('USDC_ARBITRUM', '0xaf88d065e77c8cC2239327C5EDb3A432268e5831'),
                'optimism' => env('USDC_OPTIMISM', '0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85'),
                'ethereum' => env('USDC_ETHEREUM', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'),
            ],
            'USDT' => [
                'polygon'  => env('USDT_POLYGON', '0xc2132D05D31c914a87C6611C10748AEb04B58e8F'),
                'ethereum' => env('USDT_ETHEREUM', '0xdAC17F958D2ee523a2206206994597C13D831ec7'),
                'arbitrum' => env('USDT_ARBITRUM', '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9'),
            ],
        ],

        // Demo mode balances (for testing)
        'demo_balances' => [
            // Example: '0x123...' => ['USDC' => '1000.000000', 'USDT' => '500.000000']
        ],
    ],
];
