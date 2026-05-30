<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('brand.name', 'Zelta'))</title>

    @include('partials.favicon')

    @section('seo')
        @include('partials.seo', [
            'title' => config('brand.name', 'Zelta'),
            'description' => config('brand.name', 'Zelta') . ' — Non-custodial stablecoin wallet with passkey sign-in, virtual Visa & Mastercard cards, bank-rail deposits, and an agent-callable MCP API. Six networks.',
            'keywords' => config('brand.name', 'Zelta') . ', non-custodial wallet, stablecoin wallet, virtual card, passkey, USDC, Solana, Polygon, Base, Arbitrum, MCP server, agent-callable API',
        ])
    @show

    <!-- DNS Prefetch & Preconnect -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="dns-prefetch" href="https://fonts.bunny.net">
    @if(config('brand.ga_id'))
    <link rel="preconnect" href="https://www.googletagmanager.com">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    @endif

    <!-- Fonts -->
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|plus-jakarta-sans:500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Custom Styles -->
    @stack('styles')

    @if(config('brand.ga_id'))
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('brand.ga_id') }}"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '{{ config('brand.ga_id') }}');
    </script>
    @endif
</head>
<body class="font-sans antialiased bg-white text-slate-900">
    <x-platform-banners />

    <!-- Navigation -->
    @include('partials.public-nav')

    <!-- Page Content -->
    <main class="page-enter">
        @yield('content')
    </main>

    <!-- Footer -->
    @include('partials.footer')

    <!-- Scroll Animation Observer -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

            document.querySelectorAll('.animate-on-scroll').forEach(function(el) {
                observer.observe(el);
            });
        });
    </script>

    @stack('scripts')
</body>
</html>