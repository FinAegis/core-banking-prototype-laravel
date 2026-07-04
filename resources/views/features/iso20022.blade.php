@extends('layouts.public')

@section('title', 'ISO 20022 Financial Messaging - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'ISO 20022 Financial Messaging',
        'description' => 'Standards-compliant ISO 20022 message engine with 8 message types. Parse, generate, and validate pacs, pain, and camt messages via REST and GraphQL APIs.',
        'keywords' => 'ISO 20022, financial messaging, pacs, pain, camt, SWIFT migration, SEPA messaging, cross-border payments, UETR tracking, XML validation, XSD, payment standards',
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
                    <span class="text-blue-400 text-sm font-medium">ISO 20022 Native</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">ISO 20022 Financial Messaging</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Standards-compliant message processing for cross-border payments. Parse, generate, and validate pacs, pain, and camt messages via REST and GraphQL APIs — ready for SWIFT migration and SEPA.
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
                Full coverage of the most critical ISO 20022 message types used in cross-border payments, direct debits, and account reporting. Every message type includes an XML parser, generator, and XSD validator.
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
                    <p class="text-slate-500 text-sm">Initiate customer credit transfers from debtor to creditor institutions.</p>
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
                    <p class="text-slate-500 text-sm">Pull-based payment collection from debtor accounts with mandate references.</p>
                </div>

                <!-- Pacs008 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.008</span>
                    <h3 class="text-lg font-bold mb-2">FI Credit Transfer</h3>
                    <p class="text-slate-500 text-sm">Interbank credit transfers between financial institutions with UETR tracking.</p>
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
                    <p class="text-slate-500 text-sm">Status updates and rejection reasons for initiated payment instructions.</p>
                </div>

                <!-- Pacs003 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-teal-100 text-teal-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">pacs.003</span>
                    <h3 class="text-lg font-bold mb-2">FI Direct Debit</h3>
                    <p class="text-slate-500 text-sm">Interbank direct debit settlement between financial institutions.</p>
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
                    <p class="text-slate-500 text-sm">Return previously executed payments with structured return reason codes.</p>
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
                    <p class="text-slate-500 text-sm">End-of-day account statements with detailed entry and balance reporting.</p>
                </div>

                <!-- Camt054 -->
                <div class="protocol-card card-feature !p-6">
                    <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                    <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-1 rounded mb-2 font-mono">camt.054</span>
                    <h3 class="text-lg font-bold mb-2">Debit/Credit Notification</h3>
                    <p class="text-slate-500 text-sm">Intraday transaction notifications for real-time account monitoring.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Engine Capabilities</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                A production-grade message engine handling the full lifecycle from parsing raw XML to generating standards-compliant output with namespace detection and XSD validation.
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
                    <p class="text-slate-500 mb-4">Automatic namespace detection identifies message types from raw XML input. The registry maps each ISO 20022 namespace to its parser and validator, eliminating manual type selection.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Automatic namespace-to-type mapping</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>XSD schema validation with detailed error reporting</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Structured JSON output from any XML input</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">UETR Cross-Border Tracking</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Every cross-border payment carries a Unique End-to-End Transaction Reference (UETR), enabling end-to-end payment tracking across correspondent banking chains.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Auto-generated RFC 4122 UUIDs for UETR</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>SWIFT gpi tracker compatible</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Status query by UETR reference</li>
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
                    <p class="text-slate-500 mb-4">Full BAH (AppHdr) support for wrapping ISO 20022 messages in SWIFT MX envelopes. Includes sender/receiver BIC, message name, and creation date with proper namespace handling.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>SWIFT MX envelope compatible</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>BIC routing in header fields</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Duplex detection via message ID</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold">XSD Validation Engine</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Validate messages against official ISO 20022 XSD schemas before transmission. Catch structural errors, missing mandatory fields, and invalid code values at parse time.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Official ISO XSD schema bundle</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Field-level error messages with XPath</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Strict and lenient modes</li>
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
                REST and GraphQL APIs for the full ISO 20022 message lifecycle. All endpoints require Bearer token authentication.
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
    type: "pacs.008"
  ) {
    valid
    errors { field message }
    uetr
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
                From SWIFT migration projects to SEPA compliance and regulatory reporting — ISO 20022 is the common thread.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">SWIFT Migration</h3>
                    <p class="text-sm text-slate-500">Translate legacy MT messages to ISO 20022 MX format. Full co-existence support during the SWIFT migration period.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">SEPA Messaging</h3>
                    <p class="text-sm text-slate-500">Native pain.001/pain.008 message generation for SEPA Credit Transfers and Direct Debits, built to the EPC rulebook formats (reference implementation — no live scheme settlement).</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Cross-Border Tracking</h3>
                    <p class="text-sm text-slate-500">Track payments hop-by-hop across correspondent banks using UETR references embedded in pacs.008 messages.</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Regulatory Reporting</h3>
                    <p class="text-sm text-slate-500">Generate camt.053 statements and camt.054 notifications for automated reconciliation and regulatory submission.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Ready for ISO 20022?</h2>
            <p class="text-lg text-slate-400 mb-8">Parse your first message in under five minutes. Full API docs available in the developer portal.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Start Free Trial</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
