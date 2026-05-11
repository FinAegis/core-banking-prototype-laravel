<?php

/**
 * Plan B v1.3.0 — Pricing configuration.
 *
 * Renamed from config/fees.php per ADR-0003 (Pricing bounded context).
 *
 * Fee-tier amounts: verify tx_flat_eur_cents / tx_flat_asset_amount against
 * the commercial agreement before a production deploy. The values below are
 * sourced from commercial §10.2 + deltas Q4 sample and are placeholders.
 *
 * IMPORTANT: Domain/Subscription code must NEVER read config('pricing.tiers')
 * directly — it goes through the ResolveFeeTier query handler. This file is
 * the query handler's backing store only.
 *
 * @see docs/adr/0003-pricing-bounded-context.md
 * @see docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md §2 (fee resolver)
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Backend-Q4
 */

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fee tiers
    |--------------------------------------------------------------------------
    |
    | tx_flat_eur_cents    — flat fee per transaction in EUR cents (€0.20 = 20)
    | tx_flat_asset_amount — flat fee in USDC smallest-unit (1.0 USDC = 1000000)
    | swap_margin_bps      — swap margin in basis points (1 bp = 0.01%)
    | ramp_margin_bps      — ramp margin in basis points
    |
    | PLACEHOLDER VALUES — verify against commercial agreement before baking
    | into production. Update this file; no migration or code change needed.
    |
    */

    'tiers' => [
        'free' => [
            'tx_flat_eur_cents'    => 20,          // €0.20 per commercial §10.2 (verify before prod)
            'tx_flat_asset_amount' => '1000000',   // 1.0 USDC (6 decimals)
            'swap_margin_bps'      => 20,
            'ramp_margin_bps'      => 100,
        ],
        'pro' => [
            'tx_flat_eur_cents'    => 5,            // €0.05 per commercial §10.2 (verify before prod)
            'tx_flat_asset_amount' => '50000',      // 0.05 USDC
            'swap_margin_bps'      => 5,
            'ramp_margin_bps'      => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quote TTL (seconds, per kind)
    |--------------------------------------------------------------------------
    |
    | Different kinds have different price-drift profiles. Swap quotes are
    | very short-lived (AMM prices move fast). Subscription/deposit are flat
    | and long-lived.
    |
    */

    'quote_ttl_seconds' => [
        'send'                  => 300,    // 5 min — gas estimation drift is slow
        'swap'                  => 30,     // 30 s  — AMM prices move fast
        'ramp_buy'              => 60,     // 60 s  — Stripe Bridge quote window
        'ramp_sell'             => 60,
        'subscription_initial'  => 3600,   // 60 min — flat price, user may delay during consent
        'card_waitlist_deposit' => 3600,   // 60 min — flat €5, no rate drift
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'quotes_per_minute_per_user' => 60,
        'quotes_per_minute_per_ip'   => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pepper env key
    |--------------------------------------------------------------------------
    |
    | The PRICING_QUOTE_PEPPER env var is read via config('pricing.quote_pepper').
    | See config/services.php for the actual env() binding.
    |
    */

    'quote_pepper_env_key' => 'PRICING_QUOTE_PEPPER',

    /*
    |--------------------------------------------------------------------------
    | Fee-collector wallet addresses (per chain)
    |--------------------------------------------------------------------------
    |
    | These are the EOA / smart-account addresses that receive service fees.
    | Provisioned via the ADR-0001 KMS-backed wallet setup (separate ops concern).
    | PLACEHOLDER — fill in before first on-chain fee collection.
    |
    */

    'fee_collectors' => [
        'polygon'  => env('FEE_COLLECTOR_POLYGON', ''),
        'base'     => env('FEE_COLLECTOR_BASE', ''),
        'arbitrum' => env('FEE_COLLECTOR_ARBITRUM', ''),
        'ethereum' => env('FEE_COLLECTOR_ETHEREUM', ''),
        'solana'   => env('FEE_COLLECTOR_SOLANA', ''),
    ],

];
