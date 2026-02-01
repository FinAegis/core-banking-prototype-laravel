<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BaaS (Banking as a Service) Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the embedded finance and white-label API features
    | including partner tiers, SDK generation, widgets, and billing.
    |
    */

    'enabled' => env('BAAS_ENABLED', true),

    'demo_mode' => env('BAAS_DEMO_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Partner Tier Configuration
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'starter' => [
            'name'                  => 'Starter',
            'api_calls_monthly'     => 10000,
            'rate_limit_per_minute' => 60,
            'price_monthly_usd'     => 99.00,
            'overage_per_thousand'  => 1.00,
            'features'              => [
                'sandbox'           => true,
                'production'        => false,
                'webhooks'          => true,
                'api_analytics'     => true,
                'white_label'       => false,
                'custom_domain'     => false,
                'sdk_access'        => false,
                'widgets'           => false,
                'dedicated_support' => false,
                'priority_support'  => false,
                'sla_guarantee'     => false,
            ],
        ],
        'growth' => [
            'name'                  => 'Growth',
            'api_calls_monthly'     => 100000,
            'rate_limit_per_minute' => 300,
            'price_monthly_usd'     => 499.00,
            'overage_per_thousand'  => 0.50,
            'features'              => [
                'sandbox'           => true,
                'production'        => true,
                'webhooks'          => true,
                'api_analytics'     => true,
                'white_label'       => true,
                'custom_domain'     => false,
                'sdk_access'        => true,
                'widgets'           => true,
                'dedicated_support' => false,
                'priority_support'  => false,
                'sla_guarantee'     => false,
            ],
        ],
        'enterprise' => [
            'name'                  => 'Enterprise',
            'api_calls_monthly'     => 1000000,
            'rate_limit_per_minute' => 1000,
            'price_monthly_usd'     => 1999.00,
            'overage_per_thousand'  => 0.25,
            'features'              => [
                'sandbox'           => true,
                'production'        => true,
                'webhooks'          => true,
                'api_analytics'     => true,
                'white_label'       => true,
                'custom_domain'     => true,
                'sdk_access'        => true,
                'widgets'           => true,
                'dedicated_support' => true,
                'priority_support'  => true,
                'sla_guarantee'     => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | White-Label Settings
    |--------------------------------------------------------------------------
    */
    'white_label' => [
        'allowed_custom_domains' => env('BAAS_CUSTOM_DOMAINS_ENABLED', true),
        'default_branding'       => [
            'primary_color'   => '#1a365d',
            'secondary_color' => '#2b6cb0',
            'logo_url'        => null,
            'favicon_url'     => null,
            'company_name'    => 'FinAegis',
        ],
        'branding_fields' => [
            'primary_color',
            'secondary_color',
            'accent_color',
            'text_color',
            'background_color',
            'logo_url',
            'logo_dark_url',
            'favicon_url',
            'company_name',
            'tagline',
            'support_email',
            'support_phone',
            'privacy_policy_url',
            'terms_of_service_url',
            'custom_css',
            'custom_js',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK Generation Settings
    |--------------------------------------------------------------------------
    */
    'sdk' => [
        'enabled'             => env('BAAS_SDK_ENABLED', true),
        'generator_path'      => env('SDK_GENERATOR_PATH', '/usr/local/bin/openapi-generator'),
        'output_path'         => storage_path('app/sdk'),
        'supported_languages' => [
            'typescript' => [
                'name'            => 'TypeScript/JavaScript',
                'generator'       => 'typescript-fetch',
                'extension'       => 'ts',
                'package_manager' => 'npm',
            ],
            'python' => [
                'name'            => 'Python',
                'generator'       => 'python',
                'extension'       => 'py',
                'package_manager' => 'pip',
            ],
            'java' => [
                'name'            => 'Java',
                'generator'       => 'java',
                'extension'       => 'java',
                'package_manager' => 'maven',
            ],
            'go' => [
                'name'            => 'Go',
                'generator'       => 'go',
                'extension'       => 'go',
                'package_manager' => 'go mod',
            ],
            'csharp' => [
                'name'            => 'C#/.NET',
                'generator'       => 'csharp',
                'extension'       => 'cs',
                'package_manager' => 'nuget',
            ],
            'php' => [
                'name'            => 'PHP',
                'generator'       => 'php',
                'extension'       => 'php',
                'package_manager' => 'composer',
            ],
        ],
        'api_version'     => 'v1',
        'cache_ttl_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget Settings
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'enabled' => env('BAAS_WIDGETS_ENABLED', true),
        'types'   => [
            'payment' => [
                'name'        => 'Payment Form',
                'description' => 'Embeddable payment form widget',
                'js_file'     => 'finaegis-payment.js',
            ],
            'checkout' => [
                'name'        => 'Checkout Experience',
                'description' => 'Full checkout flow widget',
                'js_file'     => 'finaegis-checkout.js',
            ],
            'balance' => [
                'name'        => 'Balance Display',
                'description' => 'Account balance display widget',
                'js_file'     => 'finaegis-balance.js',
            ],
            'transfer' => [
                'name'        => 'Transfer Form',
                'description' => 'Money transfer widget',
                'js_file'     => 'finaegis-transfer.js',
            ],
            'account' => [
                'name'        => 'Account Summary',
                'description' => 'Account summary widget',
                'js_file'     => 'finaegis-account.js',
            ],
        ],
        'sandbox_domain'    => env('BAAS_WIDGET_SANDBOX_DOMAIN', 'sandbox.finaegis.com'),
        'production_domain' => env('BAAS_WIDGET_PRODUCTION_DOMAIN', 'api.finaegis.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage & Billing Settings
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'enabled'                       => env('BAAS_BILLING_ENABLED', true),
        'currency'                      => 'USD',
        'grace_period_days'             => 7,
        'billing_cycles'                => ['monthly', 'quarterly', 'annually'],
        'default_cycle'                 => 'monthly',
        'annual_discount_percentage'    => 15,
        'quarterly_discount_percentage' => 5,
        'payment_methods'               => ['card', 'bank_transfer', 'invoice'],
        'invoice_due_days'              => 30,
        'usage_aggregation_interval'    => 'daily', // daily, hourly
        'usage_retention_days'          => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner API Settings
    |--------------------------------------------------------------------------
    */
    'partner_api' => [
        'version'       => 'v1',
        'prefix'        => 'partner',
        'rate_limiting' => [
            'enabled'       => true,
            'default_limit' => 60,
            'burst_limit'   => 100,
        ],
        'authentication' => [
            'header_client_id'     => 'X-Partner-Client-Id',
            'header_client_secret' => 'X-Partner-Client-Secret',
            'header_api_key'       => 'Authorization',
        ],
        'allowed_endpoints' => [
            'accounts',
            'transactions',
            'transfers',
            'webhooks',
            'sdk',
            'widgets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Partner Marketplace Settings
    |--------------------------------------------------------------------------
    */
    'marketplace' => [
        'enabled'                => env('BAAS_MARKETPLACE_ENABLED', true),
        'integration_categories' => [
            'payment_processors' => [
                'name'      => 'Payment Processors',
                'providers' => ['stripe', 'adyen', 'square', 'paypal'],
            ],
            'identity_providers' => [
                'name'      => 'Identity Providers',
                'providers' => ['okta', 'auth0', 'azure_ad'],
            ],
            'kyc_providers' => [
                'name'      => 'KYC Providers',
                'providers' => ['jumio', 'onfido', 'trulioo', 'sumsub'],
            ],
            'accounting' => [
                'name'      => 'Accounting Software',
                'providers' => ['xero', 'quickbooks', 'freshbooks'],
            ],
            'analytics' => [
                'name'      => 'Analytics',
                'providers' => ['mixpanel', 'amplitude', 'segment'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'usage_warning_thresholds' => [75, 90, 100], // Percentage of API limit
        'billing_reminders_days'   => [7, 3, 1], // Days before due
        'channels'                 => ['email', 'database'],
    ],
];
