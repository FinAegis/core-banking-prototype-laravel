@extends('layouts.public')

@section('title', 'Agent Protocol (AP2) — Autonomous Agent Commerce | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Agent Protocol (AP2) — Autonomous Agent Commerce',
        'description' => 'Google A2A agent-to-agent protocol with DID authentication, escrow, reputation scoring, AP2 mandates, and Verifiable Digital Credentials for autonomous financial operations.',
        'keywords' => 'agent protocol, AP2, A2A, DID authentication, escrow, reputation, mandates, VDC, agent commerce, autonomous payments, ' . config('brand.name', 'Zelta'),
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Agent Protocol', 'url' => url('/features/agent-protocol')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'Agent Protocol', 'url' => url('/features/agent-protocol')]
                ]])
                <div class="inline-flex items-center px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-sm text-slate-300 mb-6">
                    <span class="w-2 h-2 bg-cyan-400 rounded-full mr-2"></span>
                    v7.10.3 &middot; Autonomous Agent Commerce
                </div>
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Agent Protocol
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    The infrastructure layer for autonomous agent commerce. DID-based identity, agent-to-agent messaging, escrow with dispute resolution, reputation scoring, and AP2 payment mandates.
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ route('developers') }}" class="btn-primary px-8 py-4 text-lg">API Documentation</a>
                    <a href="{{ route('features.show', 'ai-framework') }}" class="btn-outline px-8 py-4 text-lg">AI Framework</a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-cyan-500/20 to-transparent"></div>
    </section>

    <!-- Core Pillars -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-cyan-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">DID Identity</h3>
                    <p class="text-sm text-slate-500">Decentralized identifiers for agents. Register, authenticate, and manage agent identity with cryptographic verification.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">A2A Messaging</h3>
                    <p class="text-sm text-slate-500">Structured agent-to-agent communication with priority queuing, delivery receipts, and protocol negotiation.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Escrow</h3>
                    <p class="text-sm text-slate-500">Multi-party escrow with milestone-based release, dispute resolution, voting, and automatic expiration.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-amber-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Reputation</h3>
                    <p class="text-sm text-slate-500">Trust scoring with decay, boosts, and penalties. Reputation leaderboards and threshold-based access control.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Agent Lifecycle -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Agent Lifecycle</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                From registration to autonomous commerce, each agent goes through a verified lifecycle with granular permission scoping.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-cyan-700">1</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Register</h4>
                    <p class="text-xs text-slate-500">DID creation, capability declaration</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-blue-700">2</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Verify</h4>
                    <p class="text-xs text-slate-500">KYC documents, identity checks</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-indigo-700">3</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Fund</h4>
                    <p class="text-xs text-slate-500">Wallet creation, initial deposit</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-purple-700">4</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Connect</h4>
                    <p class="text-xs text-slate-500">Protocol negotiation, capability matching</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-emerald-700">5</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Transact</h4>
                    <p class="text-xs text-slate-500">Payments, escrow, messaging</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Authentication & Security -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
                <div>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-6">Authentication & Security</h2>
                    <p class="text-lg text-slate-500 mb-6">
                        Multiple authentication methods with 23 granular scopes ensure agents operate within their authorized boundaries.
                    </p>

                    <div class="space-y-4">
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">DID Challenge-Response</h4>
                            <p class="text-sm text-slate-500">Cryptographic authentication using Decentralized Identifiers. Agents prove identity without sharing secrets.</p>
                        </div>
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">API Key Authentication</h4>
                            <p class="text-sm text-slate-500">64-character API keys for programmatic access. Per-key scoping, rotation, and revocation.</p>
                        </div>
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">Session Management</h4>
                            <p class="text-sm text-slate-500">24-hour sessions with 5-minute nonce TTL. Session listing, individual and bulk revocation.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-4">Permission Scopes</h3>
                    <div class="card-feature !p-6">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['payments.send', 'payments.receive', 'wallet.read', 'wallet.transfer', 'escrow.create', 'escrow.release', 'messaging.send', 'messaging.receive', 'reputation.read', 'reputation.update', 'compliance.read', 'admin.manage'] as $scope)
                                <div class="flex items-center text-sm">
                                    <code class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs font-mono">{{ $scope }}</code>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-4">23 scopes total &mdash; showing 12 most common. Full list at <code class="text-xs">/agent-protocol/auth/scopes</code></p>
                    </div>

                    <h3 class="text-2xl font-bold text-slate-900 mb-4 mt-8">Digital Signatures</h3>
                    <div class="card-feature !p-6">
                        <ul class="space-y-2 text-sm text-slate-600">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Transaction signing and verification
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                End-to-end message encryption
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Fraud detection with 16+ risk factors
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                AML screening with high-risk country list
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Escrow & Financial -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Financial Operations</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-cyan-900">Escrow System</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-cyan-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Multi-Party Escrow</h4>
                                <p class="text-slate-600 text-sm">$10 to $1M range with configurable hold periods and automatic expiration (30-day default).</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-cyan-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Dispute Resolution</h4>
                                <p class="text-slate-600 text-sm">Voting-based resolution for escrows above $10k. Dispute filing, evidence submission, and arbitration.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-cyan-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Event-Sourced Lifecycle</h4>
                                <p class="text-slate-600 text-sm">Full audit trail: created, funded, held, released, expired, cancelled, disputed, resolved.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-purple-900">Agent Wallets</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Multi-Currency Support</h4>
                                <p class="text-slate-600 text-sm">10+ supported currencies with per-asset fee rates (0.5%&ndash;2.5%). Fund from main accounts or external sources.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Transaction Limits</h4>
                                <p class="text-slate-600 text-sm">Configurable daily ($10k) and per-transaction ($5k) limits. Automatic escalation when exceeded.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Fee Structure</h4>
                                <p class="text-slate-600 text-sm">2.5% standard rate, $0.50 minimum, $100 maximum. Fee calculations recorded as events.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- AP2 Mandates -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">AP2 Payment Mandates</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                Google's Agent Payments Protocol v2 adds structured authorization for autonomous agent spending. Three mandate types cover every agent commerce scenario.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card-feature !p-8 border-t-4 border-cyan-500">
                    <h3 class="text-xl font-bold mb-3 text-cyan-900">Cart Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">Human-present shopping. Agent builds a cart, user approves the total, payment executes.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            W3C PaymentRequest format
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            Itemized cart with merchant DID
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            User approval required
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8 border-t-4 border-purple-500">
                    <h3 class="text-xl font-bold mb-3 text-purple-900">Intent Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">Autonomous spending. Agent receives a budget and natural-language intent, acts within constraints.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Natural language intent description
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Budget cap with constraint rules
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Delegator + agent DID binding
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8 border-t-4 border-emerald-500">
                    <h3 class="text-xl font-bold mb-3 text-emerald-900">Payment Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">Direct agent-to-agent payment. Fixed amount, specific payee, with payment method preferences.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            Payee DID with fixed amount
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            Bridges to x402 and MPP rails
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            VDC-backed authorization
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Reputation & KYC -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-start">
                <div>
                    <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Reputation System</h2>
                    <p class="text-slate-500 mb-6">
                        Trust scoring that evolves with every interaction. Agents build reputation through successful transactions, and face penalties for disputes or failures.
                    </p>
                    <div class="card-feature !p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Initial Score</span>
                                <span class="font-bold text-slate-900">50/100</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Decay After Inactivity</span>
                                <span class="font-bold text-slate-900">30 days</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Score Components</span>
                                <span class="font-bold text-slate-900">Weighted</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Public Leaderboard</span>
                                <span class="font-bold text-emerald-600">Active</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Agent KYC</h2>
                    <p class="text-slate-500 mb-6">
                        Three verification tiers unlock progressively higher transaction limits and capabilities.
                    </p>
                    <div class="space-y-3">
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Basic</h4>
                                <p class="text-xs text-slate-500">Identity document verification</p>
                            </div>
                            <span class="text-sm font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Limited</span>
                        </div>
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Enhanced</h4>
                                <p class="text-xs text-slate-500">+ Business registration, proof of address</p>
                            </div>
                            <span class="text-sm font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Higher Limits</span>
                        </div>
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Full</h4>
                                <p class="text-xs text-slate-500">+ Biometric verification, compliance officer review</p>
                            </div>
                            <span class="text-sm font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full">Unlimited</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Event Sourcing -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Event-Sourced Architecture</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                Every agent action is recorded as an immutable event. 10 aggregates and 60+ domain events provide a complete audit trail for regulatory compliance.
            </p>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                @foreach ([
                    ['Agent Identity', '7 events'],
                    ['Agent Wallet', '4 events'],
                    ['Capabilities', '6 events'],
                    ['Transactions', '10 events'],
                    ['Escrow', '10 events'],
                    ['Reputation', '5 events'],
                    ['Payments', '6 events'],
                    ['Messaging', '7 events'],
                    ['Security', '4 events'],
                    ['Mandates', '6 events'],
                ] as $aggregate)
                    <div class="text-center p-4 bg-gray-50 rounded-xl">
                        <p class="font-semibold text-sm text-slate-900">{{ $aggregate[0] }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $aggregate[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- API Endpoints -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">API Surface</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-2">Discovery</h4>
                    <p class="text-xs text-slate-400 mb-3">Public &mdash; no auth required</p>
                    <ul class="text-sm text-slate-600 space-y-1">
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/.well-known/ap2-configuration</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/agents/discover</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/agents/{did}</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/protocol/versions</code></li>
                    </ul>
                </div>

                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-2">Authentication</h4>
                    <p class="text-xs text-slate-400 mb-3">Public &mdash; returns session tokens</p>
                    <ul class="text-sm text-slate-600 space-y-1">
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/challenge</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/did</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/api-key</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/validate</code></li>
                    </ul>
                </div>

                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-2">Agent Operations</h4>
                    <p class="text-xs text-slate-400 mb-3">Agent-authenticated</p>
                    <ul class="text-sm text-slate-600 space-y-1">
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/agents/{did}/payments</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/escrow/create</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/agents/{did}/messages</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/agents/{did}/reputation</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Related -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Related Capabilities</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <a href="{{ route('features.show', 'ai-framework') }}" class="card-feature !p-6 hover:border-purple-300 transition-colors group">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-purple-700">AI Framework</h3>
                    <p class="text-sm text-slate-500">6 specialized agents, 24 MCP tools, ML anomaly detection, and Temporal workflow orchestration.</p>
                </a>
                <a href="{{ route('features.show', 'machine-payments') }}" class="card-feature !p-6 hover:border-orange-300 transition-colors group">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-orange-700">Machine Payments (MPP)</h3>
                    <p class="text-sm text-slate-500">Multi-rail HTTP 402 payments. AP2 mandates bridge directly to MPP and x402 payment rails.</p>
                </a>
                <a href="{{ route('features.show', 'virtuals-protocol') }}" class="card-feature !p-6 hover:border-violet-300 transition-colors group">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-violet-700">AI Agent Commerce</h3>
                    <p class="text-sm text-slate-500">Virtuals Protocol integration with TrustCert identity, spending limits, and Pimlico enforcement.</p>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Give Your Agents a Bank Account</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Register an agent, fund its wallet, and let it transact autonomously &mdash; with escrow protection, reputation tracking, and compliance built in.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Register an Agent
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    Protocol Documentation
                </a>
            </div>
        </div>
    </section>

@endsection
