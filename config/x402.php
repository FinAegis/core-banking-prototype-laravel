<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | x402 Protocol Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the x402 HTTP-native micropayment protocol.
    | Enables per-request API monetization using stablecoin payments.
    | See: https://x402.org
    |
    */

    'enabled' => env('X402_ENABLED', false),

    'version' => 2,

    /*
    |--------------------------------------------------------------------------
    | Resource Server Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for when FinAegis acts as a resource server (seller),
    | charging for premium API endpoints via x402.
    |
    */

    'server' => [
        'pay_to' => env('X402_PAY_TO_ADDRESS', ''),

        'default_network' => env('X402_DEFAULT_NETWORK', 'eip155:8453'),

        'default_asset' => env('X402_DEFAULT_ASSET', 'USDC'),

        'max_timeout_seconds' => (int) env('X402_MAX_TIMEOUT_SECONDS', 60),

        'settle_before_response' => (bool) env('X402_SETTLE_BEFORE_RESPONSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facilitator Settings
    |--------------------------------------------------------------------------
    |
    | The facilitator verifies payment signatures and settles on-chain.
    | Use the public testnet facilitator for development or Coinbase CDP
    | for production.
    |
    */

    'facilitator' => [
        'url' => env('X402_FACILITATOR_URL', 'https://x402.org/facilitator'),

        'timeout_seconds' => (int) env('X402_FACILITATOR_TIMEOUT', 30),

        'self_hosted' => (bool) env('X402_SELF_HOSTED_FACILITATOR', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for when FinAegis acts as a client (buyer),
    | paying external x402-enabled APIs on behalf of AI agents.
    |
    */

    'client' => [
        'enabled' => (bool) env('X402_CLIENT_ENABLED', false),

        'signer_key_id' => env('X402_CLIENT_SIGNER_KEY_ID'),

        'signer_address' => env('X402_CLIENT_SIGNER_ADDRESS', ''),

        'auto_pay' => (bool) env('X402_CLIENT_AUTO_PAY', false),

        // Maximum auto-pay amount per request in atomic USDC units ($0.10)
        'max_auto_pay_amount' => env('X402_CLIENT_MAX_AUTO_PAY', '100000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Network-Specific Asset Addresses
    |--------------------------------------------------------------------------
    |
    | USDC contract addresses per supported CAIP-2 network.
    |
    */

    'assets' => [
        'eip155:8453' => [
            'USDC' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'eip155:84532' => [
            'USDC' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
        ],
        'eip155:1' => [
            'USDC' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ],
        'eip155:11155111' => [
            'USDC' => '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238',
        ],
        'eip155:43114' => [
            'USDC' => '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E',
        ],
        'eip155:43113' => [
            'USDC' => '0x5425890298aed601595a70AB815c96711a31Bc65',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Contract Addresses
    |--------------------------------------------------------------------------
    |
    | Deterministic addresses deployed via CREATE2 on all EVM chains.
    |
    */

    'contracts' => [
        'permit2'             => '0x000000000022D473030F116dDEE9F6B43aC78BA3',
        'exact_permit2_proxy' => '0x4020615294c913F045dc10f0a5cdEbd86c280001',
        'upto_permit2_proxy'  => '0x4020633461b2895a48930Ff97eE8fCdE8E520002',
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Spending Limits
    |--------------------------------------------------------------------------
    |
    | Default spending limits for AI agents making x402 payments.
    | All amounts in atomic USDC units (6 decimals, $1 = 1000000).
    |
    */

    'agent_spending' => [
        // $5.00 daily limit
        'default_daily_limit' => env('X402_AGENT_DAILY_LIMIT', '5000000'),

        // Require human approval above $1.00
        'require_approval_above' => env('X402_REQUIRE_APPROVAL_ABOVE', '1000000'),

        // $0.10 per-transaction auto-pay limit
        'default_per_transaction_limit' => env('X402_PER_TRANSACTION_LIMIT', '100000'),
    ],
];
