@extends('layouts.public')

@section('title', 'Agent Protocol for Autonomous Agent Commerce | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Agent Protocol for Autonomous Agent Commerce',
        'description' => 'The Agent Protocol module lets AI agents pay and trade with each other. It implements Google\'s Agent2Agent protocol and the Agent Payments Protocol, with decentralised identifiers, escrow, reputation scores, payment mandates and verifiable digital credentials.',
        'keywords' => 'agent protocol, Agent Payments Protocol, Agent2Agent, decentralised identifiers, escrow, reputation, mandates, verifiable digital credentials, agent commerce, autonomous payments, ' . config('brand.name', 'Zelta'),
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
                    v7.18.1 &middot; Autonomous Agent Commerce
                </div>
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Agent Protocol
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    The Agent Protocol module lets AI agents trade with each other on their own. Each agent gets a decentralised identifier and can send messages to other agents. Payments can go through escrow with dispute resolution. Reputation scores and payment mandates from the Agent Payments Protocol control what each agent may do.
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
                    <h3 class="text-lg font-bold mb-2">Decentralised Identity</h3>
                    <p class="text-sm text-slate-500">Each agent has a decentralised identifier. The module registers agents, signs them in and checks their identity with cryptography.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Agent-to-Agent Messaging</h3>
                    <p class="text-sm text-slate-500">Agents send structured messages to each other over Google's Agent2Agent protocol. Messages have priorities and delivery receipts, and agents agree on a protocol version first.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Escrow</h3>
                    <p class="text-sm text-slate-500">Escrow holds a payment between several parties until agreed milestones are met. It handles disputes and expires on its own if nobody acts.</p>
                </div>

                <div class="card-feature !p-6 text-center">
                    <div class="w-14 h-14 bg-amber-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Reputation</h3>
                    <p class="text-sm text-slate-500">Each agent has a trust score that rises with good results and falls with penalties or inactivity. Some actions need a minimum score, and a leaderboard shows the top agents.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Agent Lifecycle -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Agent Lifecycle</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                Every agent goes through the same five steps before it can trade on its own. At each step it gets only the permissions it needs.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-cyan-700">1</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Register</h4>
                    <p class="text-xs text-slate-500">The agent gets an identifier and declares what it can do.</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-blue-700">2</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Verify</h4>
                    <p class="text-xs text-slate-500">The agent passes identity checks with documents.</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-indigo-700">3</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Fund</h4>
                    <p class="text-xs text-slate-500">The agent gets a wallet and a first deposit.</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-purple-700">4</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Connect</h4>
                    <p class="text-xs text-slate-500">Agents agree on a protocol and match what they can do.</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg font-bold text-emerald-700">5</span>
                    </div>
                    <h4 class="font-semibold text-sm mb-1">Transact</h4>
                    <p class="text-xs text-slate-500">The agent pays, uses escrow and sends messages.</p>
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
                        Agents can sign in in several ways. There are 31 permission scopes, and each one limits an agent to the actions it was allowed.
                    </p>

                    <div class="space-y-4">
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">Identifier Challenge and Response</h4>
                            <p class="text-sm text-slate-500">The agent signs a challenge with the key behind its decentralised identifier. This proves who it is without sharing a secret.</p>
                        </div>
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">API Key Authentication</h4>
                            <p class="text-sm text-slate-500">Programs can sign in with a 64-character API key. Each key has its own permissions, and you can rotate or revoke it.</p>
                        </div>
                        <div class="card-feature !p-5">
                            <h4 class="font-bold text-slate-900 mb-2">Session Management</h4>
                            <p class="text-sm text-slate-500">A session lasts 24 hours, and a sign-in challenge expires after 5 minutes. You can list sessions and end one or all of them.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-4">Permission Scopes</h3>
                    <div class="card-feature !p-6">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['payments:create', 'payments:read', 'wallet:read', 'wallet:transfer', 'escrow:create', 'escrow:release', 'messages:send', 'messages:read', 'reputation:read', 'reputation:review', 'compliance:read', 'admin:manage'] as $scope)
                                <div class="flex items-center text-sm">
                                    <code class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-xs font-mono">{{ $scope }}</code>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-4">There are 31 scopes in total. This list shows 12 of the most common ones. The full list is at <code class="text-xs">/api/agent-protocol/auth/scopes</code>.</p>
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
                                Message encryption with AES-256-GCM
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Fraud checks that score velocity, amount, reputation, patterns, location and time
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Anti-money laundering screening against a list of high-risk countries
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
                                <p class="text-slate-600 text-sm">An escrow can hold from $10 to $1 million. You can set the hold period. An escrow expires on its own after 30 days by default.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-cyan-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Dispute Resolution</h4>
                                <p class="text-slate-600 text-sm">Either party can open a dispute and submit evidence. Disputes under $10,000 are settled by a vote. Larger disputes go to arbitration.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-cyan-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Event-Sourced Lifecycle</h4>
                                <p class="text-slate-600 text-sm">Every change to an escrow is stored as an event. The audit trail shows when it was created, funded, held, released, expired, cancelled, disputed and resolved.</p>
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
                                <p class="text-slate-600 text-sm">Agent wallets support ten currencies. Fees range from 0.5% to 2.5% depending on the type of transfer. You can fund a wallet from a main account or from outside.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Transaction Limits</h4>
                                <p class="text-slate-600 text-sm">By default an agent can spend $10,000 a day and $5,000 in one payment. You can change both limits. A payment above a limit is escalated for review.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg>
                            <div>
                                <h4 class="font-semibold mb-1">Fee Structure</h4>
                                <p class="text-slate-600 text-sm">The standard fee is 2.5%, with a minimum of $0.50 and a maximum of $100. Each fee calculation is stored as an event.</p>
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Payment Mandates</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                The Agent Payments Protocol from Google defines how a person authorises an agent to spend. A mandate is that authorisation in a fixed format. The module supports the three mandate types below.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card-feature !p-8 border-t-4 border-cyan-500">
                    <h3 class="text-xl font-bold mb-3 text-cyan-900">Cart Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">This mandate is for shopping while the person is present. The agent fills a cart and the person approves the total. The payment then goes through.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            Uses the PaymentRequest format of the World Wide Web Consortium
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            Itemised cart with the merchant's identifier
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full mr-2"></span>
                            The person must approve it
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8 border-t-4 border-purple-500">
                    <h3 class="text-xl font-bold mb-3 text-purple-900">Intent Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">This mandate is for spending on the agent's own. The agent gets a budget and a plain-language description of the goal. It must stay within the rules it was given.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Plain-language description of the goal
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Budget limit with rules
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-2"></span>
                            Linked to the identifiers of the person and the agent
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8 border-t-4 border-emerald-500">
                    <h3 class="text-xl font-bold mb-3 text-emerald-900">Payment Mandate</h3>
                    <p class="text-slate-500 mb-4 text-sm">This mandate is for a direct payment from one agent to another. It names the payee and a fixed amount, and it can state preferred payment methods.</p>
                    <ul class="text-sm text-slate-600 space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            Payee identifier and fixed amount
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            Pays through x402 or the Machine Payments Protocol
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span>
                            Authorised with a verifiable digital credential
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
                        Each agent has a trust score that changes after every interaction. Successful payments raise it. Lost disputes and failures lower it.
                    </p>
                    <div class="card-feature !p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Initial Score</span>
                                <span class="font-bold text-slate-900">50/100</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Score decays after inactivity of</span>
                                <span class="font-bold text-slate-900">30 days</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Score Components</span>
                                <span class="font-bold text-slate-900">Weighted</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-700">Public Leaderboard</span>
                                <span class="font-bold text-emerald-600">Available</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Identity checks for agents</h2>
                    <p class="text-slate-500 mb-6">
                        There are three levels of identity checks. Each higher level allows larger payments.
                    </p>
                    <div class="space-y-3">
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Basic</h4>
                                <p class="text-xs text-slate-500">A government identity document</p>
                            </div>
                            <span class="text-sm font-semibold text-blue-600 bg-blue-50 px-3 py-1 rounded-full">Limited</span>
                        </div>
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Enhanced</h4>
                                <p class="text-xs text-slate-500">Adds proof of address and a bank statement</p>
                            </div>
                            <span class="text-sm font-semibold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Higher Limits</span>
                        </div>
                        <div class="card-feature !p-5 flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-900">Full</h4>
                                <p class="text-xs text-slate-500">Adds business registration and financial statements</p>
                            </div>
                            <span class="text-sm font-semibold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full">No daily limit</span>
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
                Every agent action is stored as an event that cannot be changed. The module has 10 aggregates and more than 60 domain events. Together they give a full audit trail that compliance teams can use.
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
                    <p class="text-xs text-slate-400 mb-3">Public, no sign-in needed</p>
                    <ul class="text-sm text-slate-600 space-y-1">
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/.well-known/ap2-configuration</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/agents/discover</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/agents/{did}</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">GET</code> <code class="text-xs">/protocol/versions</code></li>
                    </ul>
                </div>

                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-2">Authentication</h4>
                    <p class="text-xs text-slate-400 mb-3">Public, returns session tokens</p>
                    <ul class="text-sm text-slate-600 space-y-1">
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/challenge</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/did</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/api-key</code></li>
                        <li><code class="text-xs bg-slate-100 px-1 rounded">POST</code> <code class="text-xs">/auth/validate</code></li>
                    </ul>
                </div>

                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-2">Agent Operations</h4>
                    <p class="text-xs text-slate-400 mb-3">The agent must be signed in</p>
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
                    <p class="text-sm text-slate-500">The AI framework has 6 specialised agents and {{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} Model Context Protocol tools with OAuth permissions. It also detects unusual activity with machine learning and runs workflows.</p>
                </a>
                <a href="{{ route('features.show', 'machine-payments') }}" class="card-feature !p-6 hover:border-orange-300 transition-colors group">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-orange-700">Machine Payments</h3>
                    <p class="text-sm text-slate-500">The Machine Payments Protocol handles HTTP 402 payments over several rails. Payment mandates can pay through it or through x402.</p>
                </a>
                <a href="{{ route('features.show', 'virtuals-protocol') }}" class="card-feature !p-6 hover:border-violet-300 transition-colors group">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-violet-700">AI Agent Commerce</h3>
                    <p class="text-sm text-slate-500">An integration adapter connects agents from the Virtuals Protocol. It checks their identity with TrustCert and enforces spending limits through Pimlico. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Give Your Agent a Wallet</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Register an agent and fund its wallet. The agent can then pay on its own. Escrow, reputation scores and compliance tools keep its actions in check.
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
