@extends('layouts.public')

@section('title', 'Features - Modern Banking Platform | FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Features - Modern Banking Platform',
        'description' => 'FinAegis features - Global Currency Unit (GCU), x402 protocol, cross-chain bridges, DeFi protocols, privacy-preserving identity, mobile payments, RegTech compliance, BaaS, and AI analytics.',
        'keywords' => 'FinAegis features, GCU, global currency unit, cross-chain, DeFi, privacy, mobile payments, RegTech, BaaS, AI, multi-tenancy',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')]
    ]" />
@endsection

@push('styles')
<style>
    .gradient-bg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .feature-card {
        transition: all 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="pt-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-5xl font-bold text-gray-900 mb-6">42 Domain Modules. One Platform.</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Every building block a modern fintech needs—from democratic currency governance to AI agent commerce, cross-chain DeFi, and privacy-preserving identity.
                </p>
            </div>
        </div>
    </section>


    <!-- Development Notice -->
    <section class="py-8 bg-amber-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-amber-400">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Feature Status Guide</h3>
                        <p class="mt-2 text-gray-600">
                            FinAegis is under active development with new modules shipping regularly.
                            Features are marked with status badges below. The demo environment lets you
                            explore every feature without external dependencies.
                        </p>
                        <div class="mt-3 flex gap-4">
                            <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                                ✅ Available - Fully implemented
                            </span>
                            <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                🎭 Demo Mode - Available for testing
                            </span>
                            <span class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">
                                🚧 In Progress - Under development
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Main Features -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- GCU Feature -->
            <div class="mb-20">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <div class="inline-flex items-center px-4 py-2 bg-indigo-100 rounded-full mb-6">
                            <span class="text-indigo-600 font-semibold">Flagship Product</span>
                        </div>
                        <h2 class="text-4xl font-bold text-gray-900 mb-6">Global Currency Unit (GCU)</h2>
                        <p class="text-lg text-gray-600 mb-6">
                            A democratically governed basket currency backed by six reserve assets. Users vote on composition through stake-weighted governance with full event-sourced audit trails.
                        </p>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Backed by USD, EUR, GBP, CHF, JPY, and XAU</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Monthly democratic voting on composition</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Reduced volatility through diversification</span>
                            </li>
                        </ul>
                        <a href="{{ route('features.show', 'gcu') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                            Learn more about GCU
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="gradient-bg rounded-2xl p-8 text-white">
                        <div class="text-center">
                            <div class="text-8xl font-bold mb-4">Ǥ</div>
                            <h3 class="text-2xl font-semibold mb-6">Current Composition</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span>USD</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-12"></div>
                                        </div>
                                        <span>40%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>EUR</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-9"></div>
                                        </div>
                                        <span>30%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>GBP</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-5"></div>
                                        </div>
                                        <span>15%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>CHF</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-3"></div>
                                        </div>
                                        <span>10%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>JPY</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-1"></div>
                                        </div>
                                        <span>3%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span>XAU</span>
                                    <div class="flex items-center">
                                        <div class="bg-white/20 rounded-full h-2 w-32 mr-3">
                                            <div class="bg-white rounded-full h-2 w-1"></div>
                                        </div>
                                        <span>2%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Multi-Asset Support -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-Asset Support</h3>
                    <p class="text-gray-600 mb-4">
                        Hold and transact in multiple currencies and assets from a single account. Support for fiat, crypto, and commodities.
                    </p>
                    <a href="{{ route('features.show', 'multi-asset') }}" class="text-purple-600 font-medium hover:text-purple-700">
                        Explore assets →
                    </a>
                </div>

                <!-- Real-time Settlements -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Instant Settlements</h3>
                    <p class="text-gray-600 mb-4">
                        Real-time transaction processing with event-sourced settlement. Configurable for instant or T+1 settlement based on your compliance requirements.
                    </p>
                    <a href="{{ route('features.show', 'settlements') }}" class="text-green-600 font-medium hover:text-green-700">
                        Learn more →
                    </a>
                </div>

                <!-- Democratic Governance -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Democratic Governance</h3>
                    <p class="text-gray-600 mb-4">
                        Stake-weighted voting on monetary policy and platform decisions. Monthly governance cycles with transparent tallying and event-sourced audit trails.
                    </p>
                    <a href="{{ route('features.show', 'governance') }}" class="text-yellow-600 font-medium hover:text-yellow-700">
                        Join governance →
                    </a>
                </div>

                <!-- Bank Integration -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Bank Integration Patterns</h3>
                    <p class="text-gray-600 mb-4">
                        Open Banking-compliant API adapters including Ondato KYC, Chainalysis sanctions screening, and Marqeta card issuing.
                    </p>
                    <a href="{{ route('features.show', 'bank-integration') }}" class="text-red-600 font-medium hover:text-red-700">
                        Explore patterns →
                    </a>
                </div>

                <!-- API & Webhooks -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Developer APIs</h3>
                    <p class="text-gray-600 mb-4">
                        Full REST coverage with OpenAPI specs, GraphQL across 34 domains with real-time subscriptions, and configurable webhooks.
                    </p>
                    <a href="{{ route('features.show', 'api') }}" class="text-blue-600 font-medium hover:text-blue-700">
                        View docs →
                    </a>
                </div>

                <!-- Cross-Chain & DeFi -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-cyan-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Cross-Chain & DeFi</h3>
                    <p class="text-gray-600 mb-4">
                        Bridge assets across blockchains and access DeFi protocols. Wormhole, LayerZero, Axelar bridges with Uniswap, Aave, Curve, and Lido.
                    </p>
                    <a href="{{ route('features.show', 'crosschain-defi') }}" class="text-cyan-600 font-medium hover:text-cyan-700">
                        Explore DeFi →
                    </a>
                </div>

                <!-- Security -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Bank-Grade Security</h3>
                    <p class="text-gray-600 mb-4">
                        Quantum-resistant encryption, multi-factor authentication, and comprehensive security measures protect your assets.
                    </p>
                    <a href="{{ route('security') }}" class="text-indigo-600 font-medium hover:text-indigo-700">
                        Security details →
                    </a>
                </div>

                <!-- Privacy & Identity -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Privacy & Identity</h3>
                    <p class="text-gray-600 mb-4">
                        Prove compliance without exposing data. ZK-KYC proofs, W3C verifiable credentials, soulbound tokens, and Shamir key management.
                    </p>
                    <a href="{{ route('features.show', 'privacy-identity') }}" class="text-teal-600 font-medium hover:text-teal-700">
                        Learn more →
                    </a>
                </div>

                <!-- Mobile Payments -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Mobile Payments</h3>
                    <p class="text-gray-600 mb-4">
                        Payment intents, passkey authentication, P2P transfers, activity feed, and ERC-4337 smart account abstraction.
                    </p>
                    <a href="{{ route('features.show', 'mobile-payments') }}" class="text-pink-600 font-medium hover:text-pink-700">
                        View mobile →
                    </a>
                </div>

                <!-- RegTech Compliance -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-amber-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">RegTech Compliance</h3>
                    <p class="text-gray-600 mb-4">
                        MiFID II, MiCA, and Travel Rule compliance with jurisdiction-specific adapters and automated regulatory reporting.
                    </p>
                    <a href="{{ route('features.show', 'regtech-compliance') }}" class="text-amber-600 font-medium hover:text-amber-700">
                        View compliance →
                    </a>
                </div>

                <!-- Banking-as-a-Service -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Banking-as-a-Service</h3>
                    <p class="text-gray-600 mb-4">
                        Partner APIs, auto-generated SDKs (TypeScript, Python, Java, Go, PHP), embeddable widgets, and usage-based billing.
                    </p>
                    <a href="{{ route('features.show', 'baas-platform') }}" class="text-violet-600 font-medium hover:text-violet-700">
                        Explore BaaS →
                    </a>
                </div>

                <!-- AI Framework -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-100 to-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">AI Framework</h3>
                    <p class="text-gray-600 mb-4">
                        AI-powered transaction queries, MCP tool integration, ML anomaly detection, and Google A2A agent protocol.
                    </p>
                    <a href="{{ route('features.show', 'ai-framework') }}" class="text-purple-600 font-medium hover:text-purple-700">
                        Explore AI →
                    </a>
                </div>

                <!-- Multi-Tenancy -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-Tenancy</h3>
                    <p class="text-gray-600 mb-4">
                        Team-based isolation with tenant data migration, per-tenant configuration, and enterprise management features.
                    </p>
                    <a href="{{ route('features.show', 'multi-tenancy') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- GraphQL API -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">GraphQL API</h3>
                    <p class="text-gray-600 mb-4">
                        Lighthouse-powered GraphQL covering 34 domains with real-time subscriptions, N+1 safe DataLoaders, and cursor-based pagination.
                    </p>
                    <a href="{{ route('features.show', 'api') }}" class="text-pink-600 font-medium hover:text-pink-700">
                        View API &rarr;
                    </a>
                </div>

                <!-- Plugin Marketplace -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Plugin Marketplace</h3>
                    <p class="text-gray-600 mb-4">
                        Extensible plugin system with sandbox execution, static security scanning, hook-based integration, and a plugin manager UI.
                    </p>
                    <a href="{{ route('features') }}" class="text-violet-600 font-medium hover:text-violet-700">
                        Explore plugins &rarr;
                    </a>
                </div>

                <!-- Event Streaming -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Event Streaming</h3>
                    <p class="text-gray-600 mb-4">
                        Redis Streams-powered event streaming with a live dashboard, consumer groups, backpressure handling, and dead-letter queues.
                    </p>
                    <a href="{{ route('features') }}" class="text-teal-600 font-medium hover:text-teal-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- x402 Protocol -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">x402 Protocol</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">Available</span>
                    </div>
                    <p class="text-gray-600 mb-4">
                        HTTP-native micropayments with USDC on Base. Instant settlement, AI agent autonomous payments, spending limits, and multi-network support.
                    </p>
                    <a href="{{ route('features.show', 'x402-protocol') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- SOC 2 / PCI DSS Compliance -->
                <div class="feature-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Compliance Certification</h3>
                    <p class="text-gray-600 mb-4">
                        SOC 2 Type II and PCI DSS readiness tooling with continuous control monitoring, evidence collection, and GDPR enhanced privacy.
                    </p>
                    <a href="{{ route('security') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        View security &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature Comparison -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">FinAegis vs Traditional Banking</h2>
                <p class="text-xl text-gray-600">See how we compare to traditional financial institutions</p>
            </div>
            
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Feature</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">FinAegis</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Traditional Banks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Transaction Speed</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">< 1 second</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">1-5 days</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-700">Multi-Currency Support</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">6+ currencies</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">Limited</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Account Opening</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">5 minutes</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">Days to weeks</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-700">API Access</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">Full REST API</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">Limited or none</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-700">Governance</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">Democratic voting</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">Centralized</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-700">Transparency</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-green-600 font-semibold">Full audit trail</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">Limited</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 gradient-bg text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6">Explore the Demo</h2>
            <p class="text-xl mb-8 text-purple-100">
                See these features in action. Try the demo or explore the source code on GitHub.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                    Explore the Platform
                </a>
                <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    View Source
                </a>
            </div>
        </div>
    </section>

@endsection