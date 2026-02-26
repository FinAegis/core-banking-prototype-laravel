@extends('layouts.public')

@section('title', 'FinAegis - Open Source Core Banking Infrastructure')

@section('seo')
    @include('partials.seo', [
        'title' => 'FinAegis - Open Source Core Banking Infrastructure',
        'description' => 'Open-source core banking infrastructure with 42 DDD domains, event sourcing, cross-chain DeFi, privacy-preserving identity, RegTech compliance, AI analytics, and HTTP-native micropayments. Built with Laravel.',
        'keywords' => 'FinAegis, open source banking, core banking infrastructure, GCU, event sourcing, DeFi, cross-chain, RegTech, banking API, Laravel fintech',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="organization" />
    <x-schema type="website" />
@endsection

@push('styles')
<style>
    .gradient-bg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .feature-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    .sub-product-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    .sub-product-card:hover {
        border-color: #667eea;
        transform: scale(1.02);
    }
    .gcu-highlight {
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        border: 2px solid #667eea;
    }
</style>
@endpush

@section('content')

        <!-- Hero Section -->
        <section class="gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="inline-flex items-center bg-white/20 backdrop-blur rounded-full px-4 py-2 mb-6">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        Open Source Core Banking Infrastructure
                    </div>
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        The Banking Infrastructure<br/>
                        Developers Deserve
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        42 domain modules covering everything from <a href="{{ route('features.show', 'gcu') }}" class="text-white underline hover:text-purple-100">democratic currency governance</a> to cross-chain DeFi, privacy-preserving identity, AI-driven analytics, and HTTP-native micropayments. Open source. MIT licensed.
                    </p>
                    <p class="mb-8">
                        <a href="{{ route('about') }}" class="text-purple-200 hover:text-white underline">Why we built this →</a>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Explore the Platform
                        </a>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition inline-flex items-center justify-center">
                            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                            </svg>
                            View on GitHub
                        </a>
                    </div>
                </div>
            </div>

            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- What Is This Section -->
        <section class="py-20 bg-gradient-to-r from-indigo-50 to-purple-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="grid md:grid-cols-2">
                        <!-- Left Side - Project Info -->
                        <div class="p-12 bg-gradient-to-br from-indigo-600 to-purple-700 text-white">
                            <div class="mb-4">
                                <span class="inline-block px-4 py-2 bg-white/20 backdrop-blur rounded-full text-sm font-semibold">
                                    MIT Licensed &middot; Open Source
                                </span>
                            </div>
                            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                                What Is FinAegis?
                            </h2>
                            <p class="text-lg mb-6 text-indigo-100">
                                A production-grade core banking platform built with Laravel and domain-driven design. 42 bounded contexts, event sourcing, CQRS, and every integration pattern a modern fintech needs—from cross-chain bridges to AI agent commerce.
                            </p>
                            <div class="space-y-4 mb-8">
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Cross-chain bridges, DeFi protocols & multi-chain portfolio</span>
                                </div>
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Privacy-preserving identity with ZK-KYC & verifiable credentials</span>
                                </div>
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>RegTech compliance, mobile payments & Banking-as-a-Service</span>
                                </div>
                            </div>
                            <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                                <p class="text-sm">
                                    <strong>Sandbox environment:</strong> All transactions use test data. Explore every feature freely.
                                </p>
                            </div>
                        </div>

                        <!-- Right Side - Who It's For -->
                        <div class="p-12">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6">Who Is This For?</h3>

                            <div class="space-y-6 mb-8">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-2">Developers & Architects</h4>
                                    <p class="text-gray-600">See how event sourcing, CQRS, and DDD work in a real financial system—not just theory, but running code.</p>
                                </div>

                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-2">Fintech Founders</h4>
                                    <p class="text-gray-600">Fork the codebase and build your product on top of battle-tested banking infrastructure. MIT licensed.</p>
                                </div>

                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-2">AI/ML Engineers</h4>
                                    <p class="text-gray-600">Integrate AI agents into financial workflows with our MCP tools, A2A protocol, and natural language transaction queries.</p>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="{{ route('developers') }}" class="flex-1 text-center bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl">
                                    Developer Docs
                                </a>
                                <a href="{{ route('register') }}" class="flex-1 text-center border-2 border-indigo-600 text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-indigo-50 transition">
                                    Explore Demo
                                </a>
                            </div>

                            <p class="text-sm text-gray-500 mt-6 text-center">
                                MIT Licensed.
                                <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="text-indigo-600 hover:underline">Contribute on GitHub</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Key Features Section -->
        <section id="features" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Built-In Capabilities</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        42 domain modules spanning payments, lending, compliance, DeFi, privacy, mobile wallets, AI analytics, and more
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- GCU Feature -->
                    <a href="{{ route('features.show', 'gcu') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-indigo-500 transition-all">
                        <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                            <span class="text-2xl font-bold text-indigo-600">Ǥ</span>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Global Currency Unit</h3>
                        <p class="text-gray-600 mb-4">
                            A multi-currency basket with democratic governance. Users vote on composition across USD, EUR, GBP, CHF, JPY, and gold.
                        </p>
                        <span class="text-indigo-600 font-medium hover:text-indigo-700">
                            Learn more →
                        </span>
                    </a>

                    <!-- Multi-Asset Support -->
                    <a href="{{ route('features.show', 'multi-asset') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-purple-500 transition-all">
                        <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Multi-Asset Accounts</h3>
                        <p class="text-gray-600 mb-4">
                            Hold fiat, crypto, and commodities in a single account with real-time conversion and portfolio tracking.
                        </p>
                        <span class="text-purple-600 font-medium hover:text-purple-700">
                            Explore assets →
                        </span>
                    </a>

                    <!-- Event Sourcing -->
                    <a href="{{ route('features.show', 'settlements') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-green-500 transition-all">
                        <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Event-Sourced Ledger</h3>
                        <p class="text-gray-600 mb-4">
                            Every transaction is an immutable event. Full audit trails, point-in-time reconstruction, and replay capability built in.
                        </p>
                        <span class="text-green-600 font-medium hover:text-green-700">
                            See architecture →
                        </span>
                    </a>

                    <!-- Democratic Governance -->
                    <a href="{{ route('features.show', 'governance') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-yellow-500 transition-all">
                        <div class="w-14 h-14 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Democratic Governance</h3>
                        <p class="text-gray-600 mb-4">
                            Stake-weighted voting on monetary policy. Users shape their currency through on-chain governance proposals.
                        </p>
                        <span class="text-yellow-600 font-medium hover:text-yellow-700">
                            Try voting →
                        </span>
                    </a>

                    <!-- Banking APIs -->
                    <a href="{{ route('features.show', 'bank-integration') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-red-500 transition-all">
                        <div class="w-14 h-14 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Banking API Patterns</h3>
                        <p class="text-gray-600 mb-4">
                            Open Banking-compliant API patterns with Ondato KYC, Chainalysis sanctions screening, and Marqeta card issuing adapters.
                        </p>
                        <span class="text-red-600 font-medium hover:text-red-700">
                            View APIs →
                        </span>
                    </a>

                    <!-- Developer APIs -->
                    <a href="{{ route('features.show', 'api') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-blue-500 transition-all">
                        <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">REST, GraphQL & OpenAPI</h3>
                        <p class="text-gray-600 mb-4">
                            Full REST coverage with OpenAPI specs, GraphQL across 34 domains with real-time subscriptions, and configurable webhooks.
                        </p>
                        <span class="text-blue-600 font-medium hover:text-blue-700">
                            View docs →
                        </span>
                    </a>

                    <!-- AI Agent Framework -->
                    <a href="{{ route('ai-framework') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-cyan-500 transition-all">
                        <div class="w-14 h-14 bg-gradient-to-br from-cyan-100 to-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">AI Agent Protocol</h3>
                        <p class="text-gray-600 mb-4">
                            Google A2A protocol for autonomous agent-to-agent commerce. MCP tools, spending limits, and transaction analytics built in.
                        </p>
                        <span class="text-cyan-600 font-medium hover:text-cyan-700">
                            Explore AI →
                        </span>
                    </a>

                    <!-- Cross-Chain & DeFi -->
                    <a href="{{ route('features.show', 'crosschain-defi') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-orange-500 transition-all">
                        <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Cross-Chain & DeFi</h3>
                        <p class="text-gray-600 mb-4">
                            Bridge across Wormhole, LayerZero, and Axelar. Aggregate DEX liquidity, optimize yield, and manage multi-chain portfolios.
                        </p>
                        <span class="text-orange-600 font-medium hover:text-orange-700">
                            Explore bridges →
                        </span>
                    </a>

                    <!-- Privacy & Identity -->
                    <a href="{{ route('features.show', 'privacy-identity') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-teal-500 transition-all">
                        <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Privacy & Identity</h3>
                        <p class="text-gray-600 mb-4">
                            Prove compliance without exposing data. ZK-KYC proofs, W3C verifiable credentials, soulbound tokens, and Shamir key management.
                        </p>
                        <span class="text-teal-600 font-medium hover:text-teal-700">
                            Learn more →
                        </span>
                    </a>

                    <!-- Mobile Payments -->
                    <a href="{{ route('features.show', 'mobile-payments') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-pink-500 transition-all">
                        <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Mobile Payments</h3>
                        <p class="text-gray-600 mb-4">
                            Full mobile backend: passkey authentication, payment intents, P2P transfers, push notifications, and ERC-4337 account abstraction.
                        </p>
                        <span class="text-pink-600 font-medium hover:text-pink-700">
                            View mobile →
                        </span>
                    </a>

                    <!-- RegTech Compliance -->
                    <a href="{{ route('features.show', 'regtech-compliance') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-amber-500 transition-all">
                        <div class="w-14 h-14 bg-amber-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">RegTech Compliance</h3>
                        <p class="text-gray-600 mb-4">
                            MiFID II, MiCA, and Travel Rule adapters with jurisdiction-aware routing. Automated reporting and Ondato KYC integration.
                        </p>
                        <span class="text-amber-600 font-medium hover:text-amber-700">
                            View compliance →
                        </span>
                    </a>

                    <!-- Banking-as-a-Service -->
                    <a href="{{ route('features.show', 'baas-platform') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-violet-500 transition-all">
                        <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Banking-as-a-Service</h3>
                        <p class="text-gray-600 mb-4">
                            White-label your banking stack. Partner APIs, auto-generated SDKs, embeddable widgets, and usage-based billing.
                        </p>
                        <span class="text-violet-600 font-medium hover:text-violet-700">
                            Explore BaaS →
                        </span>
                    </a>

                    <!-- Multi-Tenancy -->
                    <a href="{{ route('features.show', 'multi-tenancy') }}" class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-emerald-500 transition-all">
                        <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Multi-Tenancy</h3>
                        <p class="text-gray-600 mb-4">
                            Full data isolation per tenant with automated migration, per-tenant configuration, and enterprise-grade access controls.
                        </p>
                        <span class="text-emerald-600 font-medium hover:text-emerald-700">
                            Learn more →
                        </span>
                    </a>

                    <div class="feature-card bg-white border border-gray-200 rounded-xl p-8 block hover:border-sky-500 transition-all">
                        <div class="w-14 h-14 bg-sky-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">x402 Micropayments</h3>
                        <p class="text-gray-600 mb-4">
                            Monetize any API endpoint with per-request stablecoin payments. Built on the x402 protocol, enabling USDC-based pay-per-call on Base with instant settlement and agent-level spending controls.
                        </p>
                        <span class="text-sky-600 font-medium">
                            v5.2.0
                        </span>
                    </div>
                </div>

                <div class="text-center mt-12">
                    <a href="{{ route('features') }}" class="inline-flex items-center justify-center bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl">
                        See All Features
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Platform Overview Section -->
        <section id="platform" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Platform Architecture</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        42 bounded contexts built with domain-driven design, event sourcing, and CQRS. Each module implements specific financial system patterns you can use independently.
                    </p>
                </div>

                <!-- Platform Visual -->
                <div class="relative">
                    <!-- Core Platform -->
                    <div class="gcu-highlight rounded-2xl p-8 mb-8">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">FinAegis Core Platform</h3>
                            <p class="text-gray-600">Domain-driven design · Event sourcing · CQRS</p>
                        </div>

                        <!-- GCU as Primary Product -->
                        <div class="bg-white rounded-xl p-8 shadow-lg mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-3xl font-bold text-indigo-600">Global Currency Unit (GCU)</h4>
                                <span class="bg-indigo-100 text-indigo-800 px-4 py-2 rounded-full text-sm font-semibold">Flagship</span>
                            </div>
                            <p class="text-lg text-gray-700 mb-6">
                                A democratically governed basket currency where users vote on composition. The system automatically rebalances across six reserve assets based on community governance.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-5xl font-bold text-indigo-600 mb-2">Ǥ</div>
                                    <p class="text-gray-600">Currency Symbol</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-900 mb-2">6 Assets</div>
                                    <p class="text-gray-600">USD, EUR, GBP, CHF, JPY, XAU</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-900 mb-2">Mock Banks</div>
                                    <p class="text-gray-600">Simulated bank connectors</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-modules -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Exchange -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">Exchange Module</h5>
                                <p class="text-sm text-gray-600 mb-3">Order matching engine with limit & market orders</p>
                                <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold">Core</span>
                            </div>

                            <!-- Lending -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">Lending Module</h5>
                                <p class="text-sm text-gray-600 mb-3">P2P lending with risk assessment</p>
                                <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold">Core</span>
                            </div>

                            <!-- Stablecoins -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">Stablecoin Module</h5>
                                <p class="text-sm text-gray-600 mb-3">Minting, burning, and peg management</p>
                                <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold">Core</span>
                            </div>

                            <!-- Treasury -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">Treasury Module</h5>
                                <p class="text-sm text-gray-600 mb-3">Portfolio management and yield optimization</p>
                                <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-semibold">Core</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-center text-gray-600 mt-6">
                        Each module is independently usable. Explore the code to understand the patterns, or fork and build on them.
                    </p>
                </div>
            </div>
        </section>

        <!-- GCU Focus Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">The GCU Concept</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        What if users could vote on their currency's composition? The GCU is a working implementation of democratic monetary policy.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Democratic -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Democratic</h3>
                        <p class="text-gray-600 mb-4">
                            Users vote on currency basket composition. Holdings determine voting weight in this governance model.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Monthly voting cycles
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Transparent tallying
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Event-sourced audit trail
                            </li>
                        </ul>
                    </div>

                    <!-- Multi-Asset -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Multi-Asset Backed</h3>
                        <p class="text-gray-600 mb-4">
                            Basket currency backed by six reserve assets—USD, EUR, GBP, CHF, JPY, and gold—with automatic rebalancing.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                6 reserve assets
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Automatic rebalancing
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Real-time diversification
                            </li>
                        </ul>
                    </div>

                    <!-- Technical -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Production Architecture</h3>
                        <p class="text-gray-600 mb-4">
                            Built with the same patterns used in production banking systems. Event sourcing, CQRS, and DDD throughout.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Event sourcing
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                CQRS pattern
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Domain-driven design
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- CTA Button -->
                <div class="text-center mt-12">
                    <a href="{{ route('features.show', 'gcu') }}" class="inline-flex items-center justify-center bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl">
                        Explore the GCU Concept
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Open Source Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h3 class="text-2xl font-bold text-gray-900">Built in the Open</h3>
                </div>
                <div class="bg-gray-50 rounded-2xl p-12 text-center">
                    <h3 class="text-xl font-semibold mb-2 flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                        </svg>
                        Open Source & MIT Licensed
                    </h3>
                    <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
                        FinAegis is fully open source. Fork it, learn from it, contribute to it. Whether you're building a fintech startup, researching banking architecture, or just curious how these systems work—dive in.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="inline-flex items-center justify-center bg-gray-900 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-800 transition">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                            </svg>
                            View on GitHub
                        </a>
                        <a href="{{ route('developers') }}" class="inline-flex items-center justify-center border-2 border-gray-900 text-gray-900 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Read the Docs
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tech Stack Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Technology Stack</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Built with modern, well-documented technologies. Easy to understand, extend, and deploy.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Laravel -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition text-center">
                        <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-red-600" viewBox="0 0 50 52" fill="currentColor">
                                <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Laravel 12</h3>
                        <p class="text-gray-600">PHP 8.4+ with strict types, event sourcing via Spatie, and DDD structure</p>
                    </div>

                    <!-- Event Sourcing -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Event Sourcing</h3>
                        <p class="text-gray-600">Complete audit trails with Spatie Event Sourcing and CQRS patterns</p>
                    </div>

                    <!-- AI/MCP -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition text-center">
                        <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">AI Agent Protocol</h3>
                        <p class="text-gray-600">Google A2A protocol implementation with MCP tool integration</p>
                    </div>

                    <!-- APIs -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">REST & GraphQL APIs</h3>
                        <p class="text-gray-600">OpenAPI/Swagger docs, GraphQL (34 domains), webhooks, and comprehensive test coverage</p>
                    </div>
                </div>

                <p class="text-center text-gray-600 italic mt-8">
                    See <a href="{{ route('developers') }}" class="text-indigo-600 hover:underline">developer documentation</a> for full stack details
                </p>
            </div>
        </section>

        <!-- Developer Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                    <div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-4">Built for Developers</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Clone, run, and ship. Every endpoint is documented, every pattern is tested, and the full codebase is yours to fork and build on.
                        </p>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">RESTful APIs</h4>
                                    <p class="text-gray-600">Well-documented endpoints with OpenAPI specs</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Comprehensive Tests</h4>
                                    <p class="text-gray-600">Pest PHP test suite with parallel execution and coverage</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Docker Ready</h4>
                                    <p class="text-gray-600">Laravel Sail for easy local development</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8">
                            <a href="{{ route('developers') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                Developer Documentation
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="mt-10 lg:mt-0">
                        <div class="bg-gray-900 rounded-lg p-6 text-gray-300 font-mono text-sm">
                            <div class="mb-2">
                                <span class="text-gray-500"># Clone and run locally</span>
                            </div>
                            <div class="mb-4">
                                <span class="text-green-400">git clone</span> https://github.com/FinAegis/core-banking-prototype-laravel.git<br/>
                                <span class="text-green-400">cd</span> core-banking-prototype-laravel<br/>
                                <span class="text-green-400">composer install</span><br/>
                                <span class="text-green-400">cp</span> .env.demo .env<br/>
                                <span class="text-green-400">php artisan</span> migrate --seed<br/>
                                <span class="text-green-400">php artisan</span> serve
                            </div>
                            <div class="mb-2">
                                <span class="text-gray-500"># Access the demo</span>
                            </div>
                            <div>
                                <span class="text-purple-400">→</span> http://localhost:8000<br/>
                                <span class="text-purple-400">→</span> Email: demo.user@gcu.global<br/>
                                <span class="text-purple-400">→</span> Password: demo123
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Ready to Explore?</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Try the live demo, fork the repo, or dive into the documentation. FinAegis is a learning resource for anyone interested in banking architecture.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Explore the Platform
                    </a>
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition inline-flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                        </svg>
                        Fork on GitHub
                    </a>
                </div>
            </div>
        </section>

@endsection
