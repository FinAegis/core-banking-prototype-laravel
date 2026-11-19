<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy Settings
    |--------------------------------------------------------------------------
    |
    | Configure the Content Security Policy (CSP) headers for your application.
    | These settings help prevent XSS attacks and other code injection attacks.
    |
    */

    'csp' => [
        // Font sources
        'font_sources' => env('CSP_FONT_SOURCES', 'https://fonts.gstatic.com,https://fonts.bunny.net'),

        // Style sources
        'style_sources' => env('CSP_STYLE_SOURCES', 'https://fonts.googleapis.com,https://fonts.bunny.net'),

        // Script sources
        'script_sources' => env('CSP_SCRIPT_SOURCES', 'https://cdn.jsdelivr.net,https://www.googletagmanager.com'),

        // API endpoints
        'api_endpoint' => env('CSP_API_ENDPOINT', 'https://api.finaegis.org'),
        'ws_endpoint'  => env('CSP_WS_ENDPOINT', 'wss://ws.finaegis.org'),

        // Additional connect sources (comma-separated)
        'connect_sources' => env('CSP_CONNECT_SOURCES', 'https://www.google-analytics.com,https://*.google-analytics.com,https://region1.google-analytics.com,https://stats.g.doubleclick.net,https://*.doubleclick.net,https://www.googletagmanager.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS Settings
    |--------------------------------------------------------------------------
    |
    | Configure when HTTPS should be enforced.
    |
    */

    'force_https' => env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Staff Role Allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails of operators whose staff roles were granted by
    | an operator. `php artisan user:audit-staff-roles` flags every other
    | holder of a staff role (and of the roles business team management
    | used to grant) for review.
    |
    */

    'staff_role_allowlist' => env('STAFF_ROLE_ALLOWLIST', ''),

];
