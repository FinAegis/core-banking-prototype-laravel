{{-- SEO Meta Tags --}}
<meta name="description" content="{{ $description ?? 'FinAegis - The Enterprise Financial Platform powering the future of banking with democratic governance and real bank integration.' }}">
<meta name="keywords" content="{{ $keywords ?? 'FinAegis, core banking, financial platform, GCU, global currency unit, open banking, API banking, fintech' }}">
<meta name="author" content="FinAegis">
<meta name="robots" content="index, follow">
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">

{{-- Open Graph / Facebook --}}
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:title" content="{{ $title ?? config('app.name') }} | FinAegis">
<meta property="og:description" content="{{ $description ?? 'FinAegis - The Enterprise Financial Platform powering the future of banking with democratic governance and real bank integration.' }}">
<meta property="og:image" content="{{ $ogImage ?? asset('images/og-default.png') }}">
<meta property="og:site_name" content="FinAegis">
<meta property="og:locale" content="en_US">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $canonical ?? url()->current() }}">
<meta name="twitter:title" content="{{ $title ?? config('app.name') }} | FinAegis">
<meta name="twitter:description" content="{{ $description ?? 'FinAegis - The Enterprise Financial Platform powering the future of banking with democratic governance and real bank integration.' }}">
<meta name="twitter:image" content="{{ $ogImage ?? asset('images/og-default.png') }}">

{{-- Additional SEO Tags --}}
<meta name="apple-mobile-web-app-title" content="FinAegis">
<meta name="application-name" content="FinAegis">

{{-- Schema.org JSON-LD --}}
@if(isset($schema))
{!! $schema !!}
@endif