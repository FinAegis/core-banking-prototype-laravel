<?php

declare(strict_types=1);

/**
 * Multi-Tenancy Configuration.
 *
 * This config file controls the team-based multi-tenancy implementation
 * that integrates Jetstream teams with stancl/tenancy.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Auto-Create Tenants
    |--------------------------------------------------------------------------
    |
    | When enabled, tenants will be automatically created for teams that
    | don't have one when resolved. This should ONLY be enabled in
    | development or demo environments.
    |
    | In production, tenants should be created through proper workflows
    | (e.g., during team creation, through admin panel, etc.)
    |
    */
    'auto_create_tenants' => env('MULTITENANCY_AUTO_CREATE', false),

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Allowed Environments
    |--------------------------------------------------------------------------
    |
    | List of environments where auto-creation of tenants is allowed.
    | Even if auto_create_tenants is true, it will only work in these
    | environments for security.
    |
    */
    'auto_create_environments' => ['local', 'testing', 'demo'],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the TeamTenantResolver.
    |
    */
    'resolver' => [
        // Whether to cache resolved tenants
        'cache' => env('MULTITENANCY_CACHE', true),

        // Cache TTL in seconds (default: 1 hour)
        'cache_ttl' => env('MULTITENANCY_CACHE_TTL', 3600),

        // Cache store to use (null = default store)
        'cache_store' => env('MULTITENANCY_CACHE_STORE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the InitializeTenancyByTeam middleware.
    |
    */
    'middleware' => [
        // Whether to allow requests without tenant context
        // Default: false (explicit 403 for missing tenant)
        'allow_without_tenant' => env('MULTITENANCY_ALLOW_NO_TENANT', false),

        // Rate limit: max tenant lookups per minute per user
        'rate_limit_attempts' => env('MULTITENANCY_RATE_LIMIT', 60),

        // Routes that bypass tenant requirement even when allow_without_tenant is false
        // These are regex patterns matched against the request path
        'bypass_routes' => [
            '#^api/user$#',           // User info endpoint
            '#^sanctum/csrf-cookie#', // CSRF cookie
            '#^livewire#',            // Livewire requests
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Additional security settings for multi-tenancy.
    |
    */
    'security' => [
        // Log all tenancy events (initialization, errors, etc.)
        'audit_logging' => env('MULTITENANCY_AUDIT_LOG', true),

        // Strict mode: fail requests when tenant verification fails
        // When false, logs warning but continues
        'strict_mode' => env('MULTITENANCY_STRICT', true),
    ],
];
