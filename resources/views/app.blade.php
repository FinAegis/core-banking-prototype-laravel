<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>FinAegis App — Pay with Stablecoins. Stay Private.</title>

    @include('partials.favicon')

    @include('partials.seo', [
        'title' => 'FinAegis App — Pay with Stablecoins in Shops',
        'description' => 'Pay at any shop with your stablecoin card. Your transactions stay private. Your identity stays yours. Get early access to the FinAegis mobile wallet.',
        'keywords' => 'FinAegis, stablecoin wallet, USDC payments, privacy wallet, tap to pay crypto, shielded transactions, Super KYC',
    ])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Standalone Tailwind CSS — pre-compiled for this page only, no Vite dependency --}}
    <link rel="stylesheet" href="/css/app-landing.css">

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-X65KH9NFMY"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-X65KH9NFMY');
    </script>

    <style>
        :root {
            --bg-dark: #060810;
            --bg-card: #0d1017;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-purple: #8b5cf6;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border-subtle: rgba(255, 255, 255, 0.06);
        }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Grid background */
        .grid-bg {
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* Gradient overlays */
        .hero-glow {
            background:
                radial-gradient(ellipse 60% 50% at 25% 20%, rgba(59,130,246,0.10) 0%, transparent 70%),
                radial-gradient(ellipse 40% 40% at 80% 60%, rgba(16,185,129,0.06) 0%, transparent 70%);
        }

        /* Glass card */
        .glass-card {
            background: rgba(13, 16, 23, 0.80);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-subtle);
        }

        /* Glow border button */
        .glow-btn {
            position: relative;
            box-shadow: 0 0 20px rgba(59,130,246,0.20), inset 0 1px 0 rgba(255,255,255,0.05);
            border: 1px solid rgba(59,130,246,0.40);
            transition: all 0.3s ease;
        }
        .glow-btn:hover {
            box-shadow: 0 0 30px rgba(59,130,246,0.35), inset 0 1px 0 rgba(255,255,255,0.08);
            border-color: rgba(59,130,246,0.60);
        }

        /* Shimmer on CTA */
        .shimmer {
            position: relative;
            overflow: hidden;
        }
        .shimmer::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 200%; height: 100%;
            background: linear-gradient(to right, transparent 0%, rgba(255,255,255,0.12) 50%, transparent 100%);
            animation: shimmer 4s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { left: -100%; }
            30% { left: 100%; }
        }

        /* Holographic card effect */
        .holo-card {
            position: relative;
            overflow: hidden;
        }
        .holo-card::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: linear-gradient(
                45deg,
                transparent 40%,
                rgba(255,255,255,0.06) 45%,
                rgba(255,255,255,0.12) 50%,
                rgba(255,255,255,0.06) 55%,
                transparent 60%
            );
            transform: rotate(30deg);
            pointer-events: none;
            animation: holo-pan 8s ease-in-out infinite;
        }
        @keyframes holo-pan {
            0%, 100% { transform: translateY(0) rotate(30deg); }
            50% { transform: translateY(-15px) rotate(30deg); }
        }

        /* Phone reflection */
        .phone-reflection {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(120deg, rgba(255,255,255,0.06) 0%, transparent 35%, transparent 65%, rgba(255,255,255,0.03) 100%);
            pointer-events: none;
            z-index: 15;
            border-radius: 2.5rem;
        }

        /* Dynamic island */
        .dynamic-island {
            width: 80px; height: 22px;
            background: #000;
            border-radius: 16px;
            position: absolute;
            top: 8px; left: 50%;
            transform: translateX(-50%);
            z-index: 20;
        }

        /* Gold gradient text (for KYC badge) */
        .gold-text {
            background: linear-gradient(to bottom, #FDE68A, #D97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Data stream animation */
        .data-stream {
            position: relative;
            overflow: hidden;
        }
        .data-stream::after {
            content: '';
            position: absolute;
            top: -100%; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, transparent, var(--accent-blue), var(--accent-green), transparent);
            animation: stream-flow 2.5s ease-in-out infinite;
        }
        @keyframes stream-flow {
            0% { top: -100%; opacity: 0; }
            30% { opacity: 1; }
            70% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        .data-stream-delayed::after {
            animation-delay: 1s;
        }

        /* Feature card hover */
        .feature-card {
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            border-color: rgba(59,130,246,0.25);
            transform: translateY(-2px);
        }
        .feature-card:hover .feature-icon {
            transform: translateY(-3px);
        }
        .feature-icon {
            transition: transform 0.3s ease;
        }

        /* Floating animation */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        /* Subtle noise overlay */
        .noise::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            mix-blend-mode: overlay;
        }

        /* FAQ styling */
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }

        /* Success animation */
        @keyframes check-in {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-check { animation: check-in 0.4s ease-out; }

        /* Shard connector lines */
        .shard-line {
            stroke-dasharray: 6 4;
            animation: dash-flow 1.5s linear infinite;
        }
        @keyframes dash-flow {
            to { stroke-dashoffset: -20; }
        }

        /* Smooth scroll */
        html { scroll-behavior: smooth; }

        /* App store badge styling */
        .store-badge {
            opacity: 0.35;
            filter: grayscale(1);
            transition: all 0.3s ease;
            cursor: not-allowed;
        }
        .store-badge.active {
            opacity: 1;
            filter: none;
            cursor: pointer;
        }

        /* Accessibility: respect reduced motion preferences */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    {{-- ═══════════════════════════════════════════════════════════════
         NAVIGATION
    ═══════════════════════════════════════════════════════════════ --}}
    <header class="fixed w-full top-0 z-50 glass-card border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 lg:h-20">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center shadow-lg shadow-blue-500/20 ring-1 ring-white/10 transition-transform group-hover:scale-105">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                    <span class="font-bold text-lg tracking-tight text-white">FinAegis</span>
                </a>

                {{-- Desktop Nav --}}
                <nav class="hidden md:flex items-center gap-8 absolute left-1/2 transform -translate-x-1/2">
                    <a href="#features" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Features</a>
                    <a href="#security" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Security</a>
                    <a href="#platform" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">Platform</a>
                    <a href="#faq" class="text-sm font-medium text-slate-400 hover:text-white transition-colors">FAQ</a>
                </nav>

                {{-- Right --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="hidden sm:inline-flex text-sm text-slate-400 hover:text-white transition-colors">Core Platform</a>
                    <a href="#early-access" class="px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-sm font-semibold text-white hover:bg-white/10 transition-all flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Early Access
                    </a>
                    <button id="mobile-nav-toggle" class="md:hidden p-2 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile Nav --}}
        <div id="mobile-nav" class="hidden md:hidden border-t border-white/5 bg-[#0d1017]/95 backdrop-blur-xl">
            <div class="px-4 py-4 space-y-1">
                <a href="#features" class="block px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors">Features</a>
                <a href="#security" class="block px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors">Security</a>
                <a href="#platform" class="block px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors">Platform</a>
                <a href="#faq" class="block px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-white/5 rounded-lg transition-colors">FAQ</a>
                <div class="border-t border-white/5 pt-2 mt-2">
                    <a href="{{ route('home') }}" class="block px-3 py-2 text-sm text-blue-400 hover:text-blue-300 rounded-lg transition-colors">Back to Core Platform</a>
                </div>
            </div>
        </div>
    </header>

    {{-- ═══════════════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════════════ --}}
    <main class="flex-grow">
        <section class="relative pt-28 lg:pt-36 pb-16 lg:pb-24 hero-glow grid-bg noise overflow-hidden" id="early-access">
            {{-- Decorative orbs --}}
            <div class="absolute top-20 left-[10%] w-[500px] h-[500px] bg-blue-500/[0.04] rounded-full blur-[120px] pointer-events-none"></div>
            <div class="absolute bottom-0 right-[5%] w-[400px] h-[400px] bg-emerald-500/[0.03] rounded-full blur-[100px] pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    {{-- Left: Copy --}}
                    <div class="space-y-8 text-center lg:text-left">
                        <div class="space-y-5">
                            <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-[4.25rem] font-extrabold leading-[1.08] tracking-tight text-white">
                                Spend Stablecoins.<br>
                                <span class="bg-gradient-to-r from-blue-400 to-blue-500 bg-clip-text text-transparent">Shield Your History.</span><br>
                                Unlock the Exclusive.
                            </h1>
                            <p class="text-lg lg:text-xl text-slate-400 max-w-xl mx-auto lg:mx-0 leading-relaxed font-light">
                                Pay at any shop with your stablecoin card. Your transactions stay private. Your identity stays yours.
                            </p>
                        </div>

                        {{-- Email Signup --}}
                        <div class="max-w-md mx-auto lg:mx-0" id="signup-container">
                            <form id="early-access-form" class="flex flex-col sm:flex-row gap-3">
                                <input
                                    id="early-access-email"
                                    type="email"
                                    required
                                    placeholder="Enter your email for early access"
                                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 outline-none transition-all text-sm"
                                >
                                <button type="submit" id="signup-btn" class="glow-btn shimmer bg-[#111827] hover:bg-[#1a2332] text-white px-6 py-3.5 rounded-xl font-semibold transition-all whitespace-nowrap flex items-center justify-center gap-2 text-sm">
                                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                    Get Early Access
                                </button>
                            </form>
                            {{-- Success state (hidden by default) --}}
                            <div id="signup-success" class="hidden">
                                <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-4">
                                    <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center flex-shrink-0 success-check">
                                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-emerald-300">You're on the list.</p>
                                        <p class="text-xs text-slate-400 mt-0.5">We'll notify you when the app is ready.</p>
                                    </div>
                                </div>
                            </div>
                            {{-- Error state (hidden by default) --}}
                            <div id="signup-error" class="hidden mt-2">
                                <p class="text-xs text-red-400" id="signup-error-msg"></p>
                            </div>
                        </div>

                        {{-- App Store Badges --}}
                        <div class="pt-1 border-t border-white/5">
                            <p class="text-[11px] uppercase tracking-widest text-slate-600 font-semibold mb-4">Coming soon to mobile</p>
                            <div class="flex gap-3 justify-center lg:justify-start" id="store-badges">
                                <div class="store-badge">
                                    <div class="bg-[#111827] border border-white/5 rounded-xl py-2 px-4 flex items-center gap-3 h-12">
                                        <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                                        <div class="text-left leading-none">
                                            <div class="text-[8px] uppercase text-slate-500">Download on the</div>
                                            <div class="text-sm font-semibold text-white">App Store</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="store-badge">
                                    <div class="bg-[#111827] border border-white/5 rounded-xl py-2 px-4 flex items-center gap-3 h-12">
                                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M3 20.5V3.5c0-.59.34-1.11.84-1.35L13.69 12l-9.85 9.85c-.5-.24-.84-.76-.84-1.35zm13.81-5.38L6.05 21.34l8.49-8.49 2.27 2.27zm3.35-4.31c.34.27.59.69.59 1.19 0 .5-.25.92-.59 1.19l-2.27 1.31-2.5-2.5 2.5-2.5 2.27 1.31zM6.05 2.66l10.76 6.22-2.27 2.27-8.49-8.49z"/></svg>
                                        <div class="text-left leading-none">
                                            <div class="text-[8px] uppercase text-slate-500">Get it on</div>
                                            <div class="text-sm font-semibold text-white">Google Play</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Phone Mockup --}}
                    <div class="relative flex items-center justify-center lg:justify-end">
                        {{-- Glow behind phone --}}
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="w-[320px] h-[500px] bg-blue-500/[0.08] rounded-full blur-[80px]"></div>
                        </div>

                        <div class="relative w-[300px] sm:w-[320px] mx-auto" style="animation: float 6s ease-in-out infinite;">
                            {{-- Phone frame --}}
                            <div class="bg-[#0a0d12] rounded-[2.5rem] border-[5px] border-[#1e2330] shadow-2xl shadow-black/50 overflow-hidden relative">
                                <div class="dynamic-island"></div>
                                <div class="phone-reflection"></div>

                                {{-- Status bar --}}
                                <div class="px-7 pt-3 flex justify-between items-center text-[10px] font-medium text-white/80 relative z-10">
                                    <span>9:41</span>
                                    <div class="flex gap-1">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3c-1.65-1.66-4.34-1.66-6 0zm-4-4l2 2c2.76-2.76 7.24-2.76 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/></svg>
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4z"/></svg>
                                    </div>
                                </div>

                                {{-- App header --}}
                                <div class="px-5 py-3 flex justify-between items-center border-b border-white/5 bg-white/[0.03] relative z-10">
                                    <span class="text-[11px] font-bold text-white tracking-widest uppercase">Virtual Card</span>
                                    <div class="flex items-center gap-1 bg-black/30 border border-yellow-500/25 px-2 py-0.5 rounded-full">
                                        <svg class="w-2.5 h-2.5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                                        <span class="text-[9px] font-bold gold-text uppercase">Verified</span>
                                    </div>
                                </div>

                                {{-- App content --}}
                                <div class="p-5 space-y-5 relative z-10 bg-gradient-to-b from-[#0a0d12] to-[#050608]">
                                    {{-- Balance --}}
                                    <div>
                                        <div class="text-[10px] text-slate-500 font-mono tracking-wider uppercase mb-1">USDC Balance</div>
                                        <div class="text-3xl font-extrabold text-white tracking-tight flex items-start gap-1">
                                            <span class="text-lg pt-0.5 text-slate-500">$</span>4,291<span class="text-slate-400">.50</span>
                                        </div>
                                    </div>

                                    {{-- Card --}}
                                    <div class="h-40 rounded-2xl bg-gradient-to-br from-blue-900 to-indigo-950 border border-white/10 p-4 relative overflow-hidden shadow-xl holo-card">
                                        {{-- Chip --}}
                                        <div class="w-9 h-6 bg-gradient-to-br from-yellow-200 to-yellow-500 rounded-md border border-yellow-600/40 mb-10 relative">
                                            <div class="absolute inset-0 border border-black/10 rounded-md"></div>
                                            <div class="absolute top-1/2 left-0 w-full h-px bg-black/15"></div>
                                            <div class="absolute left-1/2 top-0 h-full w-px bg-black/15"></div>
                                        </div>
                                        {{-- Card details --}}
                                        <div class="flex justify-between items-end absolute bottom-4 left-4 right-4">
                                            <div>
                                                <div class="text-[13px] font-mono text-gray-200 tracking-widest">---- 8842</div>
                                                <div class="text-[9px] text-slate-400 mt-0.5 font-medium">VIRTUAL</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-[7px] text-slate-400 uppercase tracking-wider">Valid Thru</div>
                                                <div class="text-[11px] text-white font-mono font-bold">12/28</div>
                                            </div>
                                        </div>
                                        {{-- Mastercard circles --}}
                                        <div class="absolute top-4 right-4 flex -space-x-2.5 opacity-70">
                                            <div class="w-7 h-7 rounded-full bg-white/15"></div>
                                            <div class="w-7 h-7 rounded-full bg-white/15"></div>
                                        </div>
                                    </div>

                                    {{-- Transaction --}}
                                    <div class="bg-[#0d1017] rounded-xl p-3.5 flex items-center justify-between border border-white/5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z"/></svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-white">Starbucks</div>
                                                <div class="text-[10px] text-slate-500">Tap to Pay</div>
                                            </div>
                                        </div>
                                        <span class="text-sm font-semibold text-white">-$5.50</span>
                                    </div>

                                    {{-- Quick actions --}}
                                    <div class="grid grid-cols-4 gap-2 pt-1">
                                        <button class="flex flex-col items-center gap-1.5 bg-blue-600/90 rounded-xl py-3 text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                                            <span class="text-[10px] font-medium">Send</span>
                                        </button>
                                        <button class="flex flex-col items-center gap-1.5 bg-white/5 rounded-xl py-3 text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25"/></svg>
                                            <span class="text-[10px] font-medium">Receive</span>
                                        </button>
                                        <button class="flex flex-col items-center gap-1.5 bg-white/5 rounded-xl py-3 text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                            <span class="text-[10px] font-medium">Swap</span>
                                        </button>
                                        <button class="flex flex-col items-center gap-1.5 bg-white/5 rounded-xl py-3 text-white">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                                            <span class="text-[10px] font-medium">Shield</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Home indicator --}}
                                <div class="flex justify-center pb-2 relative z-10">
                                    <div class="w-28 h-1 rounded-full bg-white/20"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             FEATURES
        ═══════════════════════════════════════════════════════════════ --}}
        <section class="py-20 lg:py-28 bg-[var(--bg-dark)] border-t border-white/5 relative grid-bg" id="features">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <h2 class="text-3xl lg:text-4xl font-bold text-white tracking-tight">What makes it different</h2>
                    <p class="text-slate-400 mt-3 max-w-lg mx-auto">Three things no other wallet does together.</p>
                </div>

                <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                    {{-- Feature 1: Pay with Your Card --}}
                    <div class="feature-card glass-card p-7 lg:p-8 rounded-2xl border border-white/5 group noise relative">
                        <div class="feature-icon w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/15 flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">Pay with Your Card</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            Tap your card at any shop. Pay with USDC &mdash; your card handles the conversion automatically. Add it to Apple Pay or Google Pay and go.
                        </p>
                    </div>

                    {{-- Feature 2: Shield Your Transactions --}}
                    <div class="feature-card glass-card p-7 lg:p-8 rounded-2xl border border-white/5 group noise relative">
                        <div class="feature-icon w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/15 flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">Shield Your Transactions</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            The shop gets paid. Nobody else sees your balance or spending history. Not a mixer &mdash; a privacy layer with encrypted transaction shielding and full regulatory compliance.
                        </p>
                    </div>

                    {{-- Feature 3: Verified Beyond Standard --}}
                    <div class="feature-card glass-card p-7 lg:p-8 rounded-2xl border border-white/5 group noise relative">
                        <div class="feature-icon w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/15 flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3">Verified Beyond Standard</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            Our Super KYC goes beyond checkbox compliance. We verify that companies are truly legitimate and not sanctioned. Built for businesses handling dual-use goods or regulated services.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             SECURITY / HOW YOUR KEYS STAY SAFE
        ═══════════════════════════════════════════════════════════════ --}}
        <section class="py-20 lg:py-28 bg-[#080a0e] border-t border-white/5 relative overflow-hidden" id="security">
            {{-- Background decoration --}}
            <div class="absolute top-0 right-0 w-1/3 h-full bg-gradient-to-l from-blue-900/[0.04] to-transparent pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                    {{-- Left: Shard Diagram --}}
                    <div class="order-2 lg:order-1 flex justify-center">
                        <div class="relative bg-[#0d1017] rounded-2xl border border-white/[0.06] p-10 lg:p-14 shadow-2xl w-full max-w-md">
                            <div class="relative w-full aspect-square max-w-[280px] mx-auto">
                                {{-- Center: Master Key --}}
                                <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-28 h-28 rounded-full bg-[#080a0e] border-2 border-dashed border-slate-700/60 flex items-center justify-center z-10">
                                    <div class="text-center">
                                        <svg class="w-5 h-5 text-slate-500 mx-auto mb-1" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                                        <span class="text-[10px] text-slate-500 font-mono leading-tight block">Private Key</span>
                                        <span class="text-[8px] text-slate-600 font-mono">(never exposed)</span>
                                    </div>
                                </div>

                                {{-- Shard A: Device --}}
                                <div class="absolute top-0 left-1/2 transform -translate-x-1/2 w-20 h-20 bg-[#080a0e] rounded-2xl border border-blue-500/40 shadow-[0_0_20px_rgba(59,130,246,0.12)] flex flex-col items-center justify-center z-20">
                                    <svg class="w-5 h-5 text-blue-400 mb-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                                    <span class="text-[9px] text-slate-400 font-mono">Phone</span>
                                </div>

                                {{-- Shard B: Server --}}
                                <div class="absolute bottom-0 left-2 w-20 h-20 bg-[#080a0e] rounded-2xl border border-emerald-500/40 shadow-[0_0_20px_rgba(16,185,129,0.12)] flex flex-col items-center justify-center z-20">
                                    <svg class="w-5 h-5 text-emerald-400 mb-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>
                                    <span class="text-[9px] text-slate-400 font-mono">HSM</span>
                                </div>

                                {{-- Shard C: Cloud --}}
                                <div class="absolute bottom-0 right-2 w-20 h-20 bg-[#080a0e] rounded-2xl border border-purple-500/40 shadow-[0_0_20px_rgba(139,92,246,0.12)] flex flex-col items-center justify-center z-20">
                                    <svg class="w-5 h-5 text-purple-400 mb-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/></svg>
                                    <span class="text-[9px] text-slate-400 font-mono">Backup</span>
                                </div>

                                {{-- Connector lines --}}
                                <svg class="absolute inset-0 w-full h-full pointer-events-none" style="z-index: 5;">
                                    <line x1="50%" y1="48%" x2="50%" y2="18%" stroke="#3b82f6" stroke-width="1.5" opacity="0.25" class="shard-line"/>
                                    <line x1="46%" y1="55%" x2="22%" y2="78%" stroke="#10b981" stroke-width="1.5" opacity="0.25" class="shard-line"/>
                                    <line x1="54%" y1="55%" x2="78%" y2="78%" stroke="#8b5cf6" stroke-width="1.5" opacity="0.25" class="shard-line"/>
                                </svg>
                            </div>

                            <div class="text-center mt-8">
                                <p class="text-sm font-semibold text-white">2-of-3 Shards Required</p>
                                <p class="text-xs text-slate-500 mt-1">Lose your phone? You're still fine.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Right: Explanation --}}
                    <div class="order-1 lg:order-2 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/15 text-blue-400 text-[11px] font-semibold uppercase tracking-wider">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            Threshold Cryptography
                        </div>

                        <h2 class="text-3xl lg:text-4xl font-bold text-white leading-tight">Your Keys, Split Three Ways</h2>

                        <p class="text-slate-400 text-lg leading-relaxed">
                            No seed phrase to write down. No single point of failure. Your private key is split into three encrypted shards. Any two can recover your wallet.
                        </p>

                        <div class="space-y-4 pt-2">
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-transparent hover:border-white/5 hover:bg-white/[0.02] transition-all">
                                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold text-sm">Device Shard</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">On your phone, protected by biometrics.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-4 rounded-xl border border-transparent hover:border-white/5 hover:bg-white/[0.02] transition-all">
                                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold text-sm">Server Shard</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">In our distributed HSM infrastructure.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-4 rounded-xl border border-transparent hover:border-white/5 hover:bg-white/[0.02] transition-all">
                                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold text-sm">Recovery Shard</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Encrypted cloud backup on iCloud or Google Drive.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             THE FINAEGIS CORE (Architecture)
        ═══════════════════════════════════════════════════════════════ --}}
        <section class="py-20 lg:py-28 bg-[var(--bg-dark)] border-t border-white/5 relative grid-bg" id="platform">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl lg:text-4xl font-bold text-white tracking-tight">The FinAegis Core</h2>
                    <p class="text-slate-400 mt-3 max-w-lg mx-auto">
                        Built on an AI-native banking engine. Compliance in milliseconds. You stay private. The system stays compliant.
                    </p>
                </div>

                {{-- Architecture flow --}}
                <div class="flex flex-col items-center max-w-sm mx-auto">
                    {{-- You --}}
                    <div class="bg-[#0d1017] border border-blue-500/25 px-8 py-3.5 rounded-full shadow-[0_0_20px_rgba(59,130,246,0.12)] backdrop-blur-sm">
                        <span class="text-sm font-bold text-white">You</span>
                    </div>

                    {{-- Connector --}}
                    <div class="w-px h-12 bg-slate-800 relative data-stream overflow-hidden"></div>

                    {{-- Engine --}}
                    <div class="bg-[#0d1017]/90 border border-emerald-500/30 px-10 py-7 rounded-2xl shadow-[0_0_40px_rgba(16,185,129,0.08)] relative overflow-hidden backdrop-blur-sm">
                        <div class="absolute inset-0 bg-emerald-500/[0.03] animate-pulse"></div>
                        <div class="relative z-10 text-center">
                            <svg class="w-8 h-8 text-emerald-400 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
                            <div class="text-base font-bold text-white">AI-Banking Engine</div>
                            <div class="text-[10px] text-emerald-400/80 mt-1 font-mono tracking-wider uppercase">Status: Secure</div>
                        </div>
                    </div>

                    {{-- Connector --}}
                    <div class="w-px h-12 bg-slate-800 relative data-stream data-stream-delayed overflow-hidden"></div>

                    {{-- World --}}
                    <div class="bg-[#0d1017] border border-white/10 px-8 py-3.5 rounded-full backdrop-blur-sm">
                        <span class="text-sm font-bold text-slate-400">The World</span>
                    </div>
                </div>

                {{-- Capabilities grid --}}
                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-3xl mx-auto">
                    <div class="text-center p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <div class="text-2xl font-bold text-white">42</div>
                        <div class="text-[11px] text-slate-500 mt-1">Banking Domains</div>
                    </div>
                    <div class="text-center p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <div class="text-2xl font-bold text-white">1,200+</div>
                        <div class="text-[11px] text-slate-500 mt-1">API Endpoints</div>
                    </div>
                    <div class="text-center p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <div class="text-2xl font-bold text-white">ZK</div>
                        <div class="text-[11px] text-slate-500 mt-1">Privacy Proofs</div>
                    </div>
                    <div class="text-center p-4 rounded-xl bg-white/[0.02] border border-white/5">
                        <div class="text-2xl font-bold text-white">ERC-4337</div>
                        <div class="text-[11px] text-slate-500 mt-1">Gas Abstraction</div>
                    </div>
                </div>

                {{-- Link to platform --}}
                <div class="text-center mt-10">
                    <a href="{{ route('platform') }}" class="inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300 font-medium transition-colors group">
                        Explore the full FinAegis Core Banking Platform
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             FAQ
        ═══════════════════════════════════════════════════════════════ --}}
        <section class="py-20 lg:py-28 bg-[#080a0e] border-t border-white/5 relative" id="faq">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl lg:text-4xl font-bold text-white text-center mb-12 tracking-tight">Frequently Asked Questions</h2>

                <div class="space-y-0 border-t border-white/5">
                    <details class="group border-b border-white/5">
                        <summary class="flex justify-between items-center cursor-pointer py-5 pr-2 font-medium text-white select-none text-sm hover:text-blue-400 transition-colors">
                            <span>Is FinAegis a custodial wallet?</span>
                            <svg class="w-4 h-4 text-slate-500 group-open:text-blue-400 group-open:rotate-45 transition-all duration-200 flex-shrink-0 ml-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </summary>
                        <div class="pb-5 text-slate-400 text-sm leading-relaxed">
                            No. FinAegis is fully non-custodial. Your private key is split using Shamir's Secret Sharing, and no single party (including FinAegis) can access your funds alone. You hold the keys.
                        </div>
                    </details>

                    <details class="group border-b border-white/5">
                        <summary class="flex justify-between items-center cursor-pointer py-5 pr-2 font-medium text-white select-none text-sm hover:text-blue-400 transition-colors">
                            <span>How is transaction shielding different from a mixer?</span>
                            <svg class="w-4 h-4 text-slate-500 group-open:text-blue-400 group-open:rotate-45 transition-all duration-200 flex-shrink-0 ml-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </summary>
                        <div class="pb-5 text-slate-400 text-sm leading-relaxed">
                            Mixers pool funds to hide origins. We don't. FinAegis uses zero-knowledge proofs to encrypt your transaction data so third parties can't see it &mdash; but we maintain full audit logs for regulatory authorities. Privacy for you, compliance for regulators.
                        </div>
                    </details>

                    <details class="group border-b border-white/5">
                        <summary class="flex justify-between items-center cursor-pointer py-5 pr-2 font-medium text-white select-none text-sm hover:text-blue-400 transition-colors">
                            <span>Do I need ETH for gas fees?</span>
                            <svg class="w-4 h-4 text-slate-500 group-open:text-blue-400 group-open:rotate-45 transition-all duration-200 flex-shrink-0 ml-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </summary>
                        <div class="pb-5 text-slate-400 text-sm leading-relaxed">
                            No. Account Abstraction (ERC-4337) lets you pay fees in USDC. You never need to hold volatile crypto just to move stablecoins.
                        </div>
                    </details>

                    <details class="group border-b border-white/5">
                        <summary class="flex justify-between items-center cursor-pointer py-5 pr-2 font-medium text-white select-none text-sm hover:text-blue-400 transition-colors">
                            <span>What is a Super KYC certificate?</span>
                            <svg class="w-4 h-4 text-slate-500 group-open:text-blue-400 group-open:rotate-45 transition-all duration-200 flex-shrink-0 ml-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </summary>
                        <div class="pb-5 text-slate-400 text-sm leading-relaxed">
                            Standard KYC checks documents. Our Super KYC goes further: we verify the company is genuinely legitimate and not sanctioned through enhanced due diligence, open-source intelligence, and white-hat analysis. The resulting certificate is a verifiable credential that businesses can present to partners and regulators.
                        </div>
                    </details>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             FINAL CTA
        ═══════════════════════════════════════════════════════════════ --}}
        <section class="py-16 lg:py-20 bg-[var(--bg-dark)] border-t border-white/5 relative hero-glow grid-bg noise overflow-hidden">
            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <h2 class="text-2xl lg:text-3xl font-bold text-white tracking-tight mb-4">
                    Pay with stablecoins in shops.<br>Use your card.
                </h2>
                <p class="text-slate-400 mb-8">Be the first to know when we launch.</p>

                {{-- Duplicate signup (scrolled-to version) --}}
                <form id="early-access-form-bottom" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                    <input
                        type="email"
                        required
                        placeholder="Enter your email"
                        class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500/50 outline-none transition-all text-sm"
                    >
                    <button type="submit" class="glow-btn shimmer bg-[#111827] hover:bg-[#1a2332] text-white px-6 py-3.5 rounded-xl font-semibold transition-all whitespace-nowrap flex items-center justify-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        Get Early Access
                    </button>
                </form>
                <div id="signup-success-bottom" class="hidden mt-4">
                    <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-3">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span class="text-sm font-medium text-emerald-300">You're on the list.</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- ═══════════════════════════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════════════════════════ --}}
    <footer class="bg-[#040506] border-t border-white/5 pt-14 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8 mb-12">
                {{-- Brand --}}
                <div>
                    <a href="{{ route('home') }}" class="flex items-center gap-2 mb-5">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center shadow-lg shadow-blue-500/15 ring-1 ring-white/10">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        </div>
                        <span class="text-lg font-bold text-white tracking-tight">FinAegis</span>
                    </a>
                    <p class="text-xs text-slate-500 leading-relaxed mb-5">
                        Pay with stablecoins. Stay private. Prove compliance.
                    </p>
                    <div class="flex gap-3">
                        <a href="#" class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center text-slate-500 hover:text-white hover:bg-white/10 transition-colors" aria-label="Twitter">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="w-9 h-9 rounded-full bg-white/5 flex items-center justify-center text-slate-500 hover:text-white hover:bg-white/10 transition-colors" aria-label="GitHub">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Product --}}
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-5">Product</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('platform') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Platform</a></li>
                        <li><a href="{{ route('features') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Features</a></li>
                        <li><a href="{{ route('pricing') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Pricing</a></li>
                        <li><a href="{{ route('security') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Security</a></li>
                    </ul>
                </div>

                {{-- Developers --}}
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-5">Developers</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('developers') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Developer Hub</a></li>
                        <li><a href="/api/documentation" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">API Reference</a></li>
                        <li><a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">GitHub</a></li>
                    </ul>
                </div>

                {{-- Support --}}
                <div>
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider mb-5">Support</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('support.contact') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Contact</a></li>
                        <li><a href="{{ route('support.faq') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">FAQ</a></li>
                        <li><a href="{{ route('about') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">About</a></li>
                        <li><a href="{{ route('blog') }}" class="text-sm text-slate-500 hover:text-blue-400 transition-colors">Blog</a></li>
                    </ul>
                </div>
            </div>

            {{-- Bottom --}}
            <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <p class="text-xs text-slate-600">&copy; {{ date('Y') }} FinAegis. All rights reserved.</p>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-[10px] text-slate-500">All Systems Operational</span>
                    </div>
                </div>
                <div class="flex gap-5">
                    <a href="{{ route('legal.terms') }}" class="text-xs text-slate-500 hover:text-white transition-colors">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="text-xs text-slate-500 hover:text-white transition-colors">Privacy</a>
                    <a href="{{ route('legal.cookies') }}" class="text-xs text-slate-500 hover:text-white transition-colors">Cookies</a>
                </div>
            </div>

            {{-- Disclaimer --}}
            <div class="mt-6">
                <p class="text-[10px] text-slate-700 leading-relaxed text-center max-w-2xl mx-auto">
                    FinAegis does not provide financial advice. Cryptocurrency values are volatile. Self-custody means you are responsible for the security of your wallet and private key shards. Ensure you maintain access to your recovery methods at all times.
                </p>
            </div>
        </div>
    </footer>

    {{-- ═══════════════════════════════════════════════════════════════
         JAVASCRIPT
    ═══════════════════════════════════════════════════════════════ --}}
    <script>
        // Mobile nav toggle
        document.getElementById('mobile-nav-toggle').addEventListener('click', function() {
            document.getElementById('mobile-nav').classList.toggle('hidden');
        });

        // Close mobile nav on link click
        document.querySelectorAll('#mobile-nav a[href^="#"]').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('mobile-nav').classList.add('hidden');
            });
        });

        // Early access form handler
        function handleSignup(form, successEl) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const emailInput = form.querySelector('input[type="email"]');
                const email = emailInput.value.trim();
                const btn = form.querySelector('button[type="submit"]');
                const errorEl = document.getElementById('signup-error');

                if (!email) return;

                // Loading state
                btn.disabled = true;
                btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg> Submitting...';

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    const response = await fetch('/subscriber/mobile-app', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            email: email,
                            tags: ['mobile-app', 'early-access']
                        })
                    });

                    const data = await response.json();

                    if (data.success || response.ok) {
                        // Success
                        form.classList.add('hidden');
                        successEl.classList.remove('hidden');
                        if (errorEl) errorEl.classList.add('hidden');

                        // Also update bottom form if top was used
                        document.querySelectorAll('[id^="early-access-form"]').forEach(f => f.classList.add('hidden'));
                        document.querySelectorAll('[id^="signup-success"]').forEach(s => s.classList.remove('hidden'));
                    } else {
                        throw new Error(data.message || 'Signup failed');
                    }
                } catch (error) {
                    // Fallback: mailto
                    window.location.href = 'mailto:info@finaegis.org?subject=' +
                        encodeURIComponent('FinAegis Early Access Request') +
                        '&body=' + encodeURIComponent('Please add me to the early access list.\n\nEmail: ' + email);

                    // Show as success after mailto opens
                    setTimeout(function() {
                        form.classList.add('hidden');
                        successEl.classList.remove('hidden');
                    }, 500);
                }
            });
        }

        // Attach to both forms
        const topForm = document.getElementById('early-access-form');
        const bottomForm = document.getElementById('early-access-form-bottom');
        const topSuccess = document.getElementById('signup-success');
        const bottomSuccess = document.getElementById('signup-success-bottom');

        if (topForm) handleSignup(topForm, topSuccess);
        if (bottomForm) handleSignup(bottomForm, bottomSuccess);
    </script>
</body>
</html>
