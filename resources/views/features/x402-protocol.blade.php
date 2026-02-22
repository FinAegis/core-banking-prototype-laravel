@extends('layouts.public')

@section('title', 'x402 Protocol - HTTP-Native Micropayments | FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'x402 Protocol - HTTP-Native Micropayments',
        'description' => 'Monetize APIs with HTTP 402 responses. USDC payments on Base with instant settlement, AI agent support, and spending limits.',
        'keywords' => 'x402, HTTP 402, micropayments, USDC, Base, API monetization, AI agent payments, spending limits',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'x402 Protocol', 'url' => url('/features/x402-protocol')]
    ]" />
@endsection

@push('styles')
<style>
    .x402-gradient { background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%); }
    .feature-card {
        transition: all 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')

    <!-- Hero -->
    <section class="x402-gradient text-white pt-24 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="inline-flex items-center px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-sm mb-6">
                    <span class="w-2 h-2 bg-white rounded-full mr-2"></span>
                    v5.2.0 &middot; Production Ready
                </div>
                <h1 class="text-5xl font-bold mb-6">x402 Protocol</h1>
                <p class="text-xl text-emerald-100 max-w-3xl mx-auto mb-8">
                    HTTP-native micropayments for APIs. Return a 402, get paid in USDC, deliver data. No subscriptions, no API keys to manage &mdash; just pay-per-request built into HTTP.
                </p>
                <div class="flex flex-wrap gap-4 justify-center">
                    <a href="{{ route('developers.show', 'api-docs') }}#x402" class="bg-white text-emerald-700 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-emerald-50 transition">
                        API Reference
                    </a>
                    <a href="/api/documentation#/X402" target="_blank" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-emerald-700 transition">
                        Swagger UI
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works — Protocol Flow -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-gray-900 text-center mb-4">How It Works</h2>
            <p class="text-lg text-gray-600 text-center mb-12 max-w-2xl mx-auto">Three HTTP round-trips. No out-of-band negotiation, no payment SDKs, no webhooks to configure.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                <!-- Step 1: Request -->
                <div class="relative">
                    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-blue-600 uppercase tracking-wider">Step 1</span>
                                <h3 class="text-lg font-bold text-gray-900">Request Resource</h3>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Client sends a standard HTTP request to a monetized endpoint.</p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-blue-400">GET /v2/premium/data HTTP/1.1</div>
                            <div>Host: api.example.com</div>
                            <div>Authorization: Bearer &lt;token&gt;</div>
                        </div>
                    </div>
                    <div class="hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>

                <!-- Step 2: 402 Response -->
                <div class="relative">
                    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-amber-600 uppercase tracking-wider">Step 2</span>
                                <h3 class="text-lg font-bold text-gray-900">402 + Payment Info</h3>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Server responds with payment requirements in standard HTTP headers.</p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-amber-400">HTTP/1.1 402 Payment Required</div>
                            <div>X-Payment-Amount: 0.01</div>
                            <div>X-Payment-Asset: USDC</div>
                            <div>X-Payment-Network: eip155:8453</div>
                            <div>X-Payment-Receiver: 0xabc...def</div>
                        </div>
                    </div>
                    <div class="hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </div>

                <!-- Step 3: Pay & Receive -->
                <div>
                    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-green-600 uppercase tracking-wider">Step 3</span>
                                <h3 class="text-lg font-bold text-gray-900">Pay & Receive Data</h3>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">Client signs payment, retries with proof. Server settles and delivers.</p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-green-400">HTTP/1.1 200 OK</div>
                            <div>X-Payment-Settled: true</div>
                            <div>X-Payment-TxHash: 0x7f3...</div>
                            <div class="text-gray-500 mt-1">Content-Type: application/json</div>
                            <div class="text-gray-500">{"data": { ... }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Capabilities -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-4">Core Capabilities</h2>
            <p class="text-lg text-gray-600 text-center max-w-2xl mx-auto mb-12">
                Everything you need to monetize APIs with HTTP-native payments, from middleware to AI agent integration.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Payment Gate Middleware -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Payment Gate Middleware</h3>
                    <p class="text-gray-600 mb-4">
                        Protect any Laravel route with a single middleware declaration. Configurable pricing per endpoint with automatic 402 response generation and settlement verification before data delivery.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Route-level price configuration
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automatic 402 response generation
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Settlement verification before delivery
                        </li>
                    </ul>
                </div>

                <!-- AI Agent Payments -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">AI Agent Payments</h3>
                    <p class="text-gray-600 mb-4">
                        Autonomous AI agents can pay for API access using the MCP tool integration. Budget limits and approval thresholds keep spending under control without human intervention.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            MCP tool for Claude/GPT agents
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Per-agent budget management
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approval required above threshold
                        </li>
                    </ul>
                </div>

                <!-- Spending Limits -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-amber-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Spending Limits</h3>
                    <p class="text-gray-600 mb-4">
                        Granular spending controls with daily caps, per-transaction limits, and per-agent budgets. Configurable approval thresholds trigger manual review above set amounts.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Daily and per-transaction caps
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Per-agent spending budgets
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Configurable approval thresholds
                        </li>
                    </ul>
                </div>

                <!-- Multi-Network Settlement -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Multi-Network Settlement</h3>
                    <p class="text-gray-600 mb-4">
                        Settle payments on Base (primary, low fees), Ethereum, or Avalanche using USDC. Each endpoint can be independently configured with its preferred network.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Base as primary (low fees)
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Ethereum and Avalanche supported
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Per-endpoint network configuration
                        </li>
                    </ul>
                </div>

                <!-- Facilitator Integration -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Facilitator Integration</h3>
                    <p class="text-gray-600 mb-4">
                        Leverage the x402.org facilitator for trustless payment verification and settlement, or run your own self-hosted facilitator for full control over the payment pipeline.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Self-hosted or x402.org facilitator
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Payment signature verification
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automatic settlement confirmation
                        </li>
                    </ul>
                </div>

                <!-- GraphQL & REST APIs -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-14 h-14 bg-rose-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">GraphQL & REST APIs</h3>
                    <p class="text-gray-600 mb-4">
                        Full CRUD for monetized endpoints, payment history, and spending reports. Choose REST or GraphQL for managing your x402-protected resources programmatically.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            15+ REST endpoints
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Complete GraphQL schema
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Real-time payment webhooks
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-4">Built For</h2>
            <p class="text-lg text-gray-600 text-center max-w-2xl mx-auto mb-12">
                From API monetization to autonomous AI agents, x402 handles payment at the protocol level so you can focus on delivering value.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- API Monetization -->
                <div class="bg-white rounded-xl p-8 shadow-md border-l-4 border-emerald-500">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">API Monetization</h3>
                    <p class="text-gray-600">
                        Charge per-request for premium data, analytics, or AI inference endpoints. No subscription management, no billing integration &mdash; clients pay at the HTTP layer and get instant access.
                    </p>
                </div>

                <!-- Autonomous AI Agents -->
                <div class="bg-white rounded-xl p-8 shadow-md border-l-4 border-blue-500">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Autonomous AI Agents</h3>
                    <p class="text-gray-600">
                        Let Claude, GPT, and other AI agents pay for API access autonomously. MCP tool integration with budget controls ensures agents operate within defined spending limits.
                    </p>
                </div>

                <!-- Premium Data Feeds -->
                <div class="bg-white rounded-xl p-8 shadow-md border-l-4 border-purple-500">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Premium Data Feeds</h3>
                    <p class="text-gray-600">
                        Real-time market data, research reports, and compliance feeds with instant USDC settlement on Base. Consumers pay only for the data they actually consume.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Integration -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-4">Developer Integration</h2>
            <p class="text-lg text-gray-600 text-center max-w-2xl mx-auto mb-12">
                Protect any Laravel route with x402 middleware in one line. Handle 402 responses client-side with a few lines of JavaScript.
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Server-side: Laravel -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-bold mr-3">S</span>
                        Server — Laravel Middleware
                    </h3>
                    <div class="bg-gray-900 rounded-xl p-6 font-mono text-sm text-gray-300 overflow-x-auto">
                        <pre><code><span class="text-gray-500">// routes/api.php</span>

<span class="text-gray-500">// Simple: protect a route for $0.10</span>
Route::get(<span class="text-emerald-400">'/premium/market-data'</span>, [MarketDataController::class, <span class="text-emerald-400">'index'</span>])
    ->middleware(<span class="text-emerald-400">'x402:100000'</span>); <span class="text-gray-500">// micro-USDC</span>

<span class="text-gray-500">// Full config: price, network, asset</span>
Route::get(<span class="text-emerald-400">'/premium/analytics'</span>, [AnalyticsController::class, <span class="text-emerald-400">'show'</span>])
    ->middleware(<span class="text-emerald-400">'x402:500000,eip155:8453,USDC'</span>);

<span class="text-gray-500">// Group multiple endpoints</span>
Route::middleware([<span class="text-emerald-400">'x402:250000'</span>])->group(<span class="text-purple-400">function</span> () {
    Route::get(<span class="text-emerald-400">'/data/trades'</span>, [TradeController::class, <span class="text-emerald-400">'index'</span>]);
    Route::get(<span class="text-emerald-400">'/data/orderbook'</span>, [OrderBookController::class, <span class="text-emerald-400">'show'</span>]);
});</code></pre>
                    </div>
                </div>

                <!-- Client-side: JavaScript -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 rounded-lg text-sm font-bold mr-3">C</span>
                        Client — Handle 402 Response
                    </h3>
                    <div class="bg-gray-900 rounded-xl p-6 font-mono text-sm text-gray-300 overflow-x-auto">
                        <pre><code><span class="text-purple-400">const</span> res = <span class="text-purple-400">await</span> fetch(<span class="text-emerald-400">'/v2/premium/data'</span>);

<span class="text-purple-400">if</span> (res.status === <span class="text-amber-400">402</span>) {
  <span class="text-gray-500">// Read payment requirements from headers</span>
  <span class="text-purple-400">const</span> amount  = res.headers.get(<span class="text-emerald-400">'X-Payment-Amount'</span>);
  <span class="text-purple-400">const</span> network = res.headers.get(<span class="text-emerald-400">'X-Payment-Network'</span>);
  <span class="text-purple-400">const</span> receiver = res.headers.get(<span class="text-emerald-400">'X-Payment-Receiver'</span>);

  <span class="text-gray-500">// Sign and send USDC payment</span>
  <span class="text-purple-400">const</span> txHash = <span class="text-purple-400">await</span> sendUSDC(receiver, amount, network);

  <span class="text-gray-500">// Retry with payment proof</span>
  <span class="text-purple-400">const</span> data = <span class="text-purple-400">await</span> fetch(<span class="text-emerald-400">'/v2/premium/data'</span>, {
    headers: { <span class="text-emerald-400">'X-Payment-Proof'</span>: txHash }
  });
}</code></pre>
                    </div>
                </div>
            </div>

            <!-- Quick Start Steps -->
            <div class="mt-12 max-w-3xl mx-auto">
                <h3 class="text-lg font-bold text-gray-900 mb-6 text-center">Quick Start</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">1</span>
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">Configure Facilitator</h4>
                            <p class="text-sm text-gray-500">Set <code class="text-xs bg-gray-100 px-1 rounded">X402_FACILITATOR_URL</code> and <code class="text-xs bg-gray-100 px-1 rounded">X402_RECEIVER_ADDRESS</code> in your <code class="text-xs bg-gray-100 px-1 rounded">.env</code></p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">2</span>
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">Add Middleware</h4>
                            <p class="text-sm text-gray-500">Attach <code class="text-xs bg-gray-100 px-1 rounded">x402:price</code> middleware to any route you want to monetize</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">3</span>
                        <div>
                            <h4 class="font-semibold text-gray-900 text-sm">Start Earning</h4>
                            <p class="text-sm text-gray-500">Clients receive 402 responses with payment info and pay in USDC automatically</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Supported Networks -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-gray-900 text-center mb-4">Supported Networks</h2>
            <p class="text-lg text-gray-600 text-center max-w-2xl mx-auto mb-12">
                Settle payments on the network that best fits your use case. Base offers the lowest fees for high-frequency micropayments.
            </p>

            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Network</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Chain ID</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Asset</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>Base
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 font-mono">eip155:8453</td>
                                <td class="px-6 py-4 text-sm text-gray-600">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">Primary</span></td>
                            </tr>
                            <tr class="bg-gray-50/50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Ethereum
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 font-mono">eip155:1</td>
                                <td class="px-6 py-4 text-sm text-gray-600">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Supported</span></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Avalanche
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 font-mono">eip155:43114</td>
                                <td class="px-6 py-4 text-sm text-gray-600">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Supported</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-500 text-center mt-4">Networks are configurable per-endpoint via middleware parameters or the management API.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 x402-gradient text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6">Start Monetizing Your APIs</h2>
            <p class="text-xl text-emerald-100 mb-8">Add x402 payment gates to your endpoints and get paid per request. No subscriptions, no billing integrations &mdash; just HTTP.</p>
            <div class="flex flex-wrap gap-4 justify-center">
                <a href="{{ route('developers.show', 'api-docs') }}#x402" class="bg-white text-emerald-700 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-emerald-50 transition">
                    Read the Docs
                </a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-emerald-700 transition">
                    Developer Portal
                </a>
            </div>
            <p class="mt-8 text-emerald-200 text-sm">
                Building a mobile wallet? See <a href="{{ url('/features/mobile-payments') }}" class="underline hover:text-white transition">Mobile Payments</a> &rarr;
            </p>
        </div>
    </section>

@endsection
