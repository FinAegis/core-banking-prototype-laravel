<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php $brand = config('brand.name'); @endphp

    <title>{{ $brand }} — Agentic Payments | Get Your Card to Spend Anywhere</title>

    @include('partials.favicon')

    @include('partials.seo', [
        'title' => $brand . ' — Agentic Payments',
        'description' => 'Get your personal card to spend anywhere. Get your agent a card to spend anywhere. Stablecoin-powered virtual cards with non-custodial security and privacy built in.',
        'keywords' => $brand . ', agentic payments, AI agent card, stablecoin card, virtual card, crypto payments, non-custodial wallet, tap to pay crypto, privacy payments',
    ])

    {{-- Fonts: Space Grotesk, JetBrains Mono, DM Sans --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&dm-sans:400,500,600,700&jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

    {{-- Pre-compiled Tailwind CSS — standalone, no Vite dependency --}}
    {{-- Rebuild: npx tailwindcss -c tailwind.landing.config.js -i resources/css/landing.css -o public/css/app-landing.css --minify --}}
    <link rel="stylesheet" href="/css/app-landing.css">

    @if(config('brand.ga_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('brand.ga_id') }}"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '{{ config('brand.ga_id') }}');
    </script>
    @endif

    <style>
        /* ── Base ── */
        html { scroll-behavior: smooth; }
        body {
            background: #fff;
            color: #0a0a0a;
            font-family: 'Space Grotesk', system-ui, sans-serif;
            overflow-x: hidden;
        }
        ::selection { background: #ccff00; color: #000; }

        /* ── Missing Tailwind utilities (not in pre-compiled app-landing.css) ── */
        .bg-z-purple { background-color: #c8a8f0; }
        @media (min-width: 768px) {
            .md\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .md\:text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
            .md\:py-20 { padding-top: 5rem; padding-bottom: 5rem; }
        }
        .py-16 { padding-top: 4rem; padding-bottom: 4rem; }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        /* ── Brutalist utilities ── */
        .bru-border { border: 3px solid #0a0a0a; }
        .bru-card { border: 3px solid #0a0a0a; box-shadow: 6px 6px 0px #0a0a0a; }
        .bru-card-sm { border: 3px solid #0a0a0a; box-shadow: 3px 3px 0px #0a0a0a; }
        .bru-card-lg { border: 3px solid #0a0a0a; box-shadow: 8px 8px 0px #0a0a0a; }
        .hs-4 { box-shadow: 4px 4px 0px #0a0a0a; }

        /* ── Animations ── */
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fade-in-left {
            from { opacity: 0; transform: translateX(-40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes fade-in-right {
            from { opacity: 0; transform: translateY(30px) rotate(0deg); }
            to { opacity: 1; transform: translateY(0) rotate(3deg); }
        }
        @keyframes pop-in {
            from { opacity: 0; transform: scale(0); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes z-marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .anim-fade-in-left { animation: fade-in-left 0.7s ease-out both; }
        .anim-fade-in-right { animation: fade-in-right 0.8s ease-out 0.2s both; }
        .anim-fade-in-up { animation: fade-in-up 0.6s ease-out both; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.25); }

        /* ── Skip to content ── */
        .skip-to-content {
            position: absolute; top: -100%; left: 50%;
            transform: translateX(-50%);
            padding: 8px 16px; background: #ccff00; color: #000;
            font-weight: 600; border-radius: 0 0 8px 8px; z-index: 100;
            transition: top 0.2s;
        }
        .skip-to-content:focus { top: 0; }

        /* ── Feature tabs ── */
        .feature-panel { display: none; }
        .feature-panel.active { display: block; animation: fade-in-up 0.3s ease-out; }

        /* ── FAQ accordion ── */
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease, opacity 0.3s ease; opacity: 0; }
        .faq-answer.open { max-height: 300px; opacity: 1; }
        .faq-toggle { transition: transform 0.25s ease; }
        .faq-toggle.open { transform: rotate(45deg); }

        /* ── Button hover + focus ── */
        .btn-hover { transition: transform 0.15s ease; }
        .btn-hover:hover { transform: scale(1.06); }
        .btn-hover:active { transform: scale(0.95); }
        .btn-hover:focus-visible { outline: 3px solid #7000ff; outline-offset: 2px; }

        /* ── Focus visible for interactive elements ── */
        button:focus-visible, a:focus-visible { outline: 3px solid #7000ff; outline-offset: 2px; }

        /* ── Mobile menu ── */
        .mobile-menu { display: none; animation: fade-in-up 0.2s ease-out; }
        .mobile-menu.open { display: flex; }

        /* ── Phone scrollbar hide ── */
        .phone-scroll::-webkit-scrollbar { display: none; }
        .phone-scroll { scrollbar-width: none; }

        /* ── Sticker animations ── */
        .sticker { animation: pop-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
        .sticker-0 { animation-delay: 0.4s; }
        .sticker-1 { animation-delay: 0.6s; }
        .sticker-2 { animation-delay: 0.5s; }
        .sticker-3 { animation-delay: 0.7s; }

        /* ── Reduced motion ── */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
            html { scroll-behavior: auto; }
        }
    </style>

    {{-- JSON-LD Structured Data --}}
    <x-schema type="organization" />
    <x-schema type="software" />
</head>
<body class="antialiased">

    <a href="#main-content" class="skip-to-content">Skip to content</a>

    @php
    $navLinks = ['Features' => '#features', 'Security' => '#security', 'FAQ' => '#faq'];
    @endphp

    {{-- ═══════════════════════════════════════════════════════════════
         NAVIGATION
    ═══════════════════════════════════════════════════════════════ --}}
    <nav class="fixed top-4 left-1/2 z-50 w-[95%] max-w-5xl -translate-x-1/2" aria-label="Main navigation">
        <div class="flex items-center justify-between rounded-full px-4 py-2.5 md:px-6 bru-border hs-4"
             style="background: rgba(255,255,255,0.92); backdrop-filter: blur(12px);">

            {{-- Logo --}}
            <div class="flex items-center gap-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg text-lg font-black font-heading tracking-tighter bg-mint bru-border">
                    Z
                </div>
                <span class="text-xl font-bold hidden sm:inline font-heading tracking-tighter">{{ $brand }}</span>
            </div>

            {{-- Desktop links --}}
            <div class="hidden md:flex items-center gap-6">
                @foreach($navLinks as $label => $href)
                <a href="{{ $href }}" class="text-sm font-medium text-text-sec hover:underline decoration-2 underline-offset-4">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Right CTA --}}
            <div class="flex items-center gap-3">
                <a href="#cta" class="btn-hover rounded-full px-5 py-2 text-sm font-bold bg-acid bru-border text-obsidian">
                    Early Access
                </a>

                {{-- Hamburger (mobile) --}}
                <button id="mobile-toggle" class="md:hidden flex flex-col gap-1.5 p-1" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobile-menu">
                    <span class="block h-0.5 w-5 rounded-full bg-obsidian transition-all"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-obsidian transition-all"></span>
                    <span class="block h-0.5 w-5 rounded-full bg-obsidian transition-all"></span>
                </button>
            </div>
        </div>

        {{-- Mobile dropdown --}}
        <div id="mobile-menu" class="mobile-menu mt-2 flex-col gap-2 rounded-3xl p-5 md:hidden bru-border hs-4"
             style="background: rgba(255,255,255,0.95); backdrop-filter: blur(12px);" aria-hidden="true">
            @foreach($navLinks as $label => $href)
            <a href="{{ $href }}" class="text-base font-medium py-1 mobile-link">{{ $label }}</a>
            @endforeach
        </div>
    </nav>


    <main id="main-content">

    {{-- ═══════════════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="relative pt-28 pb-16 md:pt-36 md:pb-24 px-5"
             style="background: linear-gradient(150deg, #a8f0c4 0%, #c0f5d6 40%, #c8a8f0 100%);">
        <div class="mx-auto max-w-7xl grid grid-cols-1 md:grid-cols-2 gap-12 items-center">

            {{-- Left text --}}
            <div class="anim-fade-in-left">
                <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black leading-[0.95] font-heading tracking-tighter">
                    Secure Stablecoin <span class="text-z-purple">Payments</span>
                </h1>

                <p class="mt-6 text-lg md:text-xl max-w-lg font-medium font-body text-text-sec">
                    The stablecoin wallet that shields your on-chain tracks, ships virtual Visa cards,
                    gives your AI agents a spending budget, and never asks you to trust a third party with your keys.
                </p>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="#cta"
                       class="btn-hover rounded-full px-8 py-3.5 text-base font-bold bru-card bg-acid text-obsidian">
                        Get Early Access
                    </a>
                    <a href="#features"
                       class="btn-hover rounded-full px-8 py-3.5 text-base font-bold bru-card bg-white text-obsidian">
                        How It Works
                    </a>
                </div>

                {{-- App store badges --}}
                <div class="mt-8">
                    <p class="mb-3 text-[10px] uppercase tracking-[0.3em] font-mono text-text-muted">Coming Soon to Mobile</p>
                    <div class="flex gap-3">
                        @foreach([['label' => 'App Store', 'sub' => 'Download on the'], ['label' => 'Google Play', 'sub' => 'Get it on']] as $store)
                        <div class="flex h-10 items-center gap-2 rounded-lg px-4 cursor-not-allowed opacity-50"
                             style="border: 2px solid rgba(10,10,10,0.12); background: rgba(255,255,255,0.5);">
                            <div>
                                <p class="text-[8px] text-text-muted">{{ $store['sub'] }}</p>
                                <p class="text-xs font-semibold text-text-sec">{{ $store['label'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right — phone mockup --}}
            <div class="relative flex justify-center anim-fade-in-right" aria-hidden="true" role="img" aria-label="{{ $brand }} mobile app preview showing wallet balance, transactions, and security status">

                {{-- Floating stickers (desktop only) --}}
                @php
                $stickers = [
                    ['svg' => '/icons/lock.svg', 'pos' => 'top: -2%; left: -2%;', 'rotate' => '-12deg'],
                    ['svg' => '/icons/credit-card.svg', 'pos' => 'top: 5%; right: -5%;', 'rotate' => '8deg'],
                    ['svg' => '/icons/shield.svg', 'pos' => 'bottom: 18%; left: -5%;', 'rotate' => '-6deg'],
                    ['svg' => '/icons/checkmark.svg', 'pos' => 'bottom: 2%; right: -2%;', 'rotate' => '15deg'],
                ];
                @endphp
                @foreach($stickers as $i => $s)
                <div class="absolute z-10 hidden md:block sticker sticker-{{ $i }}" style="{{ $s['pos'] }} transform: rotate({{ $s['rotate'] }});">
                    <div class="rounded-2xl overflow-hidden bru-card" style="width: 64px; height: 64px;">
                        <img src="{{ $s['svg'] }}" alt="" class="h-full w-full">
                    </div>
                </div>
                @endforeach

                {{-- Phone frame --}}
                <div class="relative w-[280px] md:w-[375px] overflow-hidden bru-card-lg"
                     style="border-width: 6px; border-radius: 2.5rem; background: linear-gradient(180deg, #a8f0c4 0%, #c0f5d6 40%, #c8a8f0 100%); aspect-ratio: 9 / 19.5;">
                    <div class="flex h-full flex-col">

                        {{-- Dynamic island --}}
                        <div class="flex justify-center pt-3">
                            <div class="h-[24px] w-[90px] rounded-full bg-black"></div>
                        </div>

                        {{-- Status bar --}}
                        <div class="flex items-center justify-between px-6 py-1.5">
                            <span class="text-[11px] font-medium font-mono">9:41</span>
                            <div class="flex items-center gap-1.5">
                                <div class="flex items-end gap-[2px]">
                                    @foreach([6, 8, 10, 12] as $h)
                                    <div style="width: 3px; height: {{ $h }}px; border-radius: 1px; background: #0a0a0a; opacity: 0.4;"></div>
                                    @endforeach
                                </div>
                                <div style="width: 22px; height: 10px; border-radius: 3px; border: 1.5px solid rgba(0,0,0,0.4); position: relative; margin-left: 2px;">
                                    <div style="position: absolute; top: 2px; left: 2px; width: 14px; height: 4px; border-radius: 1px; background: rgba(0,0,0,0.4);"></div>
                                    <div style="position: absolute; right: -3px; top: 2.5px; width: 2px; height: 4px; border-radius: 0 1px 1px 0; background: rgba(0,0,0,0.4);"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Scrollable app content --}}
                        <div class="flex-1 overflow-y-auto phone-scroll">
                            <div class="px-5 pb-4 flex flex-col gap-4">

                                {{-- Header bar --}}
                                <div class="flex items-center justify-between pt-1">
                                    <div class="flex items-center gap-1.5 rounded-full bg-white bru-border" style="padding: 6px 12px;">
                                        <div class="rounded-full bg-z-green" style="width: 8px; height: 8px;"></div>
                                        <span class="font-mono text-sm font-semibold">Mainnet</span>
                                    </div>

                                    <div class="flex items-center" style="gap: 12px;">
                                        {{-- Streak --}}
                                        <div class="flex items-center" style="gap: 6px; padding: 6px 12px; background: rgba(245,158,11,0.12); border-radius: 9999px; border: 3px solid rgba(245,158,11,0.5);">
                                            <img src="/icons/flame.svg" alt="" style="width: 22px; height: 22px;">
                                            <span class="font-mono" style="font-size: 15px; font-weight: 700;">7</span>
                                        </div>

                                        {{-- Bell --}}
                                        <div class="relative">
                                            <div class="flex items-center justify-center rounded-full bg-white bru-border" style="width: 48px; height: 48px;">
                                                <img src="/icons/bell.svg" alt="" style="width: 30px; height: 30px;">
                                            </div>
                                            <div class="flex items-center justify-center rounded-full bg-z-red absolute" style="top: -2px; right: -4px; width: 18px; height: 18px;">
                                                <span class="text-[10px] font-bold text-white">7</span>
                                            </div>
                                        </div>

                                        {{-- Avatar --}}
                                        <div class="flex items-center justify-center rounded-full bru-card-sm"
                                             style="width: 48px; height: 48px; background: linear-gradient(135deg, #a8f0c4, #c8a8f0);">
                                            <span class="font-heading tracking-tighter text-lg font-bold text-white">A</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Balance card --}}
                                <div class="rounded-[20px] bru-card p-5" style="background: linear-gradient(135deg, #a8f0c4, #a8c8f0);">
                                    <div class="flex items-start justify-between">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-body text-sm font-medium text-text-sec">Total Balance</span>
                                                <div class="flex items-center rounded-full bg-mint bru-border" style="gap: 4px; padding: 3px 8px;">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="#0a0a0a" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7l-9-5Z"/></svg>
                                                    <span class="font-mono text-[11px] font-bold">PROTECTED</span>
                                                </div>
                                            </div>
                                            <div class="flex items-baseline gap-1">
                                                <span style="font-size: 36px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -0.02em;">$1,248.35</span>
                                                <span class="font-mono text-sm font-bold text-text-sec">USDC</span>
                                            </div>
                                            <span class="font-mono text-sm text-text-muted">USDC on Solana</span>
                                        </div>
                                        <div class="flex items-center justify-center bg-bg-tertiary border border-border-subtle" style="width: 44px; height: 44px; border-radius: 10px;">
                                            <img src="/icons/qr-code.svg" alt="" style="width: 28px; height: 28px;">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 mt-4">
                                        <span class="font-mono text-[11px] text-text-muted">WALLET ID</span>
                                        <span class="font-mono text-sm text-text-sec">8xR9k&hellip;4f2Z</span>
                                        <div class="flex-1"></div>
                                        <span class="font-mono text-[11px] text-z-purple font-medium">Copy</span>
                                    </div>
                                </div>

                                {{-- Quick actions --}}
                                <div class="flex justify-center gap-4">
                                    @foreach([['svg' => '/icons/qr-code.svg', 'label' => 'Pay'], ['svg' => '/icons/arrow-down-left.svg', 'label' => 'Receive'], ['svg' => '/icons/arrow-up-right.svg', 'label' => 'Send']] as $action)
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="flex items-center justify-center rounded-full overflow-hidden bru-card-sm" style="width: 56px; height: 56px;">
                                            <img src="{{ $action['svg'] }}" alt="" class="w-full h-full">
                                        </div>
                                        <span class="font-body text-sm font-medium text-text-sec">{{ $action['label'] }}</span>
                                    </div>
                                    @endforeach
                                </div>

                                {{-- Security status --}}
                                <div class="flex flex-col" style="gap: 12px;">
                                    <span class="font-heading tracking-tighter text-xl font-bold">Security Status</span>
                                    <div class="flex" style="gap: 12px;">
                                        @foreach([['icon' => '/icons/shield.svg', 'title' => 'Shielded', 'sub' => 'Privacy Active'], ['icon' => '/icons/shield-check.svg', 'title' => 'Verified', 'sub' => 'On-chain Identity']] as $card)
                                        <div class="flex items-center flex-1 bg-white bru-card" style="gap: 12px; padding: 14px; border-radius: 14px;">
                                            <div class="overflow-hidden bru-border flex-shrink-0" style="width: 44px; height: 44px; border-radius: 10px;">
                                                <img src="{{ $card['icon'] }}" alt="{{ $card['title'] }}" class="w-full h-full">
                                            </div>
                                            <div>
                                                <p class="font-body text-sm font-bold">{{ $card['title'] }}</p>
                                                <p class="font-body text-[13px] text-text-muted">{{ $card['sub'] }}</p>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Recent activity --}}
                                <div class="flex flex-col" style="gap: 16px;">
                                    <div class="flex items-center justify-between">
                                        <span class="font-heading tracking-tighter text-xl font-bold">Recent Activity</span>
                                        <span class="font-body text-sm font-medium text-z-purple">See All</span>
                                    </div>

                                    <div class="overflow-hidden bg-white bru-card" style="border-radius: 16px;">
                                        @php
                                        $transactions = [
                                            ['svg' => '/icons/credit-card.svg', 'name' => 'Merchant Payment', 'date' => 'Today 3:41 PM', 'amount' => '-$42.50', 'pos' => false, 'shielded' => true],
                                            ['svg' => '/icons/arrow-up-right.svg', 'name' => 'Transfer to Wallet', 'date' => 'Yesterday 2:15 PM', 'amount' => '-$14.99', 'pos' => false, 'shielded' => false],
                                            ['svg' => '/icons/arrow-down-left.svg', 'name' => 'USDC Received', 'date' => 'Yesterday 11:30 AM', 'amount' => '+$500.00', 'pos' => true, 'shielded' => false],
                                            ['svg' => '/icons/credit-card.svg', 'name' => 'Starbolt Coffee', 'date' => 'Mar 5 9:20 AM', 'amount' => '-$12.00', 'pos' => false, 'shielded' => true],
                                        ];
                                        @endphp
                                        @foreach($transactions as $i => $tx)
                                        <div>
                                            <div class="flex items-center" style="padding: 12px 16px; gap: 14px;">
                                                <div class="rounded-full overflow-hidden border border-border-subtle flex-shrink-0" style="width: 44px; height: 44px;">
                                                    <img src="{{ $tx['svg'] }}" alt="" class="w-full h-full">
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-body text-[15px] font-medium leading-tight">{{ $tx['name'] }}</p>
                                                    <div class="flex items-center gap-1.5 mt-0.5">
                                                        <span class="font-body text-[13px] text-text-muted">{{ $tx['date'] }}</span>
                                                        <div class="rounded-full bg-z-green" style="width: 5px; height: 5px;"></div>
                                                        <span class="font-body text-[11px] text-z-green">Confirmed</span>
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end flex-shrink-0 gap-0.5">
                                                    <span class="text-sm font-semibold {{ $tx['pos'] ? 'text-z-green' : 'text-obsidian' }}" style="font-variant-numeric: tabular-nums;">{{ $tx['amount'] }}</span>
                                                    @if($tx['shielded'])
                                                    <div class="flex items-center gap-0.5">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="#10b981" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L3 7v5c0 5.5 3.8 10.7 9 12 5.2-1.3 9-6.5 9-12V7l-9-5Z"/></svg>
                                                        <span class="font-mono text-[10px] text-z-green">Protected</span>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($i < count($transactions) - 1)
                                            <div class="bg-border-subtle mx-4" style="height: 1px;"></div>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Bottom tab bar --}}
                        <div class="flex items-center justify-around bg-white" style="padding: 8px; border-top: 3px solid #0a0a0a;">
                            @php
                            $phoneTabs = [
                                ['svg' => '/icons/home.svg', 'label' => 'Home', 'active' => true],
                                ['svg' => '/icons/clock.svg', 'label' => 'Activity', 'active' => false],
                                ['svg' => null, 'label' => 'Pay', 'active' => false],
                                ['svg' => '/icons/star.svg', 'label' => 'Rewards', 'active' => false],
                                ['svg' => '/icons/gear.svg', 'label' => 'Settings', 'active' => false],
                            ];
                            @endphp
                            @foreach($phoneTabs as $tab)
                                @if($tab['svg'])
                                <div class="flex flex-col items-center flex-1" style="gap: 4px; padding: 4px 0;">
                                    <img src="{{ $tab['svg'] }}" alt="" style="width: 26px; height: 26px; opacity: {{ $tab['active'] ? '1' : '0.4' }};">
                                    <span class="font-mono text-[11px] {{ $tab['active'] ? 'text-obsidian' : 'text-text-muted' }}">{{ $tab['label'] }}</span>
                                </div>
                                @else
                                <div class="flex items-center justify-center rounded-full bg-acid bru-card-sm" style="width: 56px; height: 56px; margin-top: -16px;">
                                    <img src="/icons/qr-code.svg" alt="" style="width: 36px; height: 36px;">
                                </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Home indicator --}}
                        <div class="flex justify-center bg-white" style="padding: 6px 0;">
                            <div class="rounded-full bg-bg-tertiary" style="height: 4px; width: 96px;"></div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        {{-- Bottom border --}}
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-obsidian"></div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════
         MARQUEE
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="relative py-4 overflow-hidden bg-obsidian"
             style="border-top: 4px solid #fff; border-bottom: 4px solid #fff; transform: rotate(-1.5deg) scale(1.02);">
        <div class="flex whitespace-nowrap" style="animation: z-marquee 20s linear infinite;">
            @for($dup = 0; $dup < 2; $dup++)
            <span class="text-white text-xl md:text-2xl font-black uppercase tracking-wider font-heading tracking-tighter pr-8">
                SECURE STABLECOINS <span class="text-acid">&#9670;</span> SHIELD PRIVACY
                <span class="text-acid">&#9670;</span> VIRTUAL CARDS
                <span class="text-acid">&#9670;</span> AI AGENT BUDGETS
                <span class="text-acid">&#9670;</span> ZERO TRACE
                <span class="text-acid">&#9670;</span> x402 + MPP PAYMENTS
                <span class="text-acid">&#9670;</span> TRUSTCERT IDENTITY
                <span class="text-acid">&#9670;</span> SPLIT KEY SECURITY
                <span class="text-acid">&#9670;</span> AGENTIC PAYMENTS
                <span class="text-acid">&#9670;</span> NON-CUSTODIAL
                <span class="text-acid">&#9670;</span>
            </span>
            @endfor
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════
         FEATURES
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="features" class="px-5 py-20 md:py-28">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-4xl md:text-5xl font-black mb-4 font-heading tracking-tighter anim-fade-in-up">Built Different.</h2>
            <p class="text-lg mb-10 max-w-xl text-text-sec">
                Everything you need for secure stablecoin payments — nothing you don&apos;t.
            </p>

            {{-- Tab bar --}}
            @php
            $featureTabs = [
                ['id' => 'core', 'label' => 'Core', 'active' => true],
                ['id' => 'pay', 'label' => 'Pay', 'active' => false],
                ['id' => 'security', 'label' => 'Security', 'active' => false],
                ['id' => 'shield', 'label' => 'Shield', 'active' => false],
                ['id' => 'agents', 'label' => 'AI Agents', 'active' => false],
                ['id' => 'identity', 'label' => 'Identity', 'active' => false],
            ];
            @endphp
            <div class="flex gap-2 mb-8 flex-wrap" role="tablist" aria-label="Feature categories">
                @foreach($featureTabs as $tab)
                <button onclick="switchTab('{{ $tab['id'] }}')"
                        class="feature-tab rounded-full px-5 py-2 text-sm font-bold transition-all duration-200 text-obsidian"
                        data-tab="{{ $tab['id'] }}"
                        role="tab"
                        aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
                        aria-controls="panel-{{ $tab['id'] }}"
                        style="background: {{ $tab['active'] ? '#ccff00' : '#f0f0f0' }}; border: 3px solid {{ $tab['active'] ? '#0a0a0a' : '#f0f0f0' }};">
                    {{ $tab['label'] }}
                </button>
                @endforeach
            </div>

            {{-- Feature panels --}}
            @php
            $featurePanels = [
                [
                    'id' => 'core', 'bg' => 'bg-z-blue', 'active' => true,
                    'title' => 'Your Keys, Split Three Ways',
                    'desc' => 'Your private key is split into 3 encrypted shards. No single device or server ever holds the full key.',
                    'type' => 'shard-flow',
                ],
                [
                    'id' => 'pay', 'bg' => 'bg-z-pink', 'active' => false,
                    'title' => 'Pay with Your Card',
                    'desc' => 'Spin up virtual Visa cards instantly. Spend stablecoins at any merchant worldwide — they see a normal card payment, not a crypto wallet.',
                    'type' => 'card-mockup',
                ],
                [
                    'id' => 'security', 'bg' => 'bg-mint', 'active' => false,
                    'title' => 'Biometric Authentication',
                    'desc' => 'Face ID and passkey support. Your wallet is protected by the same biometric security as your device — no passwords to remember or lose.',
                    'type' => 'icon-trio',
                    'icons' => ['/icons/fingerprint.svg', '/icons/user.svg', '/icons/shield.svg'],
                ],
                [
                    'id' => 'shield', 'bg' => 'bg-lavender', 'active' => false,
                    'title' => 'Shield Your Transactions',
                    'desc' => 'Zero-knowledge proofs (ZK-SNARKs) make your balance invisible on-chain. Nobody can trace your spending or total holdings. When compliance is needed, generate a Proof of Innocence — prove your funds are clean without revealing your history.',
                    'type' => 'icon-trio',
                    'icons' => ['/icons/ghost.svg', '/icons/incognito.svg', '/icons/globe.svg'],
                ],
                [
                    'id' => 'agents', 'bg' => 'bg-z-purple', 'active' => false,
                    'title' => 'Give AI Agents a Budget',
                    'desc' => 'Set daily spending limits for autonomous AI agents. They pay for APIs, datasets, and cloud services via x402 micropayments — constrained by per-transaction caps you control. Payments above your threshold require biometric approval.',
                    'type' => 'icon-trio',
                    'icons' => ['/icons/lock.svg', '/icons/credit-card.svg', '/icons/checkmark.svg'],
                ],
                [
                    'id' => 'identity', 'bg' => 'bg-z-green', 'active' => false,
                    'title' => 'TrustCert Identity',
                    'desc' => 'Blockchain-verified credentials issued as Soulbound Tokens. Basic (ID), Verified (address), Enhanced (source of funds). Higher levels unlock larger limits and fiat off-ramps. Verifiable by third parties without exposing your personal data.',
                    'type' => 'icon-trio',
                    'icons' => ['/icons/user.svg', '/icons/shield-check.svg', '/icons/checkmark.svg'],
                ],
            ];
            @endphp

            @foreach($featurePanels as $panel)
            @if($panel['id'] === 'security')
            <div id="security"></div>
            @endif
            <div class="feature-panel{{ $panel['active'] ? ' active' : '' }} p-8 md:p-10 mb-6 {{ $panel['bg'] }} bru-card rounded-[2rem]"
                 id="panel-{{ $panel['id'] }}" role="tabpanel" aria-labelledby="tab-{{ $panel['id'] }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <h3 class="text-2xl md:text-3xl font-black mb-2 font-heading tracking-tighter">{{ $panel['title'] }}</h3>
                        <p class="text-base text-text-sec">{{ $panel['desc'] }}</p>
                    </div>

                    @if($panel['type'] === 'shard-flow')
                    <div class="flex items-center justify-center gap-3 md:gap-4 flex-wrap">
                        @foreach([['icon' => '/icons/smartphone.svg', 'label' => 'Device'], ['icon' => '/icons/lock.svg', 'label' => 'Enclave'], ['icon' => '/icons/server.svg', 'label' => 'Server'], ['icon' => '/icons/checkmark.svg', 'label' => 'Signed']] as $i => $node)
                        <div class="flex items-center gap-3 md:gap-4">
                            <div class="flex flex-col items-center">
                                <div class="w-[64px] h-[64px] md:w-[80px] md:h-[80px] rounded-full overflow-hidden bru-card-sm">
                                    <img src="{{ $node['icon'] }}" alt="{{ $node['label'] }}" class="h-full w-full">
                                </div>
                                <span class="text-[10px] md:text-xs font-bold mt-1.5 font-mono text-text-sec">{{ $node['label'] }}</span>
                            </div>
                            @if($i < 3)
                            <span class="text-lg font-bold text-obsidian" style="margin-top: -1.2rem;">&rarr;</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @elseif($panel['type'] === 'card-mockup')
                    <div class="flex justify-center">
                        <div class="p-5 w-full max-w-[280px] bg-obsidian rounded-3xl" style="transform: rotate(-2deg);">
                            <p class="text-white/50 text-xs uppercase tracking-wider">{{ $brand }} Virtual</p>
                            <p class="text-white text-lg font-bold mt-1 tracking-widest font-mono">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4291</p>
                            <div class="flex justify-between mt-4">
                                <span class="text-white/40 text-xs">Valid thru 12/28</span>
                                <span class="text-white/40 text-xs uppercase">Visa</span>
                            </div>
                        </div>
                    </div>
                    @elseif($panel['type'] === 'icon-trio')
                    <div class="flex gap-4 justify-center">
                        @foreach($panel['icons'] as $src)
                        <div class="w-[72px] h-[72px] md:w-[88px] md:h-[88px] rounded-full overflow-hidden bru-border hs-4">
                            <img src="{{ $src }}" alt="" class="h-full w-full">
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            {{-- 2-column sub-cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach([
                    ['icon' => '/icons/credit-card.svg', 'title' => 'Virtual Visa Cards', 'desc' => 'Generate virtual Visa cards backed by your stablecoin balance. Add to Apple Pay or Google Pay. Merchants see a normal card payment — not a crypto wallet.'],
                    ['icon' => '/icons/incognito.svg', 'title' => 'ZK Privacy Shield', 'desc' => 'Shield and unshield stablecoins with zero-knowledge proofs. Your on-chain balance becomes invisible. Generate Proof of Innocence for compliance without revealing history.'],
                    ['icon' => '/icons/lock.svg', 'title' => 'x402 Agent Payments', 'desc' => 'Give AI agents a spending allowance. Daily budgets, per-transaction caps, and biometric approval for over-limit requests. Your agents pay for APIs autonomously — within your rules.'],
                    ['icon' => '/icons/globe.svg', 'title' => 'Multi-Network', 'desc' => 'Polygon, Base, and Arbitrum from one wallet. Sub-cent fees, instant finality. Switch networks for the best rates. ERC-4337 means you never need ETH for gas.'],
                ] as $subCard)
                <div class="p-8 bg-white bru-card rounded-[2rem]">
                    <div class="rounded-full overflow-hidden mb-4 bru-card-sm" style="width: 56px; height: 56px;">
                        <img src="{{ $subCard['icon'] }}" alt="" class="h-full w-full">
                    </div>
                    <h3 class="text-xl md:text-2xl font-black mb-2 font-heading tracking-tighter">{{ $subCard['title'] }}</h3>
                    <p class="text-base text-text-sec">{{ $subCard['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════
         STATS / POWERED BY
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="px-5 py-16 md:py-20 bg-obsidian">
        <div class="mx-auto max-w-6xl">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                @foreach([
                    ['value' => '3', 'label' => 'Networks', 'sub' => 'Polygon · Base · Arbitrum'],
                    ['value' => 'ZK', 'label' => 'Privacy', 'sub' => 'Zero-Knowledge Proofs'],
                    ['value' => '$0', 'label' => 'Gas Fees', 'sub' => 'ERC-4337 Abstraction'],
                    ['value' => '3', 'label' => 'Pay Protocols', 'sub' => 'x402 · MPP · AP2'],
                ] as $stat)
                <div class="text-center">
                    <p class="text-3xl md:text-4xl font-black text-acid font-heading tracking-tighter">{{ $stat['value'] }}</p>
                    <p class="text-sm font-bold text-white mt-1">{{ $stat['label'] }}</p>
                    <p class="text-xs text-white/40 mt-0.5 font-mono">{{ $stat['sub'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════
         FAQ
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="faq" class="px-5 py-20 md:py-28 bg-bg-tertiary">
        <div class="mx-auto max-w-3xl">
            <h2 class="text-4xl md:text-5xl font-black mb-10 text-center font-heading tracking-tighter">FAQ</h2>

            <div class="flex flex-col gap-4">
                @php
                $faqs = [
                    ['q' => 'What is ' . $brand . '?', 'a' => $brand . ' is a technology platform providing a user interface for services offered by independent third-party providers. ' . $brand . ' does not offer, hold, or transmit funds, crypto-assets, or provide financial, custodial, or regulated services. All wallet functionality is non-custodial — private keys remain under exclusive user control. Financial services are provided by third-party licensed providers.'],
                    ['q' => 'Is ' . $brand . ' a custodial wallet?', 'a' => 'No. ' . $brand . ' is fully non-custodial. Your private key is split into three encrypted shards using Shamir\'s Secret Sharing. One lives in your device\'s Secure Enclave, one in our HSM infrastructure, and one is an optional cloud backup. Any two of three are needed to sign. No seed phrase, no single point of failure. You are responsible for storing your own recovery phrase.'],
                    ['q' => 'How does transaction shielding work?', 'a' => $brand . ' uses zero-knowledge proofs (ZK-SNARKs) to shield your stablecoin balance from public view. Your transactions become unlinkable on-chain — nobody can trace your spending habits or total balance. When compliance is needed, generate a Proof of Innocence: a cryptographic certificate proving your funds are clean, without revealing your history.'],
                    ['q' => 'Do I pay gas fees?', 'a' => 'No. ' . $brand . ' uses ERC-4337 Account Abstraction so you never need to hold ETH. Gas fees are either sponsored or paid in the stablecoin you\'re spending. You think in dollars, not in gwei.'],
                    ['q' => 'What is TrustCert?', 'a' => 'TrustCert is ' . $brand . '\'s identity verification system, issued as non-transferable Soulbound Tokens on-chain. Levels range from Basic (email + phone) to Premium (full KYB). Higher levels unlock larger spending limits and fiat off-ramps. Certificates are verifiable by third parties without accessing your personal data.'],
                    ['q' => 'Can AI agents use ' . $brand . '?', 'a' => 'Yes. ' . $brand . ' supports three payment protocols: x402 (USDC micropayments), MPP (Stripe, Lightning, stablecoins), and AP2 mandates (Google\'s agent authorization). Agents pay for APIs, SMS, cloud services, and datasets autonomously — constrained by daily budgets and per-transaction caps that you control.'],
                    ['q' => 'Which networks are supported?', 'a' => $brand . ' supports Base, Polygon, Arbitrum, Ethereum, and Solana. EVM networks share a single wallet address with sub-cent transaction fees. Solana offers the fastest settlement (~400ms). Switch networks anytime.'],
                ];
                @endphp
                @foreach($faqs as $i => $faq)
                <div class="overflow-hidden bg-white bru-card rounded-3xl">
                    <button onclick="toggleFaq({{ $i }})" class="w-full flex items-center justify-between px-6 py-5 text-left"
                            aria-expanded="false" aria-controls="faq-{{ $i }}">
                        <span class="text-lg md:text-xl font-bold pr-4 font-heading tracking-tighter">{{ $faq['q'] }}</span>
                        <span class="faq-toggle text-2xl font-black flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-xl bg-bg-tertiary bru-border"
                              id="faq-icon-{{ $i }}">+</span>
                    </button>
                    <div class="faq-answer" id="faq-{{ $i }}" role="region">
                        <div class="px-6 pb-5 text-base text-text-sec" style="border-top: 2px solid #e5e5e5;">
                            <p class="pt-4">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- FAQ JSON-LD Schema --}}
            <x-schema type="faq" :data="array_map(fn($f) => ['question' => $f['q'], 'answer' => $f['a']], $faqs)" />
        </div>
    </section>


    {{-- ═══════════════════════════════════════════════════════════════
         CTA
    ═══════════════════════════════════════════════════════════════ --}}
    <section id="cta" class="relative px-5 py-20 md:py-28 overflow-hidden bg-acid">
        <div class="relative z-10 mx-auto max-w-3xl text-center">
            <h2 class="text-4xl sm:text-5xl md:text-6xl font-black mb-6 font-heading tracking-tighter" style="transform: rotate(-2deg);">
                Ready to Go Off-Grid?
            </h2>
            <p class="text-lg md:text-xl mb-10 max-w-xl mx-auto text-text-sec">
                Drop your email and we&apos;ll send you an invite when the beta launches. No spam, just alpha.
            </p>

            <form id="early-access-form" class="flex flex-col sm:flex-row gap-3 max-w-lg mx-auto" aria-label="Early access signup">
                <label for="ea-email" class="sr-only">Email address</label>
                <input type="email" id="ea-email" name="email" required placeholder="your@email.com"
                       class="flex-1 px-6 py-4 text-base outline-none placeholder:text-black/30 bg-white bru-border hs-4 rounded-full">
                <button type="submit"
                        class="btn-hover px-8 py-4 text-base font-bold rounded-full text-white bg-obsidian bru-border hs-4">
                    Get Early Access
                </button>
            </form>

            <div id="early-access-success" class="hidden">
                <div class="inline-block px-8 py-5 rounded-full text-lg font-bold bg-white bru-card text-obsidian">
                    You&apos;re on the list!
                </div>
            </div>

            <div id="early-access-error" class="hidden mt-4">
                <p class="text-sm text-z-red font-medium">Something went wrong. Please try again.</p>
            </div>
        </div>
    </section>

    </main>

    {{-- ═══════════════════════════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════════════════════════ --}}
    <footer class="relative px-5 pt-12 pb-20 md:pt-16 md:pb-28 overflow-hidden bg-white" style="border-top: 8px solid #0a0a0a;">
        {{-- Giant watermark --}}
        <span class="absolute left-1/2 -translate-x-1/2 pointer-events-none select-none font-black font-heading tracking-tighter"
              style="font-size: 10vw; opacity: 0.04; line-height: 1; white-space: nowrap; bottom: 1.5rem;" aria-hidden="true">
            {{ $brand }}
        </span>

        <div class="relative z-10 mx-auto max-w-6xl flex flex-col md:flex-row items-start md:items-center justify-between gap-8">
            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl text-xl font-black font-heading tracking-tighter bg-mint bru-border hs-4">
                    Z
                </div>
                <span class="text-2xl font-black font-heading tracking-tighter">{{ $brand }}</span>
            </div>

            {{-- Center CTA --}}
            <a href="#cta" class="btn-hover rounded-full px-6 py-2.5 text-sm font-bold bg-acid bru-border text-obsidian">
                Early Access
            </a>

            {{-- Links --}}
            <div class="flex flex-wrap gap-x-8 gap-y-3">
                @foreach($navLinks as $label => $href)
                <a href="{{ $href }}" class="text-sm font-medium opacity-60 hover:opacity-100 transition-opacity">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        {{-- Legal disclaimer --}}
        <div class="relative z-10 mx-auto max-w-6xl mt-8 pt-6" style="border-top: 2px solid #e5e5e5;">
            <p class="text-xs opacity-30 leading-relaxed max-w-4xl">
                {{ $brand }} is a technology platform providing a user interface for services offered by independent third-party providers. {{ $brand }} does not offer, hold, or transmit funds or provide financial, custodial, or regulated services. All wallet functionality is non-custodial &mdash; private keys remain under exclusive user control. Financial services are provided by third-party licensed providers. All investments carry risks, including total loss. The user is responsible for their recovery phrase.
            </p>
        </div>

        <div class="relative z-10 mx-auto max-w-6xl mt-6 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4"
             style="border-top: 2px solid #e5e5e5;">
            <p class="text-sm opacity-40">&copy; {{ date('Y') }} {{ $brand }}. All rights reserved.</p>
            <div class="flex gap-6">
                <a href="mailto:{{ config('brand.support_email', 'info@zelta.app') }}" class="text-sm opacity-40 hover:opacity-100 transition-opacity">Contact</a>
                <a href="{{ route('legal.privacy') }}" class="text-sm opacity-40 hover:opacity-100 transition-opacity">Privacy</a>
                <a href="{{ route('legal.terms') }}" class="text-sm opacity-40 hover:opacity-100 transition-opacity">Terms</a>
            </div>
        </div>
    </footer>


    {{-- ═══════════════════════════════════════════════════════════════
         SCRIPTS
    ═══════════════════════════════════════════════════════════════ --}}
    <script>
        // ── Mobile menu toggle ──
        const mobileToggle = document.getElementById('mobile-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileToggle && mobileMenu) {
            mobileToggle.addEventListener('click', () => {
                const isOpen = mobileMenu.classList.toggle('open');
                mobileToggle.setAttribute('aria-expanded', isOpen);
                mobileMenu.setAttribute('aria-hidden', !isOpen);
            });
            document.querySelectorAll('.mobile-link').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('open');
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                });
            });
        }

        // ── Feature tabs ──
        function switchTab(tabName) {
            document.querySelectorAll('.feature-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.feature-tab').forEach(t => {
                t.style.background = '#f0f0f0';
                t.style.borderColor = '#f0f0f0';
                t.setAttribute('aria-selected', 'false');
            });

            const panel = document.getElementById('panel-' + tabName);
            const tab = document.querySelector('[data-tab="' + tabName + '"]');
            if (panel) panel.classList.add('active');
            if (tab) {
                tab.style.background = '#ccff00';
                tab.style.borderColor = '#0a0a0a';
                tab.setAttribute('aria-selected', 'true');
            }
        }

        // ── FAQ accordion ──
        function toggleFaq(index) {
            const answer = document.getElementById('faq-' + index);
            const icon = document.getElementById('faq-icon-' + index);
            const isOpen = answer.classList.contains('open');

            // close all
            document.querySelectorAll('.faq-answer').forEach(a => a.classList.remove('open'));
            document.querySelectorAll('.faq-toggle').forEach(t => {
                t.classList.remove('open');
                t.style.background = '#f0f0f0';
            });
            document.querySelectorAll('[aria-controls^="faq-"]').forEach(b => {
                b.setAttribute('aria-expanded', 'false');
            });

            if (!isOpen) {
                answer.classList.add('open');
                icon.classList.add('open');
                icon.style.background = '#ccff00';
                // find the parent button and set aria-expanded
                icon.closest('button')?.setAttribute('aria-expanded', 'true');
            }
        }

        // ── Early access form ──
        const eaForm = document.getElementById('early-access-form');
        const eaSuccess = document.getElementById('early-access-success');
        const eaError = document.getElementById('early-access-error');
        if (eaForm) {
            eaForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                eaError.classList.add('hidden');
                const email = eaForm.querySelector('input[name="email"]').value;
                if (!email) return;
                try {
                    const response = await fetch('/subscriber/landing', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ email }),
                    });
                    if (!response.ok) throw new Error('Request failed');
                    eaForm.style.display = 'none';
                    eaSuccess.classList.remove('hidden');
                } catch (err) {
                    console.error('Early access signup failed:', err);
                    eaError.classList.remove('hidden');
                }
            });
        }
    </script>

</body>
</html>
