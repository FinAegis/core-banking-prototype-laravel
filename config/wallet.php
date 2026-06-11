<?php

declare(strict_types=1);

return [
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

        /*
        |----------------------------------------------------------------------
        | Fee-Payer (Sponsor) Account
        |----------------------------------------------------------------------
        |
        | A non-custodial Solana wallet holds SPL stablecoins but typically no
        | SOL, so it cannot pay its own transaction fee — the send fails with
        | "Attempt to debit an account but found no record of a prior credit".
        | When `secret_key` is set, the platform's sponsor account becomes the
        | transaction fee payer (account index 0) and co-signs every send; the
        | user's device still signs as the transfer authority. Leave it empty
        | to keep the legacy behaviour (sender pays its own fee).
        |
        | `secret_key` is the base58-encoded 64-byte ed25519 secret key
        | (32-byte seed || 32-byte public key — the standard Solana layout).
        | Generate it OFF-machine and store it only in the secret manager.
        |
        */
        'sponsor' => [
            'secret_key' => (string) env('WALLET_SOLANA_SPONSOR_SECRET_KEY', ''),
            // Alert threshold for the scheduled balance check, in lamports.
            // 1 SOL = 1_000_000_000 lamports; default 0.1 SOL.
            'low_balance_lamports' => (int) env('WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS', 100_000_000),
        ],

        /*
        |----------------------------------------------------------------------
        | Inbound Dust Filter
        |----------------------------------------------------------------------
        |
        | Solana addresses are public, so anyone can send a wallet tiny
        | unsolicited SOL transfers ("dusting" / address-poisoning spam).
        | Inbound native-SOL transfers below this threshold are still
        | recorded as a BlockchainTransaction for audit, but are kept out of
        | the activity feed and suppress the push notification — so spam does
        | not buzz the user. Token transfers (USDC/USDT) are never filtered.
        |
        */
        'dust' => [
            'min_inbound_sol' => (string) env('WALLET_SOLANA_DUST_MIN_INBOUND_SOL', '0.001'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | EVM Send Configuration
    |--------------------------------------------------------------------------
    */

    'evm' => [
        // Networks enabled for outbound dispatch. Mobile validates against
        // PaymentNetwork enum, so values must match (lowercase casing).
        //
        // Ethereum L1 is intentionally excluded by default: a single L1 send
        // can cost $1-$20+ in gas (vs fractions of a cent on the L2s), and we
        // sponsor that gas. The L2s — polygon/base/arbitrum — cover real usage
        // cheaply. Re-add 'ethereum' only with a deliberate cost decision.
        'enabled_networks' => array_filter(array_map(
            'trim',
            explode(',', (string) env('WALLET_SEND_EVM_NETWORKS', 'polygon,base,arbitrum')),
        )),
        // Token used to pay sponsorship fee. Must be supported by the paymaster.
        'fee_token' => (string) env('WALLET_SEND_EVM_FEE_TOKEN', 'USDC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sponsored-Send Abuse Guardrail
    |--------------------------------------------------------------------------
    |
    | Gas-sponsored sends (EVM via the Pimlico paymaster, Solana via the fee
    | payer) cost the platform real money per transaction. These caps bound
    | that exposure: a per-user daily limit stops a single scripted account,
    | and a global daily ceiling is a kill-switch against a mass-abuse spike.
    | Counts reset at 00:00 UTC. Defaults are generous enough to be invisible
    | to real users.
    |
    */

    'sponsorship' => [
        'per_user_daily_limit' => (int) env('WALLET_SEND_PER_USER_DAILY_LIMIT', 30),
        'global_daily_limit'   => (int) env('WALLET_SEND_GLOBAL_DAILY_LIMIT', 5000),

        // Optional value-denominated cap on top of the count caps: total
        // estimated USD the platform will spend on sponsored gas per UTC day.
        // Null/empty disables the check. The spend counter accumulates at
        // confirmation time (when the real fee is known), so enforcement
        // trails in-flight sends slightly — a burst can overshoot the budget,
        // bounded by the count caps above. See SponsorshipCostTracker.
        'daily_budget_usd' => env('WALLET_SPONSORSHIP_DAILY_BUDGET_USD'),
    ],
];
