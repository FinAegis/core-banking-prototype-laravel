@php
    $brandName = config('brand.name', 'Zelta');
    // Brand-aware default description (finaegis.org demo/promo vs zelta.app) — see docs/REGULATORY-CLAIMS.md
    $defaultDescription = (app()->environment('demo') || config('brand.show_promo_pages'))
        ? $brandName . ' — open-source core banking infrastructure. Apache-2.0 licensed.'
        : $brandName . ' — Non-custodial stablecoin wallet software with passkey sign-in and an agent-callable MCP API. Six networks.';
    // TODO(regulatory): stopgap — images/og-default.png and images/og-twitter.png show card artwork;
    // new OG artwork needed (see docs/REGULATORY-CLAIMS.md). Until then the default share image is
    // the neutral square brand icon, shown as a "summary" card.
    $defaultSocialImage = asset(strtolower((string) $brandName) === 'zelta'
        ? 'brand/zelta/android-chrome-512x512.png'
        : 'brand/finaegis/android-chrome-512x512.png');
@endphp

{{-- SEO Meta Tags --}}
<meta name="description" content="{{ $description ?? $defaultDescription }}">
<meta name="keywords" content="{{ $keywords ?? $brandName . ', non-custodial wallet, stablecoin wallet, passkey, USDC, Solana, Polygon, Base, Arbitrum, MCP server, agent-callable API' }}">
<meta name="author" content="{{ $brandName }}">
<meta name="robots" content="{{ $robots ?? 'index, follow' }}">
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">

{{-- Google Search Console Verification --}}
@if(config('brand.google_site_verification'))
<meta name="google-site-verification" content="{{ config('brand.google_site_verification') }}">
@endif

{{-- Open Graph / Facebook --}}
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:title" content="{{ $title ?? $brandName . ' — Non-custodial stablecoin wallet' }}">
<meta property="og:description" content="{{ $description ?? $defaultDescription }}">
<meta property="og:image" content="{{ $ogImage ?? $defaultSocialImage }}">
<meta property="og:image:width" content="{{ isset($ogImage) ? '1200' : '512' }}">
<meta property="og:image:height" content="{{ isset($ogImage) ? '630' : '512' }}">
<meta property="og:site_name" content="{{ $brandName }}">
<meta property="og:locale" content="en_US">

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ isset($twitterImage) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:url" content="{{ $canonical ?? url()->current() }}">
<meta name="twitter:title" content="{{ $title ?? $brandName . ' — Non-custodial stablecoin wallet' }}">
<meta name="twitter:description" content="{{ $description ?? $defaultDescription }}">
<meta name="twitter:image" content="{{ $twitterImage ?? $defaultSocialImage }}">
<meta name="twitter:domain" content="{{ parse_url(config('app.url'), PHP_URL_HOST) }}">
@if(config('brand.twitter_handle'))
<meta name="twitter:site" content="{{ config('brand.twitter_handle') }}">
<meta name="twitter:creator" content="{{ config('brand.twitter_handle') }}">
@endif

{{-- Additional SEO Tags --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ $brandName }}">
<meta name="application-name" content="{{ $brandName }}">

{{-- Schema.org JSON-LD --}}
@if(isset($schema))
{!! $schema !!}
@endif