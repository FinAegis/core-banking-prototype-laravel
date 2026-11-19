<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the FinAegis platform including
    | the GCU demo basket composition, statistics, and feature availability.
    | GCU is a software demonstration only: it is not issued, offered or sold
    | and has no monetary value (see docs/REGULATORY-CLAIMS.md).
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
        'next_voting_date' => '2025-07-15', // Demo only: next simulated basket poll date
        'voting_enabled'   => false, // Not yet implemented
    ],

    'statistics' => [
        'supported_currencies' => 6,
        'api_endpoints'        => 12, // Actual count from our API routes
        'transaction_speed'    => '< 1s', // Target, not yet measured
        'uptime_sla'           => '99.9%', // Target, not yet measured
    ],

    'features' => [
        'multi_asset_support'   => true,
        'instant_settlements'   => false, // Not yet implemented
        'democratic_governance' => false, // Not yet implemented
        'bank_integration'      => true, // Integration adapter only; no partnership or endorsement implied
        'api_access'            => true,
        'security_features'     => true,
    ],

    'sub_products' => [
        'exchange' => [
            'enabled'     => false,
            'status'      => 'development',
            'launch_date' => 'Q2 2025',
            'pricing'     => 'TBD',
        ],
        'lending' => [
            'enabled'     => false,
            'status'      => 'development',
            'launch_date' => 'Q2 2025',
            'pricing'     => 'TBD',
        ],
        'stablecoins' => [
            'enabled'     => false,
            'status'      => 'planned',
            'launch_date' => 'Q3 2025',
            'pricing'     => 'TBD',
        ],
        'treasury' => [
            'enabled'     => false,
            'status'      => 'planned',
            'launch_date' => 'Q4 2025',
            'pricing'     => 'TBD',
        ],
    ],

    'api' => [
        'version'                 => 'v1',
        'documentation_available' => true,
        'sdks_available'          => false, // Not yet created
        'webhooks_available'      => false, // Not yet implemented
        'rate_limit'              => '1000 requests/hour',
    ],

    'pricing' => [
        'platform_fee'       => 'Free during alpha',
        'future_model'       => 'Subscription-based for platform access',
        'open_source'        => true,
        'commercial_license' => 'Coming soon',
    ],
];
