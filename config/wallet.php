<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Wallet Send (Outbound Transfer) Configuration
    |--------------------------------------------------------------------------
    |
    | Real on-chain dispatch is gated behind a feature flag. When the flag is
    | OFF (default), POST /api/v1/wallet/transactions/send returns a stub
    | PENDING acknowledgement matching the mobile contract — funds do not
    | move on-chain. When ON, the WalletSendDispatcher routes to the
    | per-chain pipeline (Solana via Helius RPC, EVM via Pimlico bundler).
    |
    | Flip the flag to true ONLY after smoke-testing on staging with small
    | amounts and verifying tx confirmation propagates back through the
    | webhook / polling path.
    |
    */

    'real_dispatch_enabled' => (bool) env('WALLET_SEND_REAL_DISPATCH', false),

    /*
    |--------------------------------------------------------------------------
    | Solana Send Configuration
    |--------------------------------------------------------------------------
    */

    'solana' => [
        // Public RPC endpoint used to broadcast signed transactions.
        // Helius mainnet RPC supports api-key as query param.
        'rpc_url' => (string) env('HELIUS_RPC_URL', 'https://mainnet.helius-rpc.com'),
        // Confirmation level required before treating a tx as confirmed.
        // Options: processed | confirmed | finalized
        'commitment' => (string) env('SOLANA_COMMITMENT', 'confirmed'),
        // Microlamports per compute unit for priority fee. Bump under congestion.
        'priority_fee_microlamports' => (int) env('SOLANA_PRIORITY_FEE_MICROLAMPORTS', 1000),
        // Compute unit limit for a single SPL transfer (with optional ATA create).
        'compute_unit_limit' => (int) env('SOLANA_COMPUTE_UNIT_LIMIT', 200000),
    ],

    /*
    |--------------------------------------------------------------------------
    | EVM Send Configuration
    |--------------------------------------------------------------------------
    */

    'evm' => [
        // Networks enabled for outbound dispatch. Mobile validates against
        // PaymentNetwork enum, so values must match (lowercase casing).
        'enabled_networks' => array_filter(array_map(
            'trim',
            explode(',', (string) env('WALLET_SEND_EVM_NETWORKS', 'polygon,base,arbitrum,ethereum')),
        )),
        // Token used to pay sponsorship fee. Must be supported by the paymaster.
        'fee_token' => (string) env('WALLET_SEND_EVM_FEE_TOKEN', 'USDC'),
    ],
];
