<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Privy Application Identifiers
    |--------------------------------------------------------------------------
    |
    | These values come from the Privy dashboard. The mobile app embeds the
    | same `app_id` so the JWT `aud` claim will match what the backend
    | verifies against. The `app_secret` is reserved for server-to-server
    | calls into the Privy management API.
    |
    */

    'app_id' => env('PRIVY_APP_ID'),

    'app_secret' => env('PRIVY_APP_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | JWKS Endpoint
    |--------------------------------------------------------------------------
    |
    | Public JSON Web Key Set Privy publishes for verifying JWT signatures.
    | The verifier will fetch this once and cache the parsed key set for
    | `jwks_cache_ttl_seconds`. Fully URL-controlled so we can pin to a
    | specific environment (sandbox/production) per deploy.
    |
    */

    'jwks_url' => env('PRIVY_JWKS_URL'),

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    |
    | Constant — Privy always issues tokens with this `iss` claim. We pin it
    | here rather than reading from env so a misconfiguration in deployment
    | can't quietly accept tokens from a forged issuer.
    |
    */

    'issuer' => 'privy.io',

    /*
    |--------------------------------------------------------------------------
    | JWKS Cache TTL (seconds)
    |--------------------------------------------------------------------------
    */

    'jwks_cache_ttl_seconds' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Privy REST API base URL
    |--------------------------------------------------------------------------
    |
    | The host for server-to-server REST calls (email OTP, user lookup, wallet
    | provisioning). Defaults to the production endpoint.
    |
    */

    'api_base_url' => env('PRIVY_API_BASE_URL', 'https://auth.privy.io'),

    /*
    |--------------------------------------------------------------------------
    | Web login feature flag
    |--------------------------------------------------------------------------
    |
    | When true, the public web /login surface uses Privy email-OTP via the
    | server-side REST API. Off by default while we ramp; flip on per
    | environment to migrate web auth off the legacy email+password Jetstream
    | flow. No effect on the existing mobile flow at /api/v1/auth/privy-login.
    |
    */

    'web_login_enabled' => (bool) env('MCP_WEB_PRIVY_LOGIN', false),
];
