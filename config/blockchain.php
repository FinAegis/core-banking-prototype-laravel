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
        'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/YOUR_PROJECT_ID'),
        'chain_id' => env('ETHEREUM_CHAIN_ID', '1'),
        'network' => env('ETHEREUM_NETWORK', 'mainnet'),
    ],
    
    'polygon' => [
        'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
        'chain_id' => env('POLYGON_CHAIN_ID', '137'),
        'network' => env('POLYGON_NETWORK', 'mainnet'),
    ],
    
    'bsc' => [
        'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),
        'chain_id' => env('BSC_CHAIN_ID', '56'),
        'network' => env('BSC_NETWORK', 'mainnet'),
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
            'address' => env('ETHEREUM_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('ETHEREUM_HOT_WALLET_KEY'),
        ],
        'polygon' => [
            'address' => env('POLYGON_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('POLYGON_HOT_WALLET_KEY'),
        ],
        'bsc' => [
            'address' => env('BSC_HOT_WALLET_ADDRESS'),
            'encrypted_key' => env('BSC_HOT_WALLET_KEY'),
        ],
        'bitcoin' => [
            'address' => env('BITCOIN_HOT_WALLET_ADDRESS'),
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
            'polygon' => 128,
            'bsc' => 15,
            'bitcoin' => 6,
        ],
        'daily_withdrawal_limit' => env('BLOCKCHAIN_DAILY_WITHDRAWAL_LIMIT', '10000'),
        'require_2fa_amount' => env('BLOCKCHAIN_REQUIRE_2FA_AMOUNT', '1000'),
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
            'minimum' => env('BLOCKCHAIN_DEPOSIT_FEE_MIN', '0'),
        ],
        'withdrawal' => [
            'percentage' => env('BLOCKCHAIN_WITHDRAWAL_FEE_PERCENTAGE', '0.1'),
            'minimum' => env('BLOCKCHAIN_WITHDRAWAL_FEE_MIN', '1'),
        ],
    ],
];