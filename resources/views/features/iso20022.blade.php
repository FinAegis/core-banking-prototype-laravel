@extends('layouts.public')

@section('title', 'ISO 20022 Financial Messaging - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'ISO 20022 Financial Messaging',
        'description' => 'A reference implementation of ISO 20022 payment messages. It generates, parses and checks eight pain, pacs and camt message types through REST and GraphQL APIs. It runs in demo mode and has no live connection to any payment scheme.',
        'keywords' => 'ISO 20022, financial messaging, pacs, pain, camt, SEPA messaging, cross-border payments, unique end-to-end transaction reference, XML validation, payment standards, reference implementation',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'ISO 20022 Financial Messaging', 'url' => url('/features/iso20022')]
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
                <div class="inline-flex items-center bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-blue-400 text-sm font-medium">Reference implementation</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">ISO 20022 Financial Messaging</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    ISO 20022 is the international standard for payment messages. This module generates, parses and checks pain, pacs and camt messages through REST and GraphQL APIs. It is a reference implementation at the message layer. It runs in demo mode, is switched off on the production site and has no live connection to SWIFT, SEPA or any other scheme.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#api-endpoints" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Explore API
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Message Types Overview -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">8 Supported Message Types</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The module covers eight common message types for credit transfers, direct debits and account reports. It can generate all eight. It reads pain.001, pacs.008 and pacs.002 field by field and the others in a general form.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Pain001 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pain.001</span>
                    <h3 class="text-lg font-bold mb-2">Customer Credit Transfer</h3>
                    <p class="text-slate-500 text-sm">A customer asks its bank to send money to another account.</p>
                </div>

                <!-- Pain008 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-orange-100 text-orange-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pain.008</span>
                    <h3 class="text-lg font-bold mb-2">Direct Debit Initiation</h3>
                    <p class="text-slate-500 text-sm">A creditor collects money from a payer's account under a mandate.</p>
                </div>

                <!-- Pacs008 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.008</span>
                    <h3 class="text-lg font-bold mb-2">Interbank Credit Transfer</h3>
                    <p class="text-slate-500 text-sm">One bank sends a credit transfer to another bank. Each message carries a unique end-to-end transaction reference.</p>
                </div>

                <!-- Pacs002 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-purple-100 text-purple-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.002</span>
                    <h3 class="text-lg font-bold mb-2">Payment Status Report</h3>
                    <p class="text-slate-500 text-sm">A bank reports the status of a payment, or the reason it was rejected.</p>
                </div>

                <!-- Pacs003 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-teal-100 text-teal-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.003</span>
                    <h3 class="text-lg font-bold mb-2">Interbank Direct Debit</h3>
                    <p class="text-slate-500 text-sm">One bank passes a direct debit to another bank.</p>
                </div>

                <!-- Pacs004 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-red-100 text-red-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.004</span>
                    <h3 class="text-lg font-bold mb-2">Payment Return</h3>
                    <p class="text-slate-500 text-sm">A bank returns a payment that was already made and gives a coded reason.</p>
                </div>

                <!-- Camt053 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-indigo-100 text-indigo-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">camt.053</span>
                    <h3 class="text-lg font-bold mb-2">Bank Statement</h3>
                    <p class="text-slate-500 text-sm">An end-of-day account statement with every entry and the balances.</p>
                </div>

                <!-- Camt054 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">camt.054</span>
                    <h3 class="text-lg font-bold mb-2">Debit and Credit Notification</h3>
                    <p class="text-slate-500 text-sm">A notice about a single booking during the day, so the account holder sees it at once.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Engine Capabilities</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The message engine reads raw XML and turns it into structured data. It also builds XML from data. Before it uses a message, it checks the structure and the required fields.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Message Registry</h3>
                    </div>
                    <p class="text-slate-500 mb-4">The registry reads the namespace of a raw XML message and finds its type. It then picks the matching parser and checker. You do not have to name the message type yourself.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Finds the message type from its namespace</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Clear error messages for every failed check</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Returns structured JSON from the XML</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Unique Transaction References</h3>
                    </div>
                    <p class="text-slate-500 mb-4">A cross-border payment carries a unique end-to-end transaction reference. Banks use it to follow the payment along the chain of correspondent banks. The module adds this reference to the messages it builds.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Creates the reference as a standard random UUID</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Puts the reference in pacs.008 messages and the business application header</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Reads the reference back when it parses a message</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Business Application Header</h3>
                    </div>
                    <p class="text-slate-500 mb-4">The business application header wraps an ISO 20022 message for sending. It names the sending and receiving bank by their bank identifier codes. It also holds the message name and the creation date.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Follows the AppHdr format</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Sender and receiver bank codes in the header</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Carries the message identifier</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">Message Checks</h3>
                    </div>
                    <p class="text-slate-500 mb-4">The checker runs before a message is used. It catches broken XML, unknown message types and missing required fields. It does not yet check messages against the official XML schemas.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Checks that the XML is well formed</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Names each missing required field</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Enforces a size limit and the enabled message families</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- API Endpoints -->
    <section id="api-endpoints" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">API Reference</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The module has REST and GraphQL APIs. Every endpoint needs a bearer token.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">REST Endpoints</h3>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded font-mono">POST</span>
                            <span class="ml-3 font-mono text-sm">/api/v1/iso20022/validate</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded font-mono">POST</span>
                            <span class="ml-3 font-mono text-sm">/api/v1/iso20022/parse</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded font-mono">POST</span>
                            <span class="ml-3 font-mono text-sm">/api/v1/iso20022/generate</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded font-mono">GET</span>
                            <span class="ml-3 font-mono text-sm">/api/v1/iso20022/supported-types</span>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">GraphQL Operations</h3>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded font-mono">mutation</span>
                            <span class="ml-3 font-mono text-sm">iso20022Validate</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-1 rounded font-mono">query</span>
                            <span class="ml-3 font-mono text-sm">iso20022SupportedTypes</span>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-gray-900 rounded-lg text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>mutation {
  iso20022Validate(
    xml: "..."
  ) {
    valid
    errors
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Use Cases</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Developers can use the module to learn and test ISO 20022 messages. Here are four examples.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Learning the Format</h3>
                    <p class="text-sm text-slate-500">Teams that move from older formats to ISO 20022 can build and read sample messages here. The module does not convert older SWIFT MT messages.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">SEPA Messaging</h3>
                    <p class="text-sm text-slate-500">The module generates pain.001 and pain.008 messages for SEPA credit transfers and direct debits. They follow the formats of the European Payments Council rulebooks. This is a reference implementation, and nothing is settled in a live scheme.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Cross-Border Tracking</h3>
                    <p class="text-sm text-slate-500">pacs.008 messages carry a unique end-to-end transaction reference. Banks use it to follow a payment from one correspondent bank to the next.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Statements and Notifications</h3>
                    <p class="text-sm text-slate-500">The module generates camt.053 statements and camt.054 notifications. You can use them to test automatic reconciliation.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Try ISO 20022 in the sandbox</h2>
            <p class="text-lg text-slate-400 mb-8">Create a sandbox account and send a message to the parser. The developer portal has the full API documentation.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create a Sandbox Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
