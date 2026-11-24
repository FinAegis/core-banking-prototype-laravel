@extends('layouts.public')

@section('title', 'Multi-Rail Payment Processing - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Multi-Rail Payment Processing',
        'description' => 'A reference implementation of payment rails. It models ACH, Fedwire, RTP, FedNow and SEPA payments, builds NACHA files and FedNow messages, and scores rails to pick one. It runs in demo mode and nothing is settled in a live scheme.',
        'keywords' => 'payment rails, ACH, Fedwire, RTP, FedNow, SEPA, NACHA, ISO 8583, rail routing, same-day ACH, SEPA Instant, payment processing, reference implementation',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Multi-Rail Payment Processing', 'url' => url('/features/payment-rails')]
    ]" />
@endsection

@push('styles')
<style>
    .rail-card {
        transition: all 0.3s ease;
    }
    .rail-card:hover {
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
                <div class="inline-flex items-center bg-orange-500/10 border border-orange-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-orange-400 text-sm font-medium">Reference implementation</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Rail Payments</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    These modules model payments on the Automated Clearing House network (ACH), Fedwire, the Real-Time Payments network (RTP), FedNow and SEPA. They also model card messages in ISO 8583. A scoring engine picks the rail for each payment. This is a reference implementation at the message and logic layer. It runs in demo mode, is switched off on the production site and settles nothing in a live scheme.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#routing" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Explore Routing
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-orange-500/20 to-transparent"></div>
    </section>

    <!-- US Rails -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">United States Payment Rails</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The modules cover four payment rails in the United States, from batch Automated Clearing House payments to instant FedNow payments. They build NACHA files for the Automated Clearing House network and ISO 20022 messages for FedNow.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- ACH -->
                <div class="rail-card card-feature !p-8">
                    <div class="flex items-center mb-5">
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">ACH</h3>
                            <span class="text-xs text-slate-400">Automated Clearing House</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">The module builds NACHA files for standard and same-day ACH batches. It also reads return files and notification of change files.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>NACHA fixed-width files</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Same-day and standard batches</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Standard entry class codes PPD, CCD, WEB and TEL</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Return and notification of change files</li>
                    </ul>
                </div>

                <!-- Fedwire -->
                <div class="rail-card card-feature !p-8">
                    <div class="flex items-center mb-5">
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">Fedwire</h3>
                            <span class="text-xs text-slate-400">Federal Reserve wire transfers</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">Fedwire is the Federal Reserve's same-day system for large payments. The module records a transfer request and tracks its status. It does not build the Fedwire message format and has no connection to the Federal Reserve.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Transfer request with the beneficiary's routing number</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Sender routing number from the settings</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Status lookup for each transfer</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Status updates through callbacks</li>
                    </ul>
                </div>

                <!-- RTP -->
                <div class="rail-card card-feature !p-8">
                    <div class="flex items-center mb-5">
                        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">RTP</h3>
                            <span class="text-xs text-slate-400">The Clearing House Real-Time Payments</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">RTP is the instant payment rail of The Clearing House. The module models instant credit transfers with an amount limit. It also models requests for payment.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Instant credit transfers</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Request for payment</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>A configurable maximum amount</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Status for each payment</li>
                    </ul>
                </div>

                <!-- FedNow -->
                <div class="rail-card card-feature !p-8">
                    <div class="flex items-center mb-5">
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">FedNow</h3>
                            <span class="text-xs text-slate-400">Federal Reserve Instant Payments</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">FedNow is the Federal Reserve's instant payment service, and it uses ISO 20022 messages. The module builds pacs.008 credit transfers and reads pacs.002 status reports.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pacs.008 credit transfer messages</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pacs.002 status reports update the transaction</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Available at any time of day in the routing rules</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>No connection to the Federal Reserve</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- EU Rails -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">European Payment Rails</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The modules build messages for SEPA credit transfers, direct debits and instant credit transfers. They follow the formats of the European Payments Council rulebooks, and they also manage direct debit mandates.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SEPA Credit Transfer</h3>
                    <p class="text-slate-500 text-sm mb-4">A SEPA credit transfer sends euros between accounts in Europe. The module builds the pain.001 message with the account numbers and bank codes.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pain.001 XML generation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Status lookup for each transfer</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Payment reference in the message</li>
                    </ul>
                </div>

                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SEPA Direct Debit</h3>
                    <p class="text-slate-500 text-sm mb-4">A SEPA direct debit collects euros under a mandate. The module builds the pain.008 message for the core scheme and the business scheme. It also handles returned collections.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pain.008 mandate collection</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Core and business schemes</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mandates can be created, suspended, cancelled and reactivated</li>
                    </ul>
                </div>

                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SEPA Instant Credit Transfer</h3>
                    <p class="text-slate-500 text-sm mb-4">A SEPA instant credit transfer must arrive within ten seconds. The module builds it as a pacs.008 message. The routing rules allow up to €100,000 for this rail.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pacs.008 message</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Available at any time of day in the routing rules</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>€100,000 limit in the routing rules</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Intelligent Routing -->
    <section id="routing" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Rail Routing</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The router gives every possible rail a score and picks the highest one. The score weighs past success, speed, cost, opening hours and how urgent the payment is. The router returns the reasons for its choice together with the other rails.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Weight of each factor</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Success Rate</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-green-500 rounded-full" style="width:30%"></div></div>
                                <span class="text-xs text-gray-500">30%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Latency</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-blue-500 rounded-full" style="width:20%"></div></div>
                                <span class="text-xs text-gray-500">20%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Transaction Cost</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-purple-500 rounded-full" style="width:25%"></div></div>
                                <span class="text-xs text-gray-500">25%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Opening Hours</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-orange-500 rounded-full" style="width:15%"></div></div>
                                <span class="text-xs text-gray-500">15%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Alternatives and Audit</h3>
                    <p class="text-slate-500 mb-5">Urgency makes up the remaining 10% of the score. Each decision comes back with its scores and the ranked alternatives. A fallback order is defined for each rail, but the router does not yet retry on another rail by itself.</p>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Fallback order defined for each rail</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Decision factors returned with every choice</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Ranked list of the other rails</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Opening hours checked for each rail</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ISO 8583 -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">ISO 8583 Message Processing</h2>
                <p class="text-lg text-slate-500 mb-10">ISO 8583 is the message format of card networks. The module encodes and decodes these messages with their bitmaps. It routes authorisation, reversal and settlement messages by their message type indicator.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                        <h3 class="font-semibold mb-2">Authorization</h3>
                        <p class="text-sm text-slate-500">Message types 0100 and 0110 carry the authorisation request and the response. The module maps the data elements and encodes the bitmap.</p>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></div>
                        <h3 class="font-semibold mb-2">Reversal</h3>
                        <p class="text-sm text-slate-500">Message types 0400 and 0410 reverse an earlier authorisation.</p>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg></div>
                        <h3 class="font-semibold mb-2">Settlement</h3>
                        <p class="text-sm text-slate-500">Message types 0500 and 0510 carry settlement. In this reference implementation the module approves a settlement request that has an amount.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">One API for Several Payment Rails</h2>
            <p class="text-lg text-slate-400 mb-8">One API lets you model routing across ACH, Fedwire, RTP, FedNow and SEPA. This is a reference implementation, and nothing is settled in a live scheme.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create a Sandbox Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
