<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sub-Products Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which FinAegis sub-products are enabled for this installation.
    | Each sub-product can be independently enabled/disabled with granular
    | feature control.
    |
    */

    'exchange' => [
        'enabled' => env('SUB_PRODUCT_EXCHANGE_ENABLED', true),
        'name' => 'FinAegis Exchange',
        'description' => 'Multi-currency and crypto trading platform',
        'features' => [
            'crypto_trading' => env('EXCHANGE_CRYPTO_ENABLED', false),
            'fiat_pairs' => env('EXCHANGE_FIAT_ENABLED', true),
            'advanced_orders' => env('EXCHANGE_ADVANCED_ORDERS_ENABLED', true),
            'market_making' => env('EXCHANGE_MARKET_MAKING_ENABLED', false),
        ],
        'licenses' => ['vasp', 'mica'],
        'routes_prefix' => 'exchange',
        'navigation_order' => 20,
        'icon' => 'heroicon-o-arrows-right-left',
        'color' => 'info',
    ],

    'lending' => [
        'enabled' => env('SUB_PRODUCT_LENDING_ENABLED', false),
        'name' => 'FinAegis Lending',
        'description' => 'P2P lending and credit marketplace',
        'features' => [
            'sme_loans' => env('LENDING_SME_ENABLED', true),
            'invoice_financing' => env('LENDING_INVOICE_ENABLED', true),
            'trade_finance' => env('LENDING_TRADE_ENABLED', false),
            'real_estate' => env('LENDING_REAL_ESTATE_ENABLED', false),
            'personal_loans' => env('LENDING_PERSONAL_ENABLED', false),
        ],
        'licenses' => ['lending_license', 'credit_intermediary'],
        'routes_prefix' => 'lending',
        'navigation_order' => 30,
        'icon' => 'heroicon-o-banknotes',
        'color' => 'success',
    ],

    'stablecoins' => [
        'enabled' => env('SUB_PRODUCT_STABLECOINS_ENABLED', true),
        'name' => 'FinAegis Stablecoins',
        'description' => 'Regulated stablecoin issuance and management',
        'features' => [
            'eur_stablecoin' => env('STABLECOIN_EUR_ENABLED', true),
            'usd_stablecoin' => env('STABLECOIN_USD_ENABLED', false),
            'basket_stablecoin' => env('STABLECOIN_BASKET_ENABLED', false),
            'asset_backed_tokens' => env('STABLECOIN_ASSET_BACKED_ENABLED', false),
            'yield_bearing' => env('STABLECOIN_YIELD_ENABLED', false),
        ],
        'licenses' => ['emi_license', 'e_money_issuer'],
        'routes_prefix' => 'stablecoins',
        'navigation_order' => 40,
        'icon' => 'heroicon-o-currency-euro',
        'color' => 'warning',
    ],

    'treasury' => [
        'enabled' => env('SUB_PRODUCT_TREASURY_ENABLED', true),
        'name' => 'FinAegis Treasury',
        'description' => 'Advanced treasury and cash management',
        'features' => [
            'multi_bank' => env('TREASURY_MULTI_BANK_ENABLED', true),
            'fx_optimization' => env('TREASURY_FX_OPTIMIZATION_ENABLED', true),
            'hedging_tools' => env('TREASURY_HEDGING_ENABLED', false),
            'corporate_features' => env('TREASURY_CORPORATE_ENABLED', false),
            'liquidity_management' => env('TREASURY_LIQUIDITY_ENABLED', true),
        ],
        'licenses' => ['payment_services', 'investment_services'],
        'routes_prefix' => 'treasury',
        'navigation_order' => 10,
        'icon' => 'heroicon-o-building-library',
        'color' => 'primary',
    ],
];