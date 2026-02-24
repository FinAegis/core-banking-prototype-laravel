<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Chain Bridge Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cross-chain bridge integrations including Wormhole,
    | LayerZero, and Axelar protocol adapters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Bridge Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('CROSSCHAIN_DEFAULT_PROVIDER', 'demo'),

    /*
    |--------------------------------------------------------------------------
    | Fee Thresholds
    |--------------------------------------------------------------------------
    */

    'fees' => [
        'max_fee_percentage' => env('CROSSCHAIN_MAX_FEE_PCT', 2.0),
        'quote_ttl_seconds'  => env('CROSSCHAIN_QUOTE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Bridge Routes
    |--------------------------------------------------------------------------
    |
    | Define which chain pairs are supported. Routes are bidirectional.
    |
    */

    'routes' => [
        'ethereum_polygon'  => ['source' => 'ethereum', 'dest' => 'polygon', 'tokens' => ['USDC', 'USDT', 'WETH', 'WBTC', 'DAI']],
        'ethereum_arbitrum' => ['source' => 'ethereum', 'dest' => 'arbitrum', 'tokens' => ['USDC', 'USDT', 'WETH', 'WBTC', 'DAI']],
        'ethereum_optimism' => ['source' => 'ethereum', 'dest' => 'optimism', 'tokens' => ['USDC', 'USDT', 'WETH', 'DAI']],
        'ethereum_base'     => ['source' => 'ethereum', 'dest' => 'base', 'tokens' => ['USDC', 'WETH', 'DAI']],
        'ethereum_bsc'      => ['source' => 'ethereum', 'dest' => 'bsc', 'tokens' => ['USDC', 'USDT']],
        'polygon_arbitrum'  => ['source' => 'polygon', 'dest' => 'arbitrum', 'tokens' => ['USDC', 'USDT', 'WETH']],
        'polygon_optimism'  => ['source' => 'polygon', 'dest' => 'optimism', 'tokens' => ['USDC', 'WETH']],
        'polygon_base'      => ['source' => 'polygon', 'dest' => 'base', 'tokens' => ['USDC']],
        'arbitrum_optimism' => ['source' => 'arbitrum', 'dest' => 'optimism', 'tokens' => ['USDC', 'USDT', 'WETH']],
        'arbitrum_base'     => ['source' => 'arbitrum', 'dest' => 'base', 'tokens' => ['USDC', 'WETH']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Wormhole Configuration
    |--------------------------------------------------------------------------
    */

    'wormhole' => [
        'enabled'      => env('WORMHOLE_ENABLED', false),
        'guardian_rpc' => env('WORMHOLE_GUARDIAN_RPC', 'https://wormhole-v2-mainnet-api.certus.one'),
        'api_url'      => env('WORMHOLE_API_URL', 'https://api.wormholescan.io'),
        'token_bridge' => [
            'ethereum' => env('WORMHOLE_TOKEN_BRIDGE_ETH', '0x3ee18B2214AFF97000D974cf647E7C347E8fa585'),
            'polygon'  => env('WORMHOLE_TOKEN_BRIDGE_POLYGON', '0x5a58505a96D1dbf8dF91cB21B54419FC36e93fdE'),
            'bsc'      => env('WORMHOLE_TOKEN_BRIDGE_BSC', '0xB6F6D86a8f9879A9c87f643768d9efc38c1Da6E7'),
            'arbitrum' => env('WORMHOLE_TOKEN_BRIDGE_ARBITRUM', '0x0b2402144Bb366A632D14B83F244D2e0e21bD39c'),
            'optimism' => env('WORMHOLE_TOKEN_BRIDGE_OPTIMISM', '0x1D68124e65faFC907325e3EDbF8c4d84499DAa8b'),
            'base'     => env('WORMHOLE_TOKEN_BRIDGE_BASE', '0x8d2de8d2f73F1F4cAB472AC9A881C9b123C79627'),
            'solana'   => env('WORMHOLE_TOKEN_BRIDGE_SOLANA', 'wormDTUJ6AWPNvk59vGQbDvGJmqbDTdgWgAqcLBCgUb'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LayerZero Configuration
    |--------------------------------------------------------------------------
    */

    'layerzero' => [
        'enabled'      => env('LAYERZERO_ENABLED', false),
        'endpoint_url' => env('LAYERZERO_ENDPOINT_URL'),
        'api_url'      => env('LAYERZERO_API_URL', 'https://api.layerzero-scan.com'),
        'endpoints'    => [
            'ethereum' => env('LAYERZERO_ENDPOINT_ETH', '0x1a44076050125825900e736c501f859c50fE728c'),
            'polygon'  => env('LAYERZERO_ENDPOINT_POLYGON', '0x1a44076050125825900e736c501f859c50fE728c'),
            'arbitrum' => env('LAYERZERO_ENDPOINT_ARBITRUM', '0x1a44076050125825900e736c501f859c50fE728c'),
            'optimism' => env('LAYERZERO_ENDPOINT_OPTIMISM', '0x1a44076050125825900e736c501f859c50fE728c'),
            'base'     => env('LAYERZERO_ENDPOINT_BASE', '0x1a44076050125825900e736c501f859c50fE728c'),
            'bsc'      => env('LAYERZERO_ENDPOINT_BSC', '0x1a44076050125825900e736c501f859c50fE728c'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Axelar Configuration
    |--------------------------------------------------------------------------
    */

    'axelar' => [
        'enabled'     => env('AXELAR_ENABLED', false),
        'api_url'     => env('AXELAR_API_URL', 'https://api.axelarscan.io'),
        'gateway_url' => env('AXELAR_GATEWAY_URL'),
        'gateways'    => [
            'ethereum' => env('AXELAR_GATEWAY_ETH', '0x4F4495243837681061C4743b74B3eEdf548D56A5'),
            'polygon'  => env('AXELAR_GATEWAY_POLYGON', '0x6f015F16De9fC8791b234eF68D486d2bF203FBA8'),
            'arbitrum' => env('AXELAR_GATEWAY_ARBITRUM', '0xe432150cce91c13a887f7D836923d5597adD8E31'),
            'optimism' => env('AXELAR_GATEWAY_OPTIMISM', '0xe432150cce91c13a887f7D836923d5597adD8E31'),
            'base'     => env('AXELAR_GATEWAY_BASE', '0xe432150cce91c13a887f7D836923d5597adD8E31'),
            'bsc'      => env('AXELAR_GATEWAY_BSC', '0x304acf330bbE08d1e512eefaa92F6a57871fD895'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circle CCTP Configuration
    |--------------------------------------------------------------------------
    */

    'circle_cctp' => [
        'enabled'          => env('CIRCLE_CCTP_ENABLED', false),
        'api_url'          => env('CIRCLE_CCTP_API_URL', 'https://iris-api.circle.com'),
        'attestation_url'  => env('CIRCLE_CCTP_ATTESTATION_URL', 'https://iris-api.circle.com/attestations'),
        'token_messenger'  => [
            'ethereum' => env('CIRCLE_CCTP_TOKEN_MESSENGER_ETH', '0xBd3fa81B58Ba92a82136038B25aDec7066af3155'),
            'polygon'  => env('CIRCLE_CCTP_TOKEN_MESSENGER_POLYGON', '0x9daF8c91AEFAE50b9c0E69629D3F6Ca40cA3B3FE'),
            'arbitrum' => env('CIRCLE_CCTP_TOKEN_MESSENGER_ARBITRUM', '0x19330d10D9Cc8751218eaf51E8885D058642E08A'),
            'base'     => env('CIRCLE_CCTP_TOKEN_MESSENGER_BASE', '0x1682Ae6375C4E4A97e4B583BC394c861A46D8962'),
        ],
        'message_transmitter' => [
            'ethereum' => env('CIRCLE_CCTP_MSG_TRANSMITTER_ETH', '0x0a992d191DEeC32aFe36203Ad87D7d289a738F81'),
            'polygon'  => env('CIRCLE_CCTP_MSG_TRANSMITTER_POLYGON', '0xF3be9355363857F3e001be68856A2f96b4C39Ba9'),
            'arbitrum' => env('CIRCLE_CCTP_MSG_TRANSMITTER_ARBITRUM', '0xC30362313FBBA5cf9163F0bb16a0e01f01A896ca'),
            'base'     => env('CIRCLE_CCTP_MSG_TRANSMITTER_BASE', '0xAD09780d193884d503182aD4F75D113B9B2b2e8d'),
        ],
        'domains' => [
            'ethereum' => 0,
            'polygon'  => 7,
            'arbitrum' => 3,
            'base'     => 6,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Configuration
    |--------------------------------------------------------------------------
    */

    'demo' => [
        'simulated_delay_seconds' => env('CROSSCHAIN_DEMO_DELAY', 5),
        'success_rate'            => env('CROSSCHAIN_DEMO_SUCCESS_RATE', 0.95),
        'fee_percentage'          => env('CROSSCHAIN_DEMO_FEE_PCT', 0.1),
    ],
];
