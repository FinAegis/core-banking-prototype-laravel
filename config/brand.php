<?php

// SPDX-License-Identifier: Apache-2.0
// Copyright (c) 2024-2026 FinAegis Contributors

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Configuration
    |--------------------------------------------------------------------------
    |
    | Centralizes all brand-dependent values so the same codebase can serve
    | multiple domains (e.g. finaegis.org demo vs zelta.app production).
    |
    */

    'name' => env('APP_BRAND', env('APP_NAME', 'Zelta')),

    'tagline' => env('BRAND_TAGLINE', 'Agentic Payments. Spend Anywhere.'),

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@zelta.app'),

    'legal_email' => env('BRAND_LEGAL_EMAIL', 'legal@zelta.app'),

    'privacy_email' => env('BRAND_PRIVACY_EMAIL', 'privacy@zelta.app'),

    'legal_entity' => env('BRAND_LEGAL_ENTITY', 'Zelta'),

    'legal_jurisdiction' => env('BRAND_LEGAL_JURISDICTION', 'Vilnius, Lithuania'),

    'github_url' => env('BRAND_GITHUB_URL', 'https://github.com/FinAegis/core-banking-prototype-laravel'),

    'ga_id' => env('GOOGLE_ANALYTICS_ID', 'G-X65KH9NFMY'),

    /*
    |--------------------------------------------------------------------------
    | Environment-driven visibility flags
    |--------------------------------------------------------------------------
    |
    | In production (zelta.app), promotional/demo pages are hidden and API
    | documentation requires authentication.
    |
    */

    'show_promo_pages' => env('SHOW_PROMO_PAGES', true),

    'show_api_docs_publicly' => env('SHOW_API_DOCS_PUBLICLY', true),

    /*
    |--------------------------------------------------------------------------
    | Favicon & Theme
    |--------------------------------------------------------------------------
    |
    | Brand-specific favicons and theme color. Set BRAND_FAVICON_PATH to a
    | subdirectory under public/brand/ (e.g. 'finaegis' or 'zelta').
    | When empty, falls back to root public/ favicons.
    |
    */

    'favicon_path' => env('BRAND_FAVICON_PATH', ''),

    'theme_color' => env('BRAND_THEME_COLOR', '#0c1222'),

];
