@php $brandName = config('brand.name', 'Zelta'); @endphp

{{-- SEO Meta Tags --}}
<meta name="description" content="{{ $description ?? $brandName . ' — Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks.' }}">
<meta name="keywords" content="{{ $keywords ?? $brandName . ', non-custodial wallet, stablecoin wallet, virtual card, passkey, USDC, Solana, Polygon, Base, Arbitrum, MCP server, agent-callable API' }}">
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
<meta property="og:description" content="{{ $description ?? $brandName . ' — Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks.' }}">
<meta property="og:image" content="{{ $ogImage ?? asset('images/og-default.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="{{ $brandName }}">
<meta property="og:locale" content="en_US">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $canonical ?? url()->current() }}">
<meta name="twitter:title" content="{{ $title ?? $brandName . ' — Non-custodial stablecoin wallet' }}">
<meta name="twitter:description" content="{{ $description ?? $brandName . ' — Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks.' }}">
<meta name="twitter:image" content="{{ $twitterImage ?? asset('images/og-twitter.png') }}">
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