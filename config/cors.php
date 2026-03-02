<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'v1/*', 'v2/*', 'auth/*', 'broadcasting/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_merge(
        // Production origins (env-driven for multi-domain deployment)
        array_filter(explode(',', env('CORS_PRODUCTION_ORIGINS', 'https://finaegis.org,https://www.finaegis.org,https://api.finaegis.org,https://app.finaegis.org,https://dashboard.finaegis.org'))),
        // Frontend URL (configurable per environment)
        array_filter([env('FRONTEND_URL')]),
        // Local development origins (set to empty string in production to disable)
        explode(',', env('CORS_LOCAL_ORIGINS', 'http://localhost:3000,http://localhost:8080,http://localhost:5173,http://localhost:8081'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN', 'Accept', 'Origin', 'X-Client-Platform', 'X-Client-Version', 'X-Payment-Version'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
