@extends('layouts.public')

@section('title', config('brand.name', 'Zelta') . ' - Open Source Core Banking Infrastructure')

@section('seo')
    @include('partials.seo', [
        'title' => config('brand.name', 'Zelta') . ' - Open Source Core Banking Infrastructure',
        'description' => config('brand.name', 'Zelta') . ' is an open-source reference platform for core banking, built with Laravel. Its 61 modules cover payments, lending, compliance tooling, digital assets and AI agents.',
        'keywords' => config('brand.name', 'Zelta') . ', open source banking, core banking reference platform, ISO 20022, open banking, SEPA, Interledger, microfinance, event sourcing, decentralised finance, regulatory reporting, banking API, Laravel fintech, Model Context Protocol',
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
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/[0.04] border border-white/[0.08] text-sm text-slate-400 mb-4 animate-fade-in-down">
                    <svg class="w-4 h-4 text-fa-teal" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Open Source &middot; Apache-2.0 Licensed &middot; 61 Domains
                </div>

                <!-- MCP-compatible badge -->
                <a href="{{ url('/features/mcp') }}" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-400/30 text-xs font-semibold mb-8 animate-fade-in-down hover:bg-emerald-500/15 transition-colors" style="animation-delay: 0.05s">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Connect Claude Desktop, Cursor or another AI agent through the Model Context Protocol
                </a>

                <!-- Heading -->
                <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white leading-[1.08] mb-6 animate-fade-in-up">
                    Open-Source Core Banking
                    <span class="text-gradient block sm:inline"> for Developers</span>
                </h1>

                <!-- Subheading -->
                <p class="text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed animate-fade-in-up" style="animation-delay: 0.15s">
                    {{ config('brand.name', 'Zelta') }} is an open-source reference platform for core banking. Its 61 modules cover accounts, payments, lending, compliance tooling, digital assets and AI agents. You can study the code, try the demo, or reuse the modules in your own product.
                </p>

                <!-- Disclaimer (F1) -->
                <p class="text-xs text-slate-500 max-w-2xl mx-auto -mt-6 mb-10 leading-relaxed animate-fade-in-up" style="animation-delay: 0.2s">
                    FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center animate-fade-in-up" style="animation-delay: 0.3s">
                    <a href="{{ route('register') }}" class="btn-primary !py-3.5 !px-8 !text-base !rounded-lg group">
                        Try the Sandbox
                        <svg class="w-4 h-4 ml-2 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </a>
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-outline !py-3.5 !px-8 !text-base !rounded-lg group">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/></svg>
                        View on GitHub
                    </a>
                </div>

                <!-- Tech stack pills -->
                <div class="flex flex-wrap justify-center gap-3 mt-12 animate-fade-in" style="animation-delay: 0.5s">
                    @foreach(['Laravel', 'Event sourcing', 'Domain-driven design', 'GraphQL', 'ERC-4337', 'ISO 20022', 'Open banking', 'Solana', 'Interledger', 'Model Context Protocol'] as $tech)
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
                        Apache-2.0
                    </div>
                    <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-6">
                        What Is {{ config('brand.name', 'Zelta') }}?
                    </h2>
                    <p class="text-lg text-slate-600 leading-relaxed mb-6">
                        {{ config('brand.name', 'Zelta') }} is an open-source reference platform for core banking, built with Laravel. The code is split into 61 domain modules. Every money movement is stored as an event, so the full history can be replayed and audited. It is a platform to learn from and build on, not a bank.
                    </p>
                    <div class="space-y-3 mb-8">
                        @foreach([
                            'Adapters connect to cross-chain bridges and decentralised finance protocols.',
                            'Zero-knowledge proofs and verifiable credentials protect personal data.',
                            'Compliance tooling, a mobile wallet backend and banking-as-a-service APIs are included.',
                            'A provider-neutral card layer supports virtual cards, built for a closed pilot.',
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
                        <h3 class="font-display text-2xl font-bold text-slate-900 mb-8">Who It Is For</h3>
                        <div class="space-y-8">
                            @foreach([
                                ['title' => 'Developers and Architects', 'desc' => 'See how event sourcing and domain-driven design work in a real financial system. Every pattern described here is running code that you can read and test.', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'blue'],
                                ['title' => 'Fintech Founders', 'desc' => 'Fork the code and use the modules you need in your own product. Licences and compliance in your market stay your responsibility.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'amber'],
                                ['title' => 'AI Engineers', 'desc' => 'Connect AI agents to financial workflows. The platform has a Model Context Protocol server, support for Google\'s Agent2Agent protocol and transaction queries in plain language.', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'purple'],
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
                                This site is a sandbox, and every transaction uses test data.
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
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-4">61 Open-Source Modules</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                    One codebase holds modules for payments, lending, compliance tooling, digital assets, privacy, mobile wallets and AI agents.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @php
                    $features = [
                        ['route' => 'gcu', 'slug' => null, 'title' => 'Global Currency Unit Demo', 'desc' => 'The Global Currency Unit demo shows a unit valued against a basket of currencies and gold. It is a software demonstration, and its basket votes are simulated.', 'icon' => null, 'symbol' => "\u{01A4}", 'color' => 'blue'],
                        ['route' => 'features.show', 'slug' => 'multi-asset', 'title' => 'Multi-Asset Accounts', 'desc' => 'One account can hold balances in several currencies, crypto assets and commodities. The module converts between them and tracks the portfolio.', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'purple'],
                        ['route' => 'features.show', 'slug' => 'settlements', 'title' => 'Event-Sourced Ledger', 'desc' => 'Every transaction is stored as an event that cannot change. You can rebuild an account as it was at any point in time and replay its history.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'emerald'],
                        ['route' => 'features.show', 'slug' => 'governance', 'title' => 'Governance Module', 'desc' => 'The governance module runs proposals and weighted votes. The Global Currency Unit demo uses it with simulated basket votes.', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'amber'],
                        ['route' => 'features.show', 'slug' => 'bank-integration', 'title' => 'Banking API Patterns', 'desc' => 'The banking modules follow open banking API patterns. They include adapters for third-party services such as Ondato identity checks, Chainalysis sanctions screening and card issuers. These are integration adapters, with no partnership or endorsement implied.', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'red'],
                        ['route' => 'features.show', 'slug' => 'api', 'title' => 'REST, GraphQL and OpenAPI', 'desc' => 'The REST API is described in OpenAPI. GraphQL covers 43 domains and supports live subscriptions. Webhooks report events to your own systems.', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'color' => 'sky'],
                        ['route' => 'ai-framework', 'slug' => null, 'title' => 'AI Agent Protocol', 'desc' => 'AI agents can find each other and trade through Google\'s Agent2Agent protocol. Escrow and spending limits keep each agent within its budget.', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'violet'],
                        ['route' => 'features.show', 'slug' => 'mcp', 'title' => 'Model Context Protocol Server', 'desc' => 'Claude Desktop, Cursor and other Model Context Protocol clients can connect to the server. It offers ' . count((array) config('mcp.tools')) . ' banking tools behind an OAuth sign-in. Each token has a spending limit, and a repeated write never runs twice.', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'emerald'],
                        ['route' => 'features.show', 'slug' => 'crosschain-defi', 'title' => 'Cross-Chain and Decentralised Finance', 'desc' => 'Bridge adapters connect to Wormhole, LayerZero and Axelar. The module works with Ethereum-compatible networks and Solana, and it can route swaps across decentralised exchanges. These are integration adapters, with no partnership or endorsement implied.', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'color' => 'orange'],
                        ['route' => 'features.show', 'slug' => 'privacy-identity', 'title' => 'Privacy and Identity', 'desc' => 'Zero-knowledge proofs let a person prove an identity fact without showing documents. The module also issues verifiable credentials and splits keys with Shamir secret sharing. Private RAILGUN transfers are moving onto the device and are not live yet.', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'color' => 'teal'],
                        ['route' => 'features.show', 'slug' => 'mobile-payments', 'title' => 'Mobile Payments', 'desc' => 'The mobile backend signs people in with passkeys and sends push notifications. The wallet keeps keys on the device and sends stablecoins on Polygon, Base, Arbitrum and Solana. Network fees are sponsored, so people do not need to hold gas tokens.', 'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z', 'color' => 'pink'],
                        ['route' => 'features.show', 'slug' => 'x402-protocol', 'title' => 'x402 Micropayments', 'desc' => 'An API can ask for a small USDC payment before it answers a request. Payments run on Base, Ethereum, Avalanche and Solana. AI agents pay per call within their own spending limits.', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'emerald'],
                        ['route' => 'features.show', 'slug' => 'regtech-compliance', 'title' => 'Regulatory Reporting Tooling', 'desc' => 'The reporting modules build reports in the formats that European Union and international rules ask for, such as the Markets in Crypto-Assets Regulation and the Travel Rule. They check formats and do not connect to any regulator.', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'yellow'],
                        ['route' => 'features.show', 'slug' => 'baas-platform', 'title' => 'Banking-as-a-Service', 'desc' => 'Partners can run the platform under their own brand. The module generates client libraries, embeddable widgets and usage-based bills for each partner.', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'color' => 'indigo'],
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
                    See All 61 Domains
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
                    The platform has 61 domain modules. Each module keeps its own data and events, and you can switch it on or off on its own.
                </p>
            </div>

            <!-- GCU demo -->
            <div class="card-dark p-8 lg:p-10 mb-8 animate-on-scroll stagger-1">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500/20 to-teal-500/20 border border-white/[0.08] flex items-center justify-center">
                            <span class="text-3xl font-bold text-gradient">&#x01A4;</span>
                        </div>
                        <div>
                            <h3 class="font-display text-2xl font-bold text-white">Global Currency Unit</h3>
                            <p class="text-sm text-slate-500">Software demo</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">Demo</span>
                </div>
                <p class="text-slate-400 mb-8 max-w-3xl">
                    The Global Currency Unit demo is a reference implementation of a unit valued against a basket of currencies, built with FinAegis. It is not issued, offered or sold to anyone, and it has no monetary value. Balances, conversions and basket votes in the demo are simulated.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="text-center p-4 rounded-lg bg-white/[0.02] border border-white/[0.04]">
                        <div class="text-4xl font-display font-bold text-gradient mb-1">&#x01A4;</div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Demo Unit Symbol</p>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-white/[0.02] border border-white/[0.04]">
                        <div class="text-2xl font-display font-bold text-white mb-1">6 Assets</div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">USD, EUR, GBP, CHF, JPY and gold (simulated basket)</p>
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
                    ['title' => 'Exchange', 'desc' => 'The exchange module matches limit and market orders.', 'icon' => 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z', 'color' => 'blue'],
                    ['title' => 'Lending', 'desc' => 'The lending module runs peer-to-peer loans with credit scoring.', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'color' => 'emerald'],
                    ['title' => 'Stablecoin', 'desc' => 'The stablecoin module models minting, burning and the peg.', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'violet'],
                    ['title' => 'Treasury', 'desc' => 'The treasury module models portfolios and cash positions.', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'amber'],
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
                You can use each module on its own. Read the code to learn the patterns, or fork it and build on them.
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
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-4">How the Global Currency Unit Demo Works</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                    The Global Currency Unit demo shows how the basket, governance and event modules work together.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                @foreach([
                    ['title' => 'Governance Model', 'desc' => 'Basket votes in the demo are simulated and have no monetary effect. Voting weight exists only in the software.', 'items' => ['Monthly voting cycles', 'Transparent tallying', 'Event-sourced audit trail'], 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'blue'],
                    ['title' => 'Basket Model', 'desc' => 'The demo unit is valued against a weighted basket of six reference assets. No reserves are held.', 'items' => ['6 reference assets (demo)', 'Simulated rebalancing'], 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => 'emerald'],
                    ['title' => 'Audit Trail', 'desc' => 'Every governance decision is stored as an event that cannot change. The audit trail runs from the proposal to its result.', 'items' => ['Event sourcing', 'Full history of each decision'], 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z', 'color' => 'amber'],
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

            <!-- GCU disclaimer (F4) -->
            <p class="text-xs text-slate-500 max-w-3xl mx-auto mt-10 text-center leading-relaxed">
                The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
            </p>
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
                    ['value' => '61', 'label' => 'Domain Modules'],
                    ['value' => '45', 'label' => 'GraphQL Schemas'],
                    ['value' => '1,000+', 'label' => 'API Routes'],
                    ['value' => 'Apache-2.0', 'label' => 'Licensed'],
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
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-6">Try the Platform</h2>
            <p class="text-lg text-slate-500 mb-10 max-w-xl mx-auto">
                Create a free sandbox account to try the platform with test data. You can also read the API documentation or the source code on GitHub.
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
