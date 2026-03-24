@extends('layouts.public')

@section('title', 'About ' . config('brand.name', 'Zelta') . ' - Open Source Core Banking')

@section('seo')
    @include('partials.seo', [
        'title' => 'About ' . config('brand.name', 'Zelta') . ' - Open Source Core Banking Infrastructure',
        'description' => config('brand.name', 'Zelta') . ' is an open-source core banking platform with 47 DDD domains, event sourcing, CQRS, and the Global Currency Unit. Built with Laravel for fintech developers.',
        'keywords' => config('brand.name', 'Zelta') . ' about, open source banking, core banking platform, GCU, event sourcing, CQRS, Laravel banking, DDD, fintech infrastructure',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="organization" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'About', 'url' => url('/about')]
    ]" />
@endsection

@section('content')
    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute bottom-1/4 -left-20 w-72 h-72 bg-blue-500/8 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/[0.04] border border-white/[0.08] text-sm text-slate-400 mb-8">
                    <svg class="w-4 h-4 text-fa-teal" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Open Source Project
                </div>
                @include('partials.breadcrumb', ['items' => [['name' => 'About', 'url' => url('/about')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">About <span class="text-gradient">{{ config('brand.name', 'Zelta') }}</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Open-source core banking infrastructure built with Laravel — 49 domain modules covering everything from democratic currency governance to AI agent commerce.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- What It Is -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="animate-on-scroll">
                    <h2 class="font-display text-3xl lg:text-4xl font-bold text-slate-900 mb-6">What Is {{ config('brand.name', 'Zelta') }}?</h2>
                    <p class="text-lg text-slate-600 mb-4 leading-relaxed">
                        {{ config('brand.name', 'Zelta') }} is a core banking platform built with Laravel, implementing event sourcing, CQRS, domain-driven design, and AI agent integration across 47 bounded contexts.
                    </p>
                    <p class="text-lg text-slate-600 mb-6 leading-relaxed">
                        At its heart is the <strong>Global Currency Unit (GCU)</strong>&mdash;a democratically governed basket currency where users vote on composition from six global reserve assets.
                    </p>
                    <div class="card-stat border-l-4 !border-l-blue-500 bg-blue-50/50">
                        <p class="text-sm text-slate-700">
                            <strong>Sandbox environment:</strong> All transactions use test data. Explore every feature freely.
                        </p>
                    </div>
                    <div class="mt-8">
                        <a href="{{ route('features') }}" class="inline-flex items-center text-blue-600 font-semibold hover:text-blue-700 transition text-sm">
                            Explore the features
                            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Architecture Illustration -->
                <div class="animate-on-scroll stagger-2">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-8 relative overflow-hidden">
                        <div class="absolute inset-0 bg-grid-light"></div>
                        <svg class="w-full h-72 relative" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="200" cy="150" r="70" fill="#f1f5f9" stroke="#3b82f6" stroke-width="1.5"/>
                            <path d="M170 120C170 120 180 110 190 110C200 110 210 115 215 120C220 125 225 130 225 140C225 150 220 155 210 160C200 165 190 160 185 155C180 150 175 140 175 130C175 125 170 120 170 120Z" fill="#3b82f6" opacity="0.1"/>
                            <path d="M155 140C155 140 160 135 165 135C170 135 175 140 175 145C175 150 170 155 165 155C160 155 155 150 155 145V140Z" fill="#3b82f6" opacity="0.1"/>
                            <path d="M215 170C215 170 220 165 225 165C230 165 235 170 235 175C235 180 230 185 225 185C220 185 215 180 215 175V170Z" fill="#3b82f6" opacity="0.1"/>
                            <circle cx="200" cy="150" r="4" fill="#3b82f6"/>
                            <circle cx="160" cy="130" r="3" fill="#3b82f6"/>
                            <circle cx="240" cy="130" r="3" fill="#3b82f6"/>
                            <circle cx="160" cy="170" r="3" fill="#3b82f6"/>
                            <circle cx="240" cy="170" r="3" fill="#3b82f6"/>
                            <circle cx="200" cy="110" r="3" fill="#3b82f6"/>
                            <circle cx="200" cy="190" r="3" fill="#3b82f6"/>
                            <line x1="200" y1="150" x2="160" y2="130" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <line x1="200" y1="150" x2="240" y2="130" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <line x1="200" y1="150" x2="160" y2="170" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <line x1="200" y1="150" x2="240" y2="170" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <line x1="200" y1="150" x2="200" y2="110" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <line x1="200" y1="150" x2="200" y2="190" stroke="#3b82f6" stroke-width="1" opacity="0.4"/>
                            <text x="140" y="100" font-family="monospace" font-size="13" fill="#3b82f6" opacity="0.5">$</text>
                            <text x="250" y="100" font-family="monospace" font-size="13" fill="#3b82f6" opacity="0.5">&euro;</text>
                            <text x="120" y="150" font-family="monospace" font-size="13" fill="#3b82f6" opacity="0.5">&yen;</text>
                            <text x="270" y="150" font-family="monospace" font-size="13" fill="#3b82f6" opacity="0.5">&pound;</text>
                            <text x="140" y="200" font-family="monospace" font-size="11" fill="#3b82f6" opacity="0.5">CHF</text>
                            <text x="250" y="200" font-family="monospace" font-size="11" fill="#3b82f6" opacity="0.5">Au</text>
                            <ellipse cx="200" cy="150" rx="90" ry="30" fill="none" stroke="#3b82f6" stroke-width="1" opacity="0.2" stroke-dasharray="5,5"/>
                            <ellipse cx="200" cy="150" rx="110" ry="40" fill="none" stroke="#14b8a6" stroke-width="1" opacity="0.15" stroke-dasharray="5,5"/>
                            <text x="178" y="255" font-family="monospace" font-size="18" fill="#3b82f6" opacity="0.5">&lt;/&gt;</text>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Build This -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900">Why Build This?</h2>
                <p class="mt-4 text-lg text-slate-500">The problems and questions that drive this project</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @php
                    $reasons = [
                        ['title' => 'Transparency', 'desc' => "Core banking systems are rarely open. ' . config('brand.name', 'Zelta') . ' lets developers see how ledgers, transactions, and financial workflows actually work\x{2014}no black boxes.", 'color' => 'blue', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                        ['title' => 'Experimentation', 'desc' => 'What if users could vote on their currency\'s composition? What if AI agents could autonomously transact? ' . config('brand.name', 'Zelta') . ' is where these ideas become working code.', 'color' => 'teal', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                        ['title' => 'Architecture Patterns', 'desc' => "Event sourcing, CQRS, domain-driven design, saga patterns\x{2014}real implementations of patterns that are often only discussed in theory.", 'color' => 'slate', 'icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
                    ];
                @endphp
                @foreach($reasons as $i => $reason)
                <div class="card-feature animate-on-scroll stagger-{{ $i + 1 }}">
                    <div class="icon-box-lg bg-{{ $reason['color'] }}-50 mb-5">
                        <svg class="w-6 h-6 text-{{ $reason['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $reason['icon'] }}"/></svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-3">{{ $reason['title'] }}</h3>
                    <p class="text-slate-500 leading-relaxed">{{ $reason['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- What's Inside (Timeline) -->
    <section class="py-24 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900">What's Inside</h2>
                <p class="mt-4 text-lg text-slate-500">Key capabilities built into the platform</p>
            </div>

            @php
                $capabilities = [
                    ['title' => 'Global Currency Unit (GCU)', 'desc' => 'A basket currency backed by USD, EUR, GBP, CHF, JPY, and gold. Users vote monthly on composition through stake-weighted governance with full event-sourced audit trails.'],
                    ['title' => 'Event-Sourced Ledger', 'desc' => "Every transaction is stored as an immutable event. Complete audit trails, point-in-time reconstruction, and replay capability\x{2014}built with Spatie Event Sourcing."],
                    ['title' => 'AI Agent Protocol', 'desc' => "Implementation of Google's A2A protocol for AI agent commerce. Agents can register, negotiate, and execute transactions with escrow services and reputation tracking."],
                    ['title' => 'Banking API Patterns', 'desc' => 'Open Banking-compliant API adapters including Ondato KYC, Chainalysis sanctions screening, and Marqeta card issuing for real-world integration patterns.'],
                    ['title' => 'Cross-Chain & DeFi', 'desc' => 'Bridge protocols (Wormhole, LayerZero, Axelar), DEX aggregation via Uniswap/Aave/Curve/Lido, cross-chain swaps, and multi-chain portfolio management.'],
                    ['title' => 'Privacy & Identity', 'desc' => 'ZK-KYC proofs, Merkle tree commitments, soulbound tokens, W3C verifiable credentials, Shamir secret sharing, and delegated proof verification.'],
                    ['title' => 'GraphQL API', 'desc' => 'Lighthouse-powered GraphQL covering 39 domains with real-time subscriptions, N+1 safe DataLoaders, and cursor-based pagination alongside REST/OpenAPI.'],
                    ['title' => 'Plugin Marketplace', 'desc' => 'Extensible plugin system with sandboxed execution, static security scanning, hook-based integration points, and a manager UI for discovering and installing plugins.'],
                    ['title' => 'Event Streaming', 'desc' => 'Redis Streams-powered event streaming with a live dashboard, consumer groups, backpressure handling, and dead-letter queues for reliable event processing.'],
                    ['title' => 'Compliance Certification', 'desc' => 'SOC 2 Type II and PCI DSS readiness tooling, GDPR enhanced privacy (ROPA, DPIA, breach notification, consent v2), and multi-region deployment support.'],
                ];
            @endphp

            <div class="timeline animate-on-scroll">
                @foreach($capabilities as $cap)
                <div class="timeline-node">
                    <h3 class="font-display text-lg font-bold text-slate-900 mb-1.5">{{ $cap['title'] }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ $cap['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Open Source -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900">Built in the Open</h2>
                <p class="mt-4 text-lg text-slate-500">Transparency isn't just a feature&mdash;it's the foundation</p>
            </div>

            <div class="card-feature !p-8 mb-8 text-center animate-on-scroll stagger-1">
                <svg class="w-12 h-12 text-slate-900 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                </svg>
                <h3 class="font-display text-xl font-bold text-slate-900 mb-2">MIT Licensed</h3>
                <p class="text-slate-500 mb-6 max-w-lg mx-auto">
                    Fork it. Learn from it. Build on it. {{ config('brand.name', 'Zelta') }} is fully open source under the MIT license.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-secondary">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
                        View on GitHub
                    </a>
                    <a href="{{ route('developers') }}" class="btn-outline-dark">
                        Read the Docs
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 animate-on-scroll stagger-2">
                <div class="card-feature">
                    <h4 class="font-display text-base font-bold text-slate-900 mb-2">For Developers</h4>
                    <p class="text-sm text-slate-500 mb-3">
                        Fork the codebase, contribute features, or explore how production banking systems are built under the hood.
                    </p>
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="text-sm text-blue-600 font-semibold hover:text-blue-700 transition">
                        Contribute on GitHub &rarr;
                    </a>
                </div>
                <div class="card-feature">
                    <h4 class="font-display text-base font-bold text-slate-900 mb-2">For Founders</h4>
                    <p class="text-sm text-slate-500 mb-3">
                        Build your fintech product on battle-tested infrastructure. 49 domain modules, MIT licensed, ready to customize.
                    </p>
                    <a href="{{ route('developers') }}" class="text-sm text-blue-600 font-semibold hover:text-blue-700 transition">
                        View Documentation &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">See It in Action</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Create a free account to explore the GCU, governance voting, cross-chain operations, and the full banking interface.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    See It in Action
                </a>
                <a href="{{ route('features') }}" class="btn-outline px-8 py-4 text-lg">
                    See Features
                </a>
            </div>
        </div>
    </section>
@endsection
