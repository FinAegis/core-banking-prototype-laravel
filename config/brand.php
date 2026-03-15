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

    'name' => env('APP_BRAND', env('APP_NAME', 'FinAegis')),

    'tagline' => env('BRAND_TAGLINE', 'Agentic Payments. Spend Anywhere.'),

    'support_email' => env('BRAND_SUPPORT_EMAIL', 'info@finaegis.org'),

    'legal_email' => env('BRAND_LEGAL_EMAIL', 'legal@finaegis.org'),

    'privacy_email' => env('BRAND_PRIVACY_EMAIL', 'privacy@finaegis.org'),

    'legal_entity' => env('BRAND_LEGAL_ENTITY', 'FinAegis'),

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

];
