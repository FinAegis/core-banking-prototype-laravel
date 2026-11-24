@extends('layouts.public')

@section('title', 'x402 Protocol for HTTP Micropayments | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'x402 Protocol for HTTP Micropayments',
        'description' => 'The x402 module lets an API charge for each request with an HTTP 402 response. Clients and AI agents pay in USDC, mainly on Base, and spending limits keep agent budgets under control.',
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



@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="inline-flex items-center px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-sm text-slate-300 mb-6">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full mr-2"></span>
                    v7.18.1
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">x402 Protocol</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto mb-8">
                    x402 lets an API charge a small amount for each request. The server answers with HTTP status 402 and the price. The client pays in USDC and sends the request again. You do not need subscriptions or separate billing.
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
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- How It Works — Protocol Flow -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 text-center mb-4">How It Works</h2>
            <p class="text-lg text-slate-500 text-center mb-12 max-w-2xl mx-auto">A paid request takes three steps over plain HTTP. The payment details travel in the same requests and responses.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                <!-- Step 1: Request -->
                <div class="relative">
                    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-blue-600 uppercase tracking-wider">Step 1</span>
                                <h3 class="text-lg font-bold text-slate-900">Request Resource</h3>
                            </div>
                        </div>
                        <p class="text-slate-500 text-sm mb-4">The client sends a normal HTTP request to a paid endpoint.</p>
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
                            <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-amber-600 uppercase tracking-wider">Step 2</span>
                                <h3 class="text-lg font-bold text-slate-900">402 + Payment Info</h3>
                            </div>
                        </div>
                        <p class="text-slate-500 text-sm mb-4">The server answers with status 402. The response headers say what to pay and where.</p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-amber-400">HTTP/1.1 402 Payment Required</div>
                            <div>PAYMENT-REQUIRED: eyJhY2NlcHRzIjpb...</div>
                            <div>X-Payment-Protocol: x402</div>
                            <div>X-Payment-Version: 2</div>
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
                            <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-green-600 uppercase tracking-wider">Step 3</span>
                                <h3 class="text-lg font-bold text-slate-900">Pay & Receive Data</h3>
                            </div>
                        </div>
                        <p class="text-slate-500 text-sm mb-4">The client signs the payment and sends the request again with the proof. The server settles the payment and returns the data.</p>
                        <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                            <div class="text-green-400">HTTP/1.1 200 OK</div>
                            <div>PAYMENT-RESPONSE: eyJzdWNjZXNzIjp0cnVl...</div>
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Core Capabilities</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The module covers the server side, the client side and the controls for AI agents.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Payment Gate Middleware -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-emerald-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Payment Gate Middleware</h3>
                    <p class="text-slate-500 mb-4">
                        A Laravel middleware puts a price on a route. It answers unpaid requests with a 402 response. It checks that the payment is settled before it returns any data.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Price set for each route
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            402 responses built for you
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Payment checked before the data is sent
                        </li>
                    </ul>
                </div>

                <!-- AI Agent Payments -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">AI Agent Payments</h3>
                    <p class="text-slate-500 mb-4">
                        An AI agent can pay for API access on its own through a Model Context Protocol tool. Budget limits cap what each agent can spend. Payments above a set amount wait for approval.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Model Context Protocol tool for agents
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            A budget for each agent
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approval needed above a set amount
                        </li>
                    </ul>
                </div>

                <!-- Spending Limits -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-amber-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Spending Limits</h3>
                    <p class="text-slate-500 mb-4">
                        You can cap spending per day, per transaction and per agent. A payment above the approval amount goes to a person for review.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Daily and per-transaction limits
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            A spending budget for each agent
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approval amounts you can change
                        </li>
                    </ul>
                </div>

                <!-- Multi-Network Settlement -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Multi-Network Settlement</h3>
                    <p class="text-slate-500 mb-4">
                        Payments settle in USDC on Base, Ethereum, Avalanche or Solana. Base is the default because its fees are low. Each endpoint can use its own network.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Base by default, for low fees
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Ethereum, Avalanche and Solana also work
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            A network chosen for each endpoint
                        </li>
                    </ul>
                </div>

                <!-- Facilitator Integration -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Facilitator Integration</h3>
                    <p class="text-slate-500 mb-4">
                        A facilitator is the service that checks and settles each payment. You can use the public x402.org facilitator. You can also run your own facilitator if you want full control.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Your own facilitator or x402.org
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
                            Settlement confirmed for you
                        </li>
                    </ul>
                </div>

                <!-- GraphQL & REST APIs -->
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-rose-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">GraphQL & REST APIs</h3>
                    <p class="text-slate-500 mb-4">
                        You manage paid endpoints and spending limits through the API. You can also read the payment history and payment statistics. Both REST and GraphQL are available.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            More than 15 REST endpoints
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            A GraphQL schema
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Payment history and statistics
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Built For</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                x402 suits any service that wants to charge per request. These are the most common cases.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- API Monetization -->
                <div class="card-feature !p-8 border-l-4 border-emerald-500">
                    <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">API Monetization</h3>
                    <p class="text-slate-500">
                        You can charge for each request to a data, analytics or AI endpoint. Clients pay inside the HTTP request, so you do not run subscriptions or a billing system.
                    </p>
                </div>

                <!-- Autonomous AI Agents -->
                <div class="card-feature !p-8 border-l-4 border-blue-500">
                    <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Autonomous AI Agents</h3>
                    <p class="text-slate-500">
                        AI agents can pay for API access without a person in the loop. The Model Context Protocol tool keeps each agent within its spending limit.
                    </p>
                </div>

                <!-- Premium Data Feeds -->
                <div class="card-feature !p-8 border-l-4 border-purple-500">
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-slate-900">Premium Data Feeds</h3>
                    <p class="text-slate-500">
                        You can sell market data, research reports or compliance feeds per request. Buyers pay in USDC only for the data they fetch.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Integration -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Developer Integration</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                On the server, you add the x402 middleware to a Laravel route. On the client, a few lines of JavaScript handle the 402 response.
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Server-side: Laravel -->
                <div>
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-bold mr-3">S</span>
                        Laravel Middleware on the Server
                    </h3>
                    <div class="bg-gray-900 rounded-xl p-6 font-mono text-sm text-gray-300 overflow-x-auto">
                        <pre><code><span class="text-gray-500">// routes/api.php</span>

<span class="text-gray-500">// Put the payment gate on the routes that may charge</span>
Route::middleware([<span class="text-emerald-400">'x402.payment'</span>])->group(<span class="text-purple-400">function</span> () {
    Route::get(<span class="text-emerald-400">'/premium/market-data'</span>, [MarketDataController::class, <span class="text-emerald-400">'index'</span>]);
    Route::get(<span class="text-emerald-400">'/premium/analytics'</span>, [AnalyticsController::class, <span class="text-emerald-400">'show'</span>]);
});

<span class="text-gray-500">// Set the price of each route through the API</span>
<span class="text-gray-500">// POST /api/v1/x402/endpoints</span>
<span class="text-gray-500">// {"method": "GET", "path": "api/premium/market-data", "price": "0.10"}</span></code></pre>
                    </div>
                </div>

                <!-- Client-side: JavaScript -->
                <div>
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 rounded-lg text-sm font-bold mr-3">C</span>
                        Handling a 402 Response in the Client
                    </h3>
                    <div class="bg-gray-900 rounded-xl p-6 font-mono text-sm text-gray-300 overflow-x-auto">
                        <pre><code><span class="text-purple-400">const</span> res = <span class="text-purple-400">await</span> fetch(<span class="text-emerald-400">'/v2/premium/data'</span>);

<span class="text-purple-400">if</span> (res.status === <span class="text-amber-400">402</span>) {
  <span class="text-gray-500">// Read the payment requirements (base64-encoded JSON)</span>
  <span class="text-purple-400">const</span> required = JSON.parse(atob(res.headers.get(<span class="text-emerald-400">'PAYMENT-REQUIRED'</span>)));

  <span class="text-gray-500">// Sign a USDC payment authorisation with the wallet</span>
  <span class="text-purple-400">const</span> signature = <span class="text-purple-400">await</span> signPayment(required.accepts[<span class="text-amber-400">0</span>]);

  <span class="text-gray-500">// Send the request again with the signed payment</span>
  <span class="text-purple-400">const</span> data = <span class="text-purple-400">await</span> fetch(<span class="text-emerald-400">'/v2/premium/data'</span>, {
    headers: { <span class="text-emerald-400">'PAYMENT-SIGNATURE'</span>: signature }
  });
}</code></pre>
                    </div>
                </div>
            </div>

            <!-- Quick Start Steps -->
            <div class="mt-12 max-w-3xl mx-auto">
                <h3 class="text-lg font-bold text-slate-900 mb-6 text-center">Quick Start</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">1</span>
                        <div>
                            <h4 class="font-semibold text-slate-900 text-sm">Configure Facilitator</h4>
                            <p class="text-sm text-gray-500">Set <code class="text-xs bg-gray-100 px-1 rounded">X402_FACILITATOR_URL</code> and <code class="text-xs bg-gray-100 px-1 rounded">X402_RECEIVER_ADDRESS</code> in your <code class="text-xs bg-gray-100 px-1 rounded">.env</code></p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">2</span>
                        <div>
                            <h4 class="font-semibold text-slate-900 text-sm">Add Middleware</h4>
                            <p class="text-sm text-gray-500">Attach the <code class="text-xs bg-gray-100 px-1 rounded">x402.payment</code> middleware to each route that may charge. Then set its price through the x402 API.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 bg-emerald-600 text-white rounded-full flex items-center justify-center font-bold text-sm mr-3">3</span>
                        <div>
                            <h4 class="font-semibold text-slate-900 text-sm">Receive Payments</h4>
                            <p class="text-sm text-gray-500">Clients get a 402 response with the price. Their x402 client pays in USDC and repeats the request.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Supported Networks -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 text-center mb-4">Supported Networks</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                You can settle on the network that suits your service. Base has the lowest fees for frequent small payments.
            </p>

            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-slate-900">Network</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-slate-900">Chain ID</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-slate-900">Asset</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-slate-900">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>Base
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 font-mono">eip155:8453</td>
                                <td class="px-6 py-4 text-sm text-slate-500">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full font-medium">Primary</span></td>
                            </tr>
                            <tr class="bg-gray-50/50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Ethereum
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 font-mono">eip155:1</td>
                                <td class="px-6 py-4 text-sm text-slate-500">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Supported</span></td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Avalanche
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 font-mono">eip155:43114</td>
                                <td class="px-6 py-4 text-sm text-slate-500">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Supported</span></td>
                            </tr>
                            <tr class="bg-gray-50/50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-900">
                                    <span class="inline-block w-2 h-2 bg-purple-500 rounded-full mr-2"></span>Solana
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 font-mono">solana:mainnet</td>
                                <td class="px-6 py-4 text-sm text-slate-500">USDC</td>
                                <td class="px-6 py-4"><span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">Supported</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-500 text-center mt-4">You set the network for each endpoint in the middleware or through the management API.</p>
            </div>
        </div>
    </section>

    <!-- Error Handling -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 text-center mb-4">Error Handling</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                x402 errors follow the Problem Details for HTTP APIs standard (RFC 9457). Each error has a type for programs, a detail for people and the 402 status code.
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Payment Verification Failed -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4">Payment Verification Failed</h3>
                    <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                        <div class="text-red-400">HTTP/1.1 402 Payment Required</div>
                        <div>Content-Type: application/problem+json</div>
                        <div class="mt-2">{</div>
                        <div class="pl-4">"type": "verification-failed",</div>
                        <div class="pl-4">"title": "Payment signature invalid",</div>
                        <div class="pl-4">"detail": "EIP-712 signature recovery failed for network eip155:8453",</div>
                        <div class="pl-4">"status": 402</div>
                        <div>}</div>
                    </div>
                </div>

                <!-- Spending Limit Exceeded -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 mb-4">Spending Limit Exceeded</h3>
                    <div class="bg-gray-900 rounded-lg p-4 font-mono text-xs text-gray-300 overflow-x-auto">
                        <div class="text-red-400">HTTP/1.1 402 Payment Required</div>
                        <div>Content-Type: application/problem+json</div>
                        <div class="mt-2">{</div>
                        <div class="pl-4">"type": "spending-limit-exceeded",</div>
                        <div class="pl-4">"title": "Agent daily limit reached",</div>
                        <div class="pl-4">"detail": "Agent 'data-bot' spent $4.85 of $5.00 daily budget",</div>
                        <div class="pl-4">"status": 402</div>
                        <div>}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Charge for Your API per Request</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Add the x402 middleware to your endpoints, and each request can carry its own payment. You do not need a billing system.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('developers.show', 'api-docs') }}#x402" class="btn-primary px-8 py-4 text-lg">
                    Read the Docs
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    Developer Portal
                </a>
            </div>
            <p class="mt-8 text-slate-500 text-sm">
                Related: <a href="{{ url('/features/machine-payments') }}" class="underline hover:text-white transition text-slate-400">Machine Payments</a> &middot;
                <a href="{{ url('/features/zelta-cli') }}" class="underline hover:text-white transition text-slate-400">Zelta CLI</a> &middot;
                <a href="{{ url('/features/visa-cli') }}" class="underline hover:text-white transition text-slate-400">Card-Payment Command Line Tool</a>
            </p>
        </div>
    </section>

@endsection
