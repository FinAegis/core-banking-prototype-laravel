@extends('layouts.public')

@section('title', 'Interledger Protocol - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Interledger Protocol',
        'description' => 'A reference implementation of the Interledger Protocol. It simulates a connector with STREAM connections, models Open Payments incoming and outgoing payments and gives cross-currency quotes. It runs in demo mode and connects to no live network.',
        'keywords' => 'Interledger, Interledger Protocol, Open Payments, Grant Negotiation and Authorization Protocol, STREAM protocol, cross-currency, payment pointer, cross-network payments, reference implementation',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Interledger Protocol', 'url' => url('/features/interledger')]
    ]" />
@endsection

@push('styles')
<style>
    .protocol-card {
        transition: all 0.3s ease;
    }
    .protocol-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center bg-purple-500/10 border border-purple-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-purple-400 text-sm font-medium">Reference implementation</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Interledger Protocol</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    The Interledger Protocol moves value between different payment networks. This module simulates a connector that uses the STREAM protocol. It also models Open Payments, gives cross-currency quotes and turns payment pointers into Interledger addresses. It is a reference implementation for development and testing. It runs in demo mode, is switched off on the production site and connects to no live network.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#open-payments" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Open Payments
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-purple-500/20 to-transparent"></div>
    </section>

    <!-- ILP Connector -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Simulated Connector</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                A connector passes Interledger packets from one ledger to the next. This module simulates one for development and testing. It keeps STREAM connections in memory and answers packets the way a real connector would.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="protocol-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">STREAM Protocol</h3>
                    </div>
                    <p class="text-slate-500 mb-4">STREAM is the transport protocol of Interledger. It sends a payment as a stream of small packets. The module opens and closes STREAM connections and sends packets over them.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Open and close a connection to an Interledger address</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Send prepare packets over a connection</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>A maximum amount for each packet</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>A timeout for each connection</li>
                    </ul>
                </div>

                <div class="protocol-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Packet Forwarding</h3>
                    </div>
                    <p class="text-slate-500 mb-4">In the protocol, a sender sends a prepare packet and gets back a fulfil or a reject packet. The simulated connector answers with a fulfil packet, or with a reject packet when the amount is too large.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Prepare, fulfil and reject packet types</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Random fulfilment for each accepted packet</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Packets above the limit are rejected</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Configurable spread and slippage</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Open Payments -->
    <section id="open-payments" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Open Payments</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Open Payments is a standard for incoming and outgoing payments on Interledger. It uses the Grant Negotiation and Authorization Protocol for access. A wallet publishes a payment pointer, and a payer uses it to ask for a grant.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="protocol-card card-feature !p-8">
                    <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Grants</h3>
                    <p class="text-slate-500 text-sm">A grant gives a client limited access to payments. The module accepts a grant token for outgoing payments, and each grant expires after a set time. It does not include its own authorisation server.</p>
                </div>

                <div class="protocol-card card-feature !p-8">
                    <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l-3-3m3 3l3-3"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Incoming Payments</h3>
                    <p class="text-slate-500 text-sm">The module creates an incoming payment for a wallet address. A payer can then send money to it.</p>
                </div>

                <div class="protocol-card card-feature !p-8">
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3l-3-3"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">Outgoing Payments</h3>
                    <p class="text-slate-500 text-sm">The module creates an outgoing payment with a grant token. It turns the payee's pointer into an Interledger address and records the payment so its status can be checked.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Cross-Currency Quotes -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Cross-Currency Quotes</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The module gives an exchange quote before a payment is sent. A quote is valid for 30 seconds. It uses fixed demo rates for five assets and a fee of 0.1%.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Quote Parameters</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Quote valid for</span>
                            <span class="text-sm font-mono text-purple-600">30 seconds</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Fee margin</span>
                            <span class="text-sm font-mono text-purple-600">0.1% per conversion</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Supported assets</span>
                            <span class="text-sm font-mono text-purple-600">USD, EUR, GBP, BTC, ETH</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-700">Rate source</span>
                            <span class="text-sm font-mono text-purple-600">Fixed demo rates</span>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Address Resolution</h3>
                    <p class="text-slate-500 mb-4">The resolver turns a readable payment pointer such as <code class="text-xs bg-gray-100 px-1 rounded">$wallet.example/alice</code> into an Interledger address such as <code class="text-xs bg-gray-100 px-1 rounded">g.us.example.alice</code>. It also works the other way round.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Payment pointers follow the HTTPS address rules</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Account addresses under the connector's own prefix</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Pointer format of the Simple Payment Setup Protocol</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Pointer format of Open Payments</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Try Interledger in the Sandbox</h2>
            <p class="text-lg text-slate-400 mb-8">The Interledger Protocol is built to send value between wallets on different networks and in different currencies. This module lets you study that flow with simulated payments.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create a Sandbox Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
