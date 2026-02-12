<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Versioning Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the API version registry including which versions are
    | supported, deprecated, or scheduled for sunset. The ApiVersionMiddleware
    | reads this config to inject RFC 8594 Deprecation and Sunset headers.
    |
    */

    'current_version' => env('API_CURRENT_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Version Registry
    |--------------------------------------------------------------------------
    |
    | Each version entry controls:
    |  - supported:  Whether requests to this version are accepted
    |  - deprecated: If true, a Deprecation header (RFC 8594) is added
    |  - deprecated_at: ISO 8601 date when deprecation was announced
    |  - sunset: ISO 8601 date after which the version will be removed
    |
    */

    'versions' => [
        'v1' => [
            'supported'     => true,
            'deprecated'    => false,
            'deprecated_at' => null,
            'sunset'        => null,
        ],
        'v2' => [
            'supported'     => true,
            'deprecated'    => false,
            'deprecated_at' => null,
            'sunset'        => null,
        ],
    ],

];
