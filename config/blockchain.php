<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blockchain Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for blockchain integrations including
    | RPC endpoints, chain IDs, and hot wallet settings.
    |
    */

    'ethereum' => [
        'rpc_url'  => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'),
        'chain_id' => env('ETHEREUM_CHAIN_ID', '1'),
        'network'  => env('ETHEREUM_NETWORK', 'mainnet'),
    ],

    'polygon' => [
        'rpc_url'  => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
        'chain_id' => env('POLYGON_CHAIN_ID', '137'),
        'network'  => env('POLYGON_NETWORK', 'mainnet'),
    ],

    'bsc' => [
        'rpc_url'  => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),
        'chain_id' => env('BSC_CHAIN_ID', '56'),
        'network'  => env('BSC_NETWORK', 'mainnet'),
    ],

    'bitcoin' => [
        'network' => env('BITCOIN_NETWORK', 'mainnet'),
        'api_url' => env('BITCOIN_API_URL', 'https://api.blockcypher.com/v1/btc/main'),
        'api_key' => env('BITCOIN_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hot Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for hot wallets used for automated deposits/withdrawals.
    | In production, private keys should be stored in HSM.
    |
    */

    'hot_wallets' => [
        'ethereum' => [
            'address'       => env('ETHEREUM_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('ETHEREUM_HOT_WALLET_KEY'),
        ],
        'polygon' => [
            'address'       => env('POLYGON_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('POLYGON_HOT_WALLET_KEY'),
        ],
        'bsc' => [
            'address'       => env('BSC_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('BSC_HOT_WALLET_KEY'),
        ],
        'bitcoin' => [
            'address'       => env('BITCOIN_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('BITCOIN_HOT_WALLET_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for blockchain operations.
    |
    */

    'security' => [
        'min_confirmations' => [
            'ethereum' => 12,
            'polygon'  => 128,
            'bsc'      => 15,
            'bitcoin'  => 6,
        ],
        'daily_withdrawal_limit' => env('BLOCKCHAIN_DAILY_WITHDRAWAL_LIMIT', '10000'),
        'require_2fa_amount'     => env('BLOCKCHAIN_REQUIRE_2FA_AMOUNT', '1000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Configuration
    |--------------------------------------------------------------------------
    |
    | Fee settings for blockchain transactions.
    |
    */

    'fees' => [
        'deposit' => [
            'percentage' => env('BLOCKCHAIN_DEPOSIT_FEE_PERCENTAGE', '0'),
            'minimum'    => env('BLOCKCHAIN_DEPOSIT_FEE_MIN', '0'),
        ],
        'withdrawal' => [
            'percentage' => env('BLOCKCHAIN_WITHDRAWAL_FEE_PERCENTAGE', '0.1'),
            'minimum'    => env('BLOCKCHAIN_WITHDRAWAL_FEE_MIN', '1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Access Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for secure key access control and rate limiting.
    |
    */
    'key_access' => [
        'max_attempts'  => env('KEY_ACCESS_MAX_ATTEMPTS', 10),
        'decay_minutes' => env('KEY_ACCESS_DECAY_MINUTES', 1),
        'temp_key_ttl'  => env('KEY_ACCESS_TEMP_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Hardware Security Module (HSM) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HSM integration for secure key storage.
    |
    */
    'hsm' => [
        'enabled'     => env('HSM_ENABLED', false),
        'provider'    => env('HSM_PROVIDER', 'aws'), // aws, azure, thales
        'partition'   => env('HSM_PARTITION', 'default'),
        'endpoint'    => env('HSM_ENDPOINT'),
        'credentials' => [
            'key'    => env('HSM_ACCESS_KEY'),
            'secret' => env('HSM_SECRET_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hardware Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for hardware wallet integration (Ledger, Trezor).
    | Hardware wallets provide secure key storage with user-controlled signing.
    |
    */

    'hardware_wallets' => [
        'enabled' => env('HARDWARE_WALLETS_ENABLED', true),

        'ledger' => [
            'supported_models' => ['nano_s', 'nano_x'],
            'app_versions'     => [
                'ethereum' => '1.9.0',
                'bitcoin'  => '2.0.0',
            ],
        ],

        'trezor' => [
            'supported_models' => ['one', 'model_t'],
            'webusb_enabled'   => true,
        ],

        'signing_request' => [
            'ttl_seconds'      => env('HARDWARE_WALLET_SIGNING_TTL', 300), // 5 minutes
            'max_retries'      => 3,
            'poll_interval_ms' => 1000,
        ],

        'supported_chains' => [
            'ethereum' => [
                'coin_type'       => 60,
                'derivation_path' => "m/44'/60'/0'/0/{index}",
            ],
            'bitcoin' => [
                'coin_type'       => 0,
                'derivation_path' => "m/44'/0'/0'/0/{index}",
            ],
            'polygon' => [
                'coin_type'       => 60, // Same as Ethereum (EVM compatible)
                'derivation_path' => "m/44'/60'/0'/0/{index}",
            ],
            'bsc' => [
                'coin_type'       => 60, // Same as Ethereum (EVM compatible)
                'derivation_path' => "m/44'/60'/0'/0/{index}",
            ],
        ],

        'security' => [
            'require_verification'      => true,
            'max_associations_per_user' => 10,
            'max_pending_requests'      => 5,
        ],
    ],
];
