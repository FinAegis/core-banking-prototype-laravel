@extends('layouts.public')

@section('title', 'About FinAegis - Open Source Core Banking')

@section('seo')
    @include('partials.seo', [
        'title' => 'About FinAegis - Open Source Core Banking Infrastructure',
        'description' => 'FinAegis is an open-source core banking platform with 42 DDD domains, event sourcing, CQRS, and the Global Currency Unit. Built with Laravel for fintech developers.',
        'keywords' => 'FinAegis about, open source banking, core banking platform, GCU, event sourcing, CQRS, Laravel banking, DDD, fintech infrastructure',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="organization" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'About', 'url' => url('/about')]
    ]" />
@endsection

@push('styles')
<style>
    .timeline-item {
        position: relative;
        padding-left: 30px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 10px;
        width: 10px;
        height: 10px;
        background: #4f46e5;
        border-radius: 50%;
    }
    .timeline-item::after {
        content: '';
        position: absolute;
        left: 4px;
        top: 20px;
        width: 2px;
        height: calc(100% + 20px);
        background: #e5e7eb;
    }
    .timeline-item:last-child::after {
        display: none;
    }
</style>
@endpush

@section('content')
    <!-- Hero Section -->
    <section class="bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <div class="inline-flex items-center bg-indigo-100 rounded-full px-4 py-2 mb-6">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Open Source Project
                </div>
                <h1 class="text-5xl font-bold text-gray-900 mb-6">About FinAegis</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Open-source core banking infrastructure built with Laravel—42 domain modules covering everything from democratic currency governance to AI agent commerce.
                </p>
            </div>
        </div>
    </section>

    <!-- What It Is Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">What Is FinAegis?</h2>
                    <p class="text-lg text-gray-600 mb-4">
                        FinAegis is a core banking platform built with Laravel, implementing event sourcing, CQRS, domain-driven design, and AI agent integration across 42 bounded contexts.
                    </p>
                    <p class="text-lg text-gray-600 mb-4">
                        At its heart is the <strong>Global Currency Unit (GCU)</strong>—a democratically governed basket currency where users vote on composition from six global reserve assets.
                    </p>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mt-6">
                        <p class="text-blue-800">
                            <strong>Sandbox environment:</strong> All transactions use test data. Explore every feature freely.
                        </p>
                    </div>
                    <div class="mt-8">
                        <a href="{{ route('features') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                            Explore the features
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="relative">
                    <div class="bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl p-8">
                        <svg class="w-full h-72" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Globe representing global reach -->
                            <circle cx="200" cy="150" r="70" fill="#E0E7FF" stroke="#4F46E5" stroke-width="2"/>

                            <!-- Continents simplified -->
                            <path d="M170 120C170 120 180 110 190 110C200 110 210 115 215 120C220 125 225 130 225 140C225 150 220 155 210 160C200 165 190 160 185 155C180 150 175 140 175 130C175 125 170 120 170 120Z" fill="#4F46E5" opacity="0.2"/>
                            <path d="M155 140C155 140 160 135 165 135C170 135 175 140 175 145C175 150 170 155 165 155C160 155 155 150 155 145V140Z" fill="#4F46E5" opacity="0.2"/>
                            <path d="M215 170C215 170 220 165 225 165C230 165 235 170 235 175C235 180 230 185 225 185C220 185 215 180 215 175V170Z" fill="#4F46E5" opacity="0.2"/>

                            <!-- Network connections representing architecture -->
                            <circle cx="200" cy="150" r="4" fill="#4F46E5"/>
                            <circle cx="160" cy="130" r="3" fill="#4F46E5"/>
                            <circle cx="240" cy="130" r="3" fill="#4F46E5"/>
                            <circle cx="160" cy="170" r="3" fill="#4F46E5"/>
                            <circle cx="240" cy="170" r="3" fill="#4F46E5"/>
                            <circle cx="200" cy="110" r="3" fill="#4F46E5"/>
                            <circle cx="200" cy="190" r="3" fill="#4F46E5"/>

                            <!-- Connecting lines -->
                            <line x1="200" y1="150" x2="160" y2="130" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>
                            <line x1="200" y1="150" x2="240" y2="130" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>
                            <line x1="200" y1="150" x2="160" y2="170" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>
                            <line x1="200" y1="150" x2="240" y2="170" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>
                            <line x1="200" y1="150" x2="200" y2="110" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>
                            <line x1="200" y1="150" x2="200" y2="190" stroke="#4F46E5" stroke-width="1" opacity="0.5"/>

                            <!-- Currency symbols around the globe -->
                            <text x="140" y="100" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">$</text>
                            <text x="250" y="100" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">€</text>
                            <text x="120" y="150" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">¥</text>
                            <text x="270" y="150" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">£</text>
                            <text x="140" y="200" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">CHF</text>
                            <text x="250" y="200" font-family="Arial, sans-serif" font-size="14" fill="#4F46E5" opacity="0.6">Au</text>

                            <!-- Orbit rings -->
                            <ellipse cx="200" cy="150" rx="90" ry="30" fill="none" stroke="#4F46E5" stroke-width="1" opacity="0.3" stroke-dasharray="5,5"/>
                            <ellipse cx="200" cy="150" rx="110" ry="40" fill="none" stroke="#4F46E5" stroke-width="1" opacity="0.2" stroke-dasharray="5,5"/>

                            <!-- Code bracket representing open source -->
                            <text x="185" y="250" font-family="monospace" font-size="20" fill="#4F46E5" opacity="0.7">&lt;/&gt;</text>
                        </svg>
                    </div>
                    <div class="absolute -bottom-10 -right-10 w-72 h-72 bg-indigo-100 rounded-full filter blur-3xl opacity-70"></div>
                    <div class="absolute -top-10 -left-10 w-72 h-72 bg-purple-100 rounded-full filter blur-3xl opacity-70"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why It Exists Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900">Why Build This?</h2>
                <p class="mt-4 text-xl text-gray-600">The problems and questions that drive this project</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Transparency</h3>
                    <p class="text-gray-600">
                        Core banking systems are rarely open. FinAegis lets developers see how ledgers, transactions, and financial workflows actually work—no black boxes.
                    </p>
                </div>
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Experimentation</h3>
                    <p class="text-gray-600">
                        What if users could vote on their currency's composition? What if AI agents could autonomously transact? FinAegis is where these ideas become working code.
                    </p>
                </div>
                <div class="bg-white p-8 rounded-xl shadow-lg">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Architecture Patterns</h3>
                    <p class="text-gray-600">
                        Event sourcing, CQRS, domain-driven design, saga patterns—real implementations of patterns that are often only discussed in theory.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- What's Inside Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900">What's Inside</h2>
                <p class="mt-4 text-xl text-gray-600">Key capabilities built into the platform</p>
            </div>
            <div class="max-w-3xl mx-auto">
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Global Currency Unit (GCU)</h3>
                    <p class="text-gray-600">
                        A basket currency backed by USD, EUR, GBP, CHF, JPY, and gold. Users vote monthly on composition through stake-weighted governance with full event-sourced audit trails.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Event-Sourced Ledger</h3>
                    <p class="text-gray-600">
                        Every transaction is stored as an immutable event. Complete audit trails, point-in-time reconstruction, and replay capability—built with Spatie Event Sourcing.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">AI Agent Protocol</h3>
                    <p class="text-gray-600">
                        Implementation of Google's A2A protocol for AI agent commerce. Agents can register, negotiate, and execute transactions with escrow services and reputation tracking.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Banking API Patterns</h3>
                    <p class="text-gray-600">
                        Open Banking-compliant API adapters including Ondato KYC, Chainalysis sanctions screening, and Marqeta card issuing for real-world integration patterns.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Cross-Chain & DeFi</h3>
                    <p class="text-gray-600">
                        Bridge protocols (Wormhole, LayerZero, Axelar), DEX aggregation via Uniswap/Aave/Curve/Lido, cross-chain swaps, and multi-chain portfolio management.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Privacy & Identity</h3>
                    <p class="text-gray-600">
                        ZK-KYC proofs, Merkle tree commitments, soulbound tokens, W3C verifiable credentials, Shamir secret sharing, and delegated proof verification.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">GraphQL API</h3>
                    <p class="text-gray-600">
                        Lighthouse-powered GraphQL covering 34 domains with real-time subscriptions, N+1 safe DataLoaders, and cursor-based pagination alongside REST/OpenAPI.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Plugin Marketplace</h3>
                    <p class="text-gray-600">
                        Extensible plugin system with sandboxed execution, static security scanning, hook-based integration points, and a manager UI for discovering and installing plugins.
                    </p>
                </div>
                <div class="timeline-item mb-12">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Event Streaming</h3>
                    <p class="text-gray-600">
                        Redis Streams-powered event streaming with a live dashboard, consumer groups, backpressure handling, and dead-letter queues for reliable event processing.
                    </p>
                </div>
                <div class="timeline-item">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Compliance Certification</h3>
                    <p class="text-gray-600">
                        SOC 2 Type II and PCI DSS readiness tooling, GDPR enhanced privacy (ROPA, DPIA, breach notification, consent v2), and multi-region deployment support.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Open Source Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900">Built in the Open</h2>
                <p class="mt-4 text-xl text-gray-600">Transparency isn't just a feature—it's the foundation</p>
            </div>
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                    <div class="flex items-center justify-center mb-6">
                        <svg class="w-16 h-16 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 text-center mb-4">MIT Licensed</h3>
                    <p class="text-lg text-gray-600 text-center mb-6">
                        Fork it. Learn from it. Build on it. FinAegis is fully open source under the MIT license. Use the code however you like.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800 transition">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                            </svg>
                            View on GitHub
                        </a>
                        <a href="{{ route('developers') }}" class="inline-flex items-center justify-center px-6 py-3 border-2 border-gray-900 text-gray-900 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Read the Docs
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h4 class="text-xl font-bold text-gray-900 mb-3">For Developers</h4>
                        <p class="text-gray-600 mb-4">
                            Fork the codebase, contribute features, or explore how production banking systems are built under the hood.
                        </p>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Contribute on GitHub →
                        </a>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h4 class="text-xl font-bold text-gray-900 mb-3">For Founders</h4>
                        <p class="text-gray-600 mb-4">
                            Build your fintech product on battle-tested infrastructure. 42 domain modules, MIT licensed, ready to customize.
                        </p>
                        <a href="{{ route('developers') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            View Documentation →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Try It Section -->
    <section class="py-20 bg-indigo-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold text-white mb-6">See It in Action</h2>
            <p class="text-xl text-indigo-100 mb-8 max-w-3xl mx-auto">
                Create a free account to explore the GCU, governance voting, cross-chain operations, and the full banking interface.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50">
                    See It in Action
                </a>
                <a href="{{ route('features') }}" class="inline-flex items-center justify-center px-8 py-3 border border-white text-base font-medium rounded-md text-white hover:bg-indigo-700">
                    See Features
                </a>
            </div>
        </div>
    </section>
@endsection
