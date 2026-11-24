@extends('layouts.public')

@section('title', 'Features - Modern Banking Platform | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Features - Modern Banking Platform | ' . config('brand.name', 'Zelta'),
        'description' => 'See what the 61 modules of ' . config('brand.name', 'Zelta') . ' do. They cover payments, lending, open banking, virtual cards, digital assets, AI agents and developer tools.',
        'keywords' => config('brand.name', 'Zelta') . ' features, GCU demo, ISO 20022, open banking, payment rails, ACH, SEPA, Interledger, ledger, microfinance, cross-chain, decentralised finance, privacy, x402, virtual cards, regulatory reporting, banking as a service',
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
    .feature-card {
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .feature-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 -right-32 w-80 h-80 bg-blue-500/10 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                @include('partials.breadcrumb', ['items' => [['name' => 'Features', 'url' => url('/features')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Platform <span class="text-gradient">Features</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    The platform has 61 modules in one open-source codebase. They cover payments, lending, compliance tooling, digital assets, AI agents and developer tools. Each card below explains what a module does today.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>


    <!-- Development Notice -->
    <section class="py-6 bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-stat flex items-start gap-3 border-l-4 border-amber-400 bg-amber-50/50">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="font-display text-sm font-semibold text-slate-900">Feature Status Guide</h3>
                    <p class="text-sm text-slate-600 mt-1">
                        {{ config('brand.name', 'Zelta') }} is under active development, and new modules ship regularly.
                        The demo runs every feature with test data and needs no outside services. Modules that follow payment and banking standards work at the message layer and do not connect to live schemes.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="badge badge-success">Available</span>
                        <span class="badge badge-info">Demo Mode</span>
                        <span class="badge badge-warning">In Progress</span>
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
                            <span class="text-indigo-600 font-semibold">Software Demo</span>
                        </div>
                        <h2 class="font-display text-4xl font-bold text-slate-900 mb-6">Global Currency Unit (GCU)</h2>
                        <p class="text-lg text-slate-500 mb-6">
                            The Global Currency Unit demo is a reference implementation of a unit valued against a basket of currencies and gold, built with FinAegis.
                        </p>
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-slate-600">A simulated reference basket of US dollars, euros, British pounds, Swiss francs, Japanese yen and gold</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-slate-600">Simulated basket votes</span>
                            </li>
                        </ul>
                        <p class="text-xs text-slate-500 mb-6">
                            The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
                        </p>
                        <a href="{{ route('gcu') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                            Explore the Global Currency Unit demo
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="bg-fa-navy rounded-2xl p-8 text-white">
                        <div class="text-center">
                            <div class="text-8xl font-bold mb-4">Ǥ</div>
                            <h3 class="text-2xl font-semibold mb-6">Demo Composition (simulated)</h3>
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
                <div class="card-feature">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-Asset Support</h3>
                    <p class="text-slate-500 mb-4">
                        One account can hold and move several currencies and assets. It supports national currencies, crypto assets and commodities.
                    </p>
                    <a href="{{ route('features.show', 'multi-asset') }}" class="text-purple-600 font-medium hover:text-purple-700">
                        Explore assets →
                    </a>
                </div>

                <!-- MCP-native banking -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Model Context Protocol Server <span class="ml-1 text-xs font-bold text-emerald-600 align-middle">NEW</span></h3>
                    <p class="text-slate-500 mb-4">
                        Claude, Cursor and other agents can use the payment, exchange and SMS APIs through the Model Context Protocol. They connect to one endpoint that is protected by an OAuth sign-in.
                    </p>
                    <a href="{{ route('features.show', 'mcp') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Explore the server →
                    </a>
                </div>

                <!-- Real-time Settlements -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Settlements</h3>
                    <p class="text-slate-500 mb-4">
                        Every settlement is recorded as an event. The settlement module can settle transfers in real time, in batches or as net amounts.
                    </p>
                    <a href="{{ route('features.show', 'settlements') }}" class="text-green-600 font-medium hover:text-green-700">
                        Learn more →
                    </a>
                </div>

                <!-- Democratic Governance -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Governance Module</h3>
                    <p class="text-slate-500 mb-4">
                        The governance module runs weighted votes and counts them openly. Every vote is recorded as an event. The Global Currency Unit demo uses it with simulated votes.
                    </p>
                    <a href="{{ route('features.show', 'governance') }}" class="text-yellow-600 font-medium hover:text-yellow-700">
                        Learn more →
                    </a>
                </div>

                <!-- Bank Integration -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Bank Integration Patterns</h3>
                    <p class="text-slate-500 mb-4">
                        The banking modules follow open banking API patterns. They include adapters for card issuers, Ondato identity checks and Chainalysis sanctions screening. Card operations are available through GraphQL, and the REST API checks spending limits, verifies accounts and sends webhooks.
                    </p>
                    <a href="{{ route('features.show', 'bank-integration') }}" class="text-red-600 font-medium hover:text-red-700">
                        Explore patterns →
                    </a>
                </div>

                <!-- API & Webhooks -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Developer APIs</h3>
                    <p class="text-slate-500 mb-4">
                        The REST API is described in OpenAPI. GraphQL covers 43 domains and supports live subscriptions. You can configure webhooks for the events you need.
                    </p>
                    <a href="{{ route('features.show', 'api') }}" class="text-blue-600 font-medium hover:text-blue-700">
                        View docs →
                    </a>
                </div>

                <!-- Cross-Chain & DeFi -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-cyan-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Cross-Chain and Decentralised Finance</h3>
                    <p class="text-slate-500 mb-4">
                        The module moves assets between Ethereum-compatible networks and Solana. It includes adapters for third-party protocols such as Wormhole, Circle's cross-chain transfer protocol, Uniswap V3 and Aave V3.
                    </p>
                    <a href="{{ route('features.show', 'crosschain-defi') }}" class="text-cyan-600 font-medium hover:text-cyan-700">
                        Explore cross-chain →
                    </a>
                </div>

                <!-- Security -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Security Tooling</h3>
                    <p class="text-slate-500 mb-4">
                        The platform uses multi-factor sign-in, passkeys, rate limits and audit logs. A hybrid encryption layer prepares for post-quantum standards, but it is not yet a certified ML-KEM implementation.
                    </p>
                    <a href="{{ route('security') }}" class="text-indigo-600 font-medium hover:text-indigo-700">
                        Security details →
                    </a>
                </div>

                <!-- Privacy & Identity -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Privacy and Identity</h3>
                    <p class="text-slate-500 mb-4">
                        Zero-knowledge proofs let a person prove an identity fact without showing documents. Five Circom circuits with Solidity verifier contracts check age, income range, residency, identity tier and sanctions status. The module also issues verifiable credentials in the World Wide Web Consortium format and soulbound tokens and splits keys with Shamir secret sharing.
                    </p>
                    <a href="{{ route('features.show', 'privacy-identity') }}" class="text-teal-600 font-medium hover:text-teal-700">
                        Learn more →
                    </a>
                </div>

                <!-- Virtual Cards (v7.18.0) -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Virtual Cards</h3>
                    <p class="text-slate-500 mb-4">
                        Version 7.18.0 added a provider-neutral card layer with Lepo Finance as the first provider. Virtual cards are built for a closed pilot and stay switched off until the pilot starts. Adding money to a card from the wallet also stays switched off for now. Card numbers are shown only on the card partner's secure page. Approval codes for online payments appear after the person confirms it is them.
                    </p>
                    <a href="{{ route('changelog') }}" class="text-pink-600 font-medium hover:text-pink-700">
                        Read the release notes →
                    </a>
                </div>

                <!-- Mobile Payments -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Mobile Payments</h3>
                    <p class="text-slate-500 mb-4">
                        The mobile backend signs people in with passkeys and handles payment intents, peer-to-peer transfers and the activity feed. Smart accounts under ERC-4337 let the platform sponsor network fees. Solana wallets are supported, and balances update in real time.
                    </p>
                    <a href="{{ route('features.show', 'mobile-payments') }}" class="text-pink-600 font-medium hover:text-pink-700">
                        View mobile →
                    </a>
                </div>

                <!-- RegTech Compliance -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-amber-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Regulatory Reporting Tooling</h3>
                    <p class="text-slate-500 mb-4">
                        These modules build reports in the formats of the Markets in Financial Instruments Directive, the Markets in Crypto-Assets Regulation and the Travel Rule. Each jurisdiction has its own adapter. The modules do not by themselves make a deployment compliant.
                    </p>
                    <a href="{{ route('features.show', 'regtech-compliance') }}" class="text-amber-600 font-medium hover:text-amber-700">
                        View reporting tooling →
                    </a>
                </div>

                <!-- Banking-as-a-Service -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Banking-as-a-Service</h3>
                    <p class="text-slate-500 mb-4">
                        Partners can run the platform under their own brand. They get partner APIs, official libraries for TypeScript, Python and PHP, embeddable widgets and usage-based billing.
                    </p>
                    <a href="{{ route('features.show', 'baas-platform') }}" class="text-violet-600 font-medium hover:text-violet-700">
                        Explore banking as a service →
                    </a>
                </div>

                <!-- AI Framework -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-100 to-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">AI Framework</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full font-medium">{{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} Tools</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        Six specialised agents share {{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} Model Context Protocol tools, and each tool needs its own OAuth permission. Statistical models flag unusual transactions. Several agents can vote on a decision before it runs.
                    </p>
                    <a href="{{ route('features.show', 'ai-framework') }}" class="text-purple-600 font-medium hover:text-purple-700">
                        Explore AI →
                    </a>
                </div>

                <!-- Agent Protocol -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-cyan-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">Agent Protocol</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-cyan-100 text-cyan-700 text-xs rounded-full font-medium">New</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        Agents prove who they are with decentralised identifiers and message each other through Google's Agent2Agent protocol. Escrow with dispute resolution and reputation scores protect each trade. Payment mandates under the Agent Payments Protocol limit what an agent may spend.
                    </p>
                    <a href="{{ route('features.show', 'agent-protocol') }}" class="text-cyan-600 font-medium hover:text-cyan-700">
                        Explore the agent protocol →
                    </a>
                </div>

                <!-- Multi-Tenancy -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-Tenancy</h3>
                    <p class="text-slate-500 mb-4">
                        Each team's data is kept apart from every other team, and every change is written to an audit log. Plan limits are enforced on each request. A deleted tenant can be restored for 14 days, and each tenant has its own settings.
                    </p>
                    <a href="{{ route('features.show', 'multi-tenancy') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- GraphQL API -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-pink-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">GraphQL API</h3>
                    <p class="text-slate-500 mb-4">
                        The GraphQL API is built with Lighthouse and covers 43 domains. It supports live subscriptions and cursor pagination, and it batches database queries.
                    </p>
                    <a href="{{ route('features.show', 'api') }}" class="text-pink-600 font-medium hover:text-pink-700">
                        View API &rarr;
                    </a>
                </div>

                <!-- Plugin Marketplace -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Plugin Marketplace</h3>
                    <p class="text-slate-500 mb-4">
                        Plugins run in a sandbox and are scanned for security problems before they load. They connect through hooks, and a manager screen installs them.
                    </p>
                    <a href="{{ route('features.show', 'plugin-marketplace') }}" class="text-violet-600 font-medium hover:text-violet-700">
                        Explore plugins &rarr;
                    </a>
                </div>

                <!-- Event Streaming -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Event Streaming</h3>
                    <p class="text-slate-500 mb-4">
                        Domain events are published to Redis Streams. Consumer groups read them, slow down when they fall behind and move failed events to a dead-letter queue. A live dashboard shows the flow.
                    </p>
                    <a href="{{ route('features') }}" class="text-teal-600 font-medium hover:text-teal-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- x402 Protocol -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">x402 Protocol</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">Available</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        An API can ask for a small USDC payment before it answers a request. Payments run on Base, Ethereum, Avalanche and Solana. AI agents pay on their own within spending limits.
                    </p>
                    <a href="{{ route('features.show', 'x402-protocol') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Visa CLI -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">Card-Payment CLI Adapter</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Beta</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        This adapter connects to visa-cli, a third-party command-line tool for card payments. The tool is not affiliated with or endorsed by Visa. The adapter lets AI agents and developers pay and collect invoices within spending limits.
                    </p>
                    <a href="{{ route('features.show', 'visa-cli') }}" class="text-blue-600 font-medium hover:text-blue-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Zelta CLI -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">Zelta CLI</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs rounded-full font-medium">v0.2.9</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        The Zelta CLI manages payments, SMS, wallets and paid APIs from the terminal. It prints tables for people and JSON for AI agents.
                    </p>
                    <a href="{{ route('features.show', 'zelta-cli') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Virtuals Agent Integration -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-violet-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">AI Agent Commerce</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-violet-100 text-violet-700 text-xs rounded-full font-medium">New</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        AI agents get programmable wallets with spending limits. The Virtuals Protocol adapter checks each agent's identity with TrustCert. Pimlico enforces the limits on the network.
                    </p>
                    <a href="{{ route('features.show', 'virtuals-protocol') }}" class="text-violet-600 font-medium hover:text-violet-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Machine Payments (MPP + x402 + AP2) -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="text-xl font-semibold">Machine Payments</h3>
                        <span class="inline-flex items-center px-2 py-0.5 bg-orange-100 text-orange-700 text-xs rounded-full font-medium">New</span>
                    </div>
                    <p class="text-slate-500 mb-4">
                        AI agents can pay through three protocols. x402 uses USDC, the Machine Payments Protocol supports Stripe, Lightning and stablecoins, and the Agent Payments Protocol uses spending mandates. SMS messages through VertexSMS can be paid on several rails.
                    </p>
                    <a href="{{ route('features.show', 'machine-payments') }}" class="text-orange-600 font-medium hover:text-orange-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- SOC 2 / PCI DSS Compliance -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Compliance Tooling</h3>
                    <p class="text-slate-500 mb-4">
                        Readiness tooling helps a team prepare for a SOC 2 Type II audit and for an audit against the Payment Card Industry Data Security Standard. It monitors controls and collects evidence. No certification is held. Privacy tooling supports the European Union General Data Protection Regulation.
                    </p>
                    <a href="{{ route('security') }}" class="text-emerald-600 font-medium hover:text-emerald-700">
                        View security &rarr;
                    </a>
                </div>

                <!-- ISO 20022 Messaging -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">ISO 20022 Messaging</h3>
                    <p class="text-slate-500 mb-4">
                        The module reads, builds and validates eight ISO 20022 message types from the pacs, pain and camt families. REST and GraphQL APIs expose it. It works at the message layer and does not connect to a live scheme.
                    </p>
                    <a href="{{ route('features.show', 'iso20022') }}" class="text-blue-600 font-medium hover:text-blue-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Open Banking & PSD2 -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-teal-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Open Banking</h3>
                    <p class="text-slate-500 mb-4">
                        These modules follow the European Union's second Payment Services Directive. They manage consents for account information and payment initiation. They include adapters for the Berlin Group NextGenPSD2 standard and the United Kingdom Open Banking standard. They also store and check the certificates of third-party providers. They run in demo mode and do not connect to real banks.
                    </p>
                    <a href="{{ route('features.show', 'open-banking') }}" class="text-teal-600 font-medium hover:text-teal-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Payment Rails -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Payment Rails</h3>
                    <p class="text-slate-500 mb-4">
                        These modules build payment messages for Automated Clearing House files, Fedwire, Real-Time Payments, FedNow, SEPA direct debits and SEPA instant credit transfers. Routing logic picks a rail for each payment. They work at the message layer and do not settle real payments.
                    </p>
                    <a href="{{ route('features.show', 'payment-rails') }}" class="text-orange-600 font-medium hover:text-orange-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- ISO 8583 Card Processing -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-slate-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">ISO 8583 Card Processing</h3>
                    <p class="text-slate-500 mb-4">
                        ISO 8583 is the message format that card networks use. The module encodes and decodes these messages and handles authorisations, reversals and settlement messages.
                    </p>
                    <a href="{{ route('features') }}" class="text-slate-600 font-medium hover:text-slate-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Interledger Protocol -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Interledger Protocol</h3>
                    <p class="text-slate-500 mb-4">
                        The Interledger connector moves value between networks and quotes across currencies. It supports Open Payments with its own authorisation protocol.
                    </p>
                    <a href="{{ route('features.show', 'interledger') }}" class="text-purple-600 font-medium hover:text-purple-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Double-Entry Ledger -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-gray-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Double-Entry Ledger</h3>
                    <p class="text-slate-500 mb-4">
                        The accounting engine keeps a chart of accounts and journal entries, posts to the general ledger and produces a trial balance. It runs in PHP by default. An optional TigerBeetle driver handles very high volumes.
                    </p>
                    <a href="{{ route('features.show', 'ledger') }}" class="text-gray-600 font-medium hover:text-gray-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Microfinance Suite -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Microfinance Suite</h3>
                    <p class="text-slate-500 mb-4">
                        The microfinance module supports group loans with joint liability. It sets loan-loss provisions by how many days a loan is overdue. It also covers cooperative share accounts, teller work, field officer tools and savings accounts that track dormancy.
                    </p>
                    <a href="{{ route('features.show', 'microfinance') }}" class="text-green-600 font-medium hover:text-green-700">
                        Learn more &rarr;
                    </a>
                </div>

                <!-- Developer Experience -->
                <div class="card-feature">
                    <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Developer Experience</h3>
                    <p class="text-slate-500 mb-4">
                        Partners can create a sandbox from one of three seed profiles. Developers can test and replay webhooks and manage API keys from the command line.
                    </p>
                    <a href="{{ route('features.show', 'developer-experience') }}" class="text-indigo-600 font-medium hover:text-indigo-700">
                        Learn more &rarr;
                    </a>
                </div>
            </div>
            {{-- F2 once per provider list (see docs/REGULATORY-CLAIMS.md) --}}
            <p class="mt-10 text-xs text-slate-400 text-center">Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-4xl font-bold text-white mb-6">Explore the Demo</h2>
            <p class="text-lg mb-10 text-slate-400">
                You can try these features in the demo with test data. The source code is on GitHub.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary btn-lg">
                    Explore the Platform
                </a>
                <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-outline btn-lg">
                    View Source
                </a>
            </div>
        </div>
    </section>

@endsection