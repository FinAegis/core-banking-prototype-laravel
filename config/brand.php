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

    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION', ''),

    'twitter_handle' => env('BRAND_TWITTER_HANDLE', ''),

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
    | Admin Panel — Visible Module Groups
    |--------------------------------------------------------------------------
    |
    | Controls which Filament navigation groups appear in /admin.
    | Set ADMIN_MODULES in .env as comma-separated list, or leave empty for all.
    |
    | Zelta (mobile wallet):
    |   ADMIN_MODULES=System,Mobile,Relayer,TrustCert,Banking,AI Agents,Commerce,Mobile Payments,Privacy,Fraud Detection
    |
    | FinAegis (full platform): leave empty or don't set — shows everything.
    |
    */

    'admin_modules' => env('ADMIN_MODULES')
        ? array_map('trim', explode(',', (string) env('ADMIN_MODULES')))
        : null, // null = show all

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
