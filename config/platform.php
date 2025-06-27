<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the FinAegis platform including
    | GCU composition, statistics, and feature availability.
    |
    */

    'status' => 'alpha', // alpha, beta, production

    'gcu' => [
        'composition' => [
            'USD' => 35,
            'EUR' => 30,
            'GBP' => 20,
            'CHF' => 10,
            'JPY' => 3,
            'XAU' => 2,
        ],
        'next_voting_date' => '2025-07-15', // Next monthly voting date
        'voting_enabled' => false, // Not yet implemented
    ],

    'statistics' => [
        'supported_currencies' => 6,
        'banking_partners' => 3, // Paysera, Deutsche Bank, Santander (actual implemented)
        'api_endpoints' => 12, // Actual count from our API routes
        'transaction_speed' => '< 1s', // Target, not yet measured
        'uptime_sla' => '99.9%', // Target, not yet measured
    ],

    'features' => [
        'multi_asset_support' => true,
        'instant_settlements' => false, // Not yet implemented
        'democratic_governance' => false, // Not yet implemented  
        'bank_integration' => true, // Paysera implemented
        'api_access' => true,
        'security_features' => true,
    ],

    'sub_products' => [
        'exchange' => [
            'enabled' => false,
            'status' => 'development',
            'launch_date' => 'Q2 2025',
            'pricing' => 'TBD',
        ],
        'lending' => [
            'enabled' => false,
            'status' => 'development',
            'launch_date' => 'Q2 2025',
            'pricing' => 'TBD',
        ],
        'stablecoins' => [
            'enabled' => false,
            'status' => 'planned',
            'launch_date' => 'Q3 2025',
            'pricing' => 'TBD',
        ],
        'treasury' => [
            'enabled' => false,
            'status' => 'planned',
            'launch_date' => 'Q4 2025',
            'pricing' => 'TBD',
        ],
    ],

    'banking_partners' => [
        'paysera' => [
            'name' => 'Paysera',
            'country' => 'Lithuania',
            'integrated' => true,
        ],
        'deutsche_bank' => [
            'name' => 'Deutsche Bank',
            'country' => 'Germany',
            'integrated' => false, // Planned
        ],
        'santander' => [
            'name' => 'Santander',
            'country' => 'Spain', 
            'integrated' => false, // Planned
        ],
    ],

    'api' => [
        'version' => 'v1',
        'documentation_available' => true,
        'sdks_available' => false, // Not yet created
        'webhooks_available' => false, // Not yet implemented
        'rate_limit' => '1000 requests/hour',
    ],

    'pricing' => [
        'platform_fee' => 'Free during alpha',
        'future_model' => 'Subscription-based for platform access',
        'open_source' => true,
        'commercial_license' => 'Coming soon',
    ],
];