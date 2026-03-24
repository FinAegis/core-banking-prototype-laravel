@extends('layouts.public')

@section('title', config('brand.name', 'Zelta') . ' - Open Source Core Banking Infrastructure')

@section('seo')
    @include('partials.seo', [
        'title' => config('brand.name', 'Zelta') . ' - Open Source Core Banking Infrastructure',
        'description' => 'Open-source core banking infrastructure with 47 DDD domains, event sourcing, cross-chain DeFi, privacy-preserving identity, RegTech compliance, AI analytics, and HTTP-native micropayments. Built with Laravel.',
        'keywords' => config('brand.name', 'Zelta') . ', open source banking, core banking infrastructure, GCU, event sourcing, DeFi, cross-chain, RegTech, banking API, Laravel fintech',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="organization" />
    <x-schema type="website" />
@endsection

@section('content')

    <!-- ═══════════════════════════════════════
         HERO
         ═══════════════════════════════════════ -->
    <section class="relative bg-fa-navy overflow-hidden">
        <!-- Background effects -->
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/4 -left-32 w-96 h-96 bg-blue-500/10 rounded-full blur-[120px] animate-glow-pulse"></div>
        <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-teal-500/8 rounded-full blur-[120px] animate-glow-pulse" style="animation-delay: 1.5s"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-28 lg:pt-28 lg:pb-36">
            <div class="text-center max-w-4xl mx-auto">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/[0.04] border border-white/[0.08] text-sm text-slate-400 mb-8 animate-fade-in-down">
                    <svg class="w-4 h-4 text-fa-teal" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Open Source &middot; MIT Licensed &middot; 49 Domains
                </div>

                <!-- Heading -->
                <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white leading-[1.08] mb-6 animate-fade-in-up">
                    The Banking Infrastructure
                    <span class="text-gradient block sm:inline"> Developers Deserve</span>
                </h1>

                <!-- Subheading -->
                <p class="text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed animate-fade-in-up" style="animation-delay: 0.15s">
                    Production-grade core banking built with Laravel. Event sourcing, CQRS, cross-chain DeFi, privacy-preserving identity, AI agent commerce, and HTTP-native micropayments.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-up" style="animation-delay: 0.3s">
                    <a href="{{ route('register') }}" class="btn-primary !py-3.5 !px-8 !text-base !rounded-lg group">
                        Explore the Platform
                        <svg class="w-4 h-4 ml-2 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </a>
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-outline !py-3.5 !px-8 !text-base !rounded-lg group">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>
                        View on GitHub
                    </a>
                </div>

                <!-- Tech stack pills -->
                <div class="flex flex-wrap justify-center gap-3 mt-12 animate-fade-in" style="animation-delay: 0.5s">
                    @foreach(['Laravel', 'Event Sourcing', 'CQRS', 'GraphQL', 'DDD', 'ERC-4337'] as $tech)
                        <span class="px-3 py-1 text-xs font-medium text-slate-500 bg-white/[0.03] border border-white/[0.06] rounded-full">{{ $tech }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Section transition -->
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- ═══════════════════════════════════════
         WHAT IS THIS
         ═══════════════════════════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Left: Info -->
                <div class="animate-on-scroll">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 text-blue-600 text-xs font-semibold mb-6">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        MIT Licensed
                    </div>
                    <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-6">
                        What Is {{ config('brand.name', 'Zelta') }}?
                    </h2>
                    <p class="text-lg text-slate-600 leading-relaxed mb-6">
                        A production-grade core banking platform built with Laravel and domain-driven design. 47 bounded contexts, event sourcing, CQRS, and every integration pattern a modern fintech needs.
                    </p>
                    <div class="space-y-3 mb-8">
                        @foreach([
                            'Cross-chain bridges, DeFi protocols & multi-chain portfolio',
                            'Privacy-preserving identity with ZK-KYC & verifiable credentials',
                            'RegTech compliance, mobile payments & Banking-as-a-Service',
                        ] as $item)
                            <div class="flex items-start gap-3">
                                <div class="mt-1 w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-slate-700">{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('developers') }}" class="btn-primary !bg-slate-900 hover:!bg-slate-800 !rounded-lg">Developer Docs</a>
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 border border-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition">Explore Demo</a>
                    </div>
                </div>

                <!-- Right: Who it's for -->
                <div class="animate-on-scroll stagger-2">
                    <div class="bg-slate-50 rounded-2xl p-8 lg:p-10 border border-slate-100">
                        <h3 class="font-display text-2xl font-bold text-slate-900 mb-8">Who Is This For?</h3>
                        <div class="space-y-8">
                            @foreach([
                                ['title' => 'Developers & Architects', 'desc' => 'See how event sourcing, CQRS, and DDD work in a real financial system — not just theory, but running code.', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'blue'],
                                ['title' => 'Fintech Founders', 'desc' => 'Fork the codebase and build your product on top of battle-tested banking infrastructure.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'amber'],
                                ['title' => 'AI/ML Engineers', 'desc' => 'Integrate AI agents into financial workflows with MCP tools, A2A protocol, and NL transaction queries.', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'purple'],
                            ] as $persona)
                                <div class="flex gap-4">
                                    <div class="w-10 h-10 rounded-lg bg-{{ $persona['color'] }}-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-{{ $persona['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $persona['icon'] }}"></path></svg>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-slate-900 mb-1">{{ $persona['title'] }}</h4>
                                        <p class="text-sm text-slate-500 leading-relaxed">{{ $persona['desc'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-8 pt-6 border-t border-slate-200">
                            <p class="text-xs text-slate-400 text-center">
                                Sandbox environment — all transactions use test data.
                                <a href="{{ config('brand.github_url') }}" class="text-blue-500 hover:underline">Contribute on GitHub</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         FEATURES GRID
         ═══════════════════════════════════════ -->
    <section id="features" class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 animate-on-scroll">
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-4">Built-In Capabilities</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                    49 domain modules spanning payments, lending, compliance, DeFi, privacy, mobile wallets, AI analytics, and more.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @php
                    $features = [
                        ['route' => 'features.show', 'slug' => 'gcu', 'title' => 'Global Currency Unit', 'desc' => 'A multi-currency basket with democratic governance. Users vote on composition across USD, EUR, GBP, CHF, JPY, and gold.', 'icon' => null, 'symbol' => "\u{01A4}", 'color' => 'blue'],
                        ['route' => 'features.show', 'slug' => 'multi-asset', 'title' => 'Multi-Asset Accounts', 'desc' => 'Hold fiat, crypto, and commodities in a single account with real-time conversion and portfolio tracking.', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'purple'],
                        ['route' => 'features.show', 'slug' => 'settlements', 'title' => 'Event-Sourced Ledger', 'desc' => 'Every transaction is an immutable event. Full audit trails, point-in-time reconstruction, and replay capability.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'emerald'],
                        ['route' => 'features.show', 'slug' => 'governance', 'title' => 'Democratic Governance', 'desc' => 'Stake-weighted voting on monetary policy. Users shape their currency through on-chain governance proposals.', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'amber'],
                        ['route' => 'features.show', 'slug' => 'bank-integration', 'title' => 'Banking API Patterns', 'desc' => 'Open Banking-compliant API patterns with Ondato KYC, Chainalysis sanctions, and Marqeta card issuing.', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'red'],
                        ['route' => 'features.show', 'slug' => 'api', 'title' => 'REST, GraphQL & OpenAPI', 'desc' => 'Full REST coverage with OpenAPI specs, GraphQL across 39 domains, real-time subscriptions, and webhooks.', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'sky'],
                        ['route' => 'ai-framework', 'slug' => null, 'title' => 'AI Agent Protocol', 'desc' => 'Google A2A protocol for autonomous agent commerce. MCP tools, spending limits, and transaction analytics.', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'violet'],
                        ['route' => 'features.show', 'slug' => 'crosschain-defi', 'title' => 'Cross-Chain & DeFi', 'desc' => 'Bridge across Wormhole, LayerZero, Axelar. Aggregate DEX liquidity, optimize yield, manage multi-chain portfolios.', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'color' => 'orange'],
                        ['route' => 'features.show', 'slug' => 'privacy-identity', 'title' => 'Privacy & Identity', 'desc' => 'ZK-KYC proofs, RAILGUN shielded transfers, W3C verifiable credentials, soulbound tokens, Shamir key mgmt.', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'color' => 'teal'],
                        ['route' => 'features.show', 'slug' => 'mobile-payments', 'title' => 'Mobile Payments', 'desc' => 'Passkey authentication, payment intents, P2P transfers, push notifications, and ERC-4337 account abstraction.', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z', 'color' => 'pink'],
                        ['route' => 'features.show', 'slug' => 'x402-protocol', 'title' => 'x402 Micropayments', 'desc' => 'HTTP-native payments with USDC on Base. AI agents pay for APIs on demand with per-agent spending limits.', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'emerald'],
                        ['route' => 'features.show', 'slug' => 'regtech-compliance', 'title' => 'RegTech Compliance', 'desc' => 'MiFID II, MiCA, and Travel Rule adapters with jurisdiction-aware routing and automated reporting.', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'yellow'],
                        ['route' => 'features.show', 'slug' => 'baas-platform', 'title' => 'Banking-as-a-Service', 'desc' => 'White-label your banking stack. Partner APIs, auto-generated SDKs, embeddable widgets, and usage billing.', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'color' => 'indigo'],
                    ];
                @endphp

                @foreach($features as $i => $feature)
                    <a href="{{ $feature['slug'] ? route($feature['route'], $feature['slug']) : route($feature['route']) }}"
                       class="card-elevated p-6 block group animate-on-scroll stagger-{{ ($i % 6) + 1 }}">
                        <div class="w-10 h-10 rounded-lg bg-{{ $feature['color'] }}-50 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                            @if(isset($feature['symbol']))
                                <span class="text-lg font-bold text-{{ $feature['color'] }}-600">{{ $feature['symbol'] }}</span>
                            @else
                                <svg class="w-5 h-5 text-{{ $feature['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feature['icon'] }}"></path></svg>
                            @endif
                        </div>
                        <h3 class="font-display text-base font-semibold text-slate-900 mb-2">{{ $feature['title'] }}</h3>
                        <p class="text-sm text-slate-500 leading-relaxed mb-3">{{ $feature['desc'] }}</p>
                        <span class="text-sm font-medium text-{{ $feature['color'] }}-600 group-hover:text-{{ $feature['color'] }}-700 inline-flex items-center gap-1">
                            Learn more
                            <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="text-center mt-12 animate-on-scroll">
                <a href="{{ route('features') }}" class="btn-primary !bg-slate-900 hover:!bg-slate-800 !rounded-lg group">
                    See All 49 Domains
                    <svg class="w-4 h-4 ml-2 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         PLATFORM ARCHITECTURE
         ═══════════════════════════════════════ -->
    <section class="py-24 bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 animate-on-scroll">
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-white tracking-tight mb-4">Platform Architecture</h2>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    47 bounded contexts built with DDD, event sourcing, and CQRS. Each module implements specific financial system patterns you can use independently.
                </p>
            </div>

            <!-- GCU Flagship -->
            <div class="card-dark p-8 lg:p-10 mb-8 animate-on-scroll stagger-1">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500/20 to-teal-500/20 border border-white/[0.08] flex items-center justify-center">
                            <span class="text-3xl font-bold text-gradient">&#x01A4;</span>
                        </div>
                        <div>
                            <h3 class="font-display text-2xl font-bold text-white">Global Currency Unit (GCU)</h3>
                            <p class="text-sm text-slate-500">Flagship Product</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">Flagship</span>
                </div>
                <p class="text-slate-400 mb-8 max-w-3xl">
                    A democratically governed basket currency where users vote on composition. The system automatically rebalances across six reserve assets based on community governance.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="text-center p-4 rounded-lg bg-white/[0.02] border border-white/[0.04]">
                        <div class="text-4xl font-display font-bold text-gradient mb-1">&#x01A4;</div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Currency Symbol</p>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-white/[0.02] border border-white/[0.04]">
                        <div class="text-2xl font-display font-bold text-white mb-1">6 Assets</div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">USD, EUR, GBP, CHF, JPY, XAU</p>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-white/[0.02] border border-white/[0.04]">
                        <div class="text-2xl font-display font-bold text-white mb-1">Event-Sourced</div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Full audit trail</p>
                    </div>
                </div>
            </div>

            <!-- Core Modules Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 animate-on-scroll stagger-3">
                @foreach([
                    ['title' => 'Exchange', 'desc' => 'Order matching engine with limit & market orders', 'icon' => 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z', 'color' => 'blue'],
                    ['title' => 'Lending', 'desc' => 'P2P lending with risk assessment', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'emerald'],
                    ['title' => 'Stablecoin', 'desc' => 'Minting, burning, and peg management', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'violet'],
                    ['title' => 'Treasury', 'desc' => 'Portfolio management and yield optimization', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'amber'],
                ] as $module)
                    <div class="card-dark p-5 group">
                        <div class="w-9 h-9 rounded-md bg-{{ $module['color'] }}-500/10 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-4.5 h-4.5 text-{{ $module['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $module['icon'] }}"></path></svg>
                        </div>
                        <h4 class="font-semibold text-white text-sm mb-1">{{ $module['title'] }}</h4>
                        <p class="text-xs text-slate-500">{{ $module['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="text-center text-sm text-slate-500 mt-8 animate-on-scroll stagger-4">
                Each module is independently usable. Explore the code to understand the patterns, or fork and build on them.
            </p>
        </div>

        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- ═══════════════════════════════════════
         GCU CONCEPT
         ═══════════════════════════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 animate-on-scroll">
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-4">The GCU Concept</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                    What if users could vote on their currency's composition? The GCU is a working implementation of democratic monetary policy.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                @foreach([
                    ['title' => 'Democratic', 'desc' => 'Users vote on currency basket composition. Holdings determine voting weight in this governance model.', 'items' => ['Monthly voting cycles', 'Transparent tallying', 'Event-sourced audit trail'], 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'blue'],
                    ['title' => 'Multi-Asset Backed', 'desc' => 'Basket currency backed by six reserve assets with automatic rebalancing based on governance outcomes.', 'items' => ['6 reserve assets', 'Automatic rebalancing', 'Stability mechanisms'], 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'emerald'],
                    ['title' => 'Transparent', 'desc' => 'Every governance decision is recorded as an immutable event. Full audit trail from proposal to execution.', 'items' => ['Event sourcing', 'Public audit trail', 'On-chain governance'], 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'color' => 'amber'],
                ] as $i => $concept)
                    <div class="card-elevated p-8 text-center animate-on-scroll stagger-{{ $i + 1 }}">
                        <div class="w-16 h-16 rounded-2xl bg-{{ $concept['color'] }}-50 flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-{{ $concept['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $concept['icon'] }}"></path></svg>
                        </div>
                        <h3 class="font-display text-xl font-bold text-slate-900 mb-3">{{ $concept['title'] }}</h3>
                        <p class="text-sm text-slate-500 mb-5">{{ $concept['desc'] }}</p>
                        <ul class="text-left space-y-2">
                            @foreach($concept['items'] as $item)
                                <li class="flex items-center gap-2 text-sm text-slate-600">
                                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════
         STATS BAR
         ═══════════════════════════════════════ -->
    <section class="py-16 bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern opacity-50"></div>
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 animate-on-scroll">
                @foreach([
                    ['value' => '43', 'label' => 'Domain Modules'],
                    ['value' => '35', 'label' => 'GraphQL Schemas'],
                    ['value' => '143+', 'label' => 'API Endpoints'],
                    ['value' => 'MIT', 'label' => 'Licensed'],
                ] as $stat)
                    <div class="text-center">
                        <div class="font-display text-4xl lg:text-5xl font-extrabold text-gradient mb-2">{{ $stat['value'] }}</div>
                        <div class="text-xs text-slate-500 uppercase tracking-wider font-medium">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- ═══════════════════════════════════════
         CTA
         ═══════════════════════════════════════ -->
    <section class="py-24 bg-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 animate-on-scroll">
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-6">Ready to explore?</h2>
            <p class="text-lg text-slate-500 mb-10 max-w-xl mx-auto">
                See these features in action. Try the demo or explore the source code on GitHub.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary btn-lg group">
                    Explore the Platform
                    <svg class="w-4 h-4 ml-2 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </a>
                <a href="{{ config('brand.github_url') }}" target="_blank" class="inline-flex items-center justify-center px-10 py-4 border border-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-50 transition text-base group">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>
                    View Source
                </a>
            </div>
        </div>
    </section>

@endsection
