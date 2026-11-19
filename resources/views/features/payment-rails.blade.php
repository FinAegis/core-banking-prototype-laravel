@extends('layouts.public')

@section('title', 'Multi-Rail Payment Processing - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Multi-Rail Payment Processing',
        'description' => 'Message-format and routing modules for ACH, Fedwire, RTP, FedNow, and SEPA. NACHA file generation, ISO 20022 native FedNow, and ML-style rail selection (reference implementation).',
        'keywords' => 'payment rails, ACH, Fedwire, RTP, FedNow, SEPA, NACHA, ISO 8583, intelligent routing, same-day ACH, SEPA Instant, SCT Inst, payment processing',
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
                    <span class="text-orange-400 text-sm font-medium">6 Payment Rails</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Rail Payments</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Message-format and routing modules for ACH, Fedwire, RTP, FedNow, SEPA and ISO 8583 — with ML-style rail selection (reference implementation — no live scheme settlement).
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">US Payment Rails</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Message-format modules for US payment rails — from batch ACH to real-time FedNow — with native NACHA file generation and ISO 20022 support.
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
                    <p class="text-slate-500 mb-4">NACHA file generation and parsing for standard and same-day ACH. Batch management with 5-character company entry description and SEC code selection.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>NACHA fixed-width file generation and parsing</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Same-day ACH (T+0) and standard (T+1/T+2)</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>PPD, CCD, WEB, and TEL SEC codes</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Return and NOC handling</li>
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
                            <span class="text-xs text-slate-400">Real-Time Gross Settlement</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">Same-day RTGS transfers for high-value payments. Fedwire message format with tag-value encoding and immediate irrevocable settlement through the Federal Reserve.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Irrevocable same-day RTGS settlement</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Fedwire tag-value message format</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>ABA routing number validation</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Business use code and drawdown support</li>
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
                            <span class="text-xs text-slate-400">TCH Real-Time Payments</span>
                        </div>
                    </div>
                    <p class="text-slate-500 mb-4">The Clearing House real-time rail with 24/7/365 instant clearing. Sub-10-second end-to-end settlement with Request for Payment (RfP) support.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>24/7/365 instant settlement</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Request for Payment (RfP) workflow</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Remittance data with 140-character field</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Real-time confirmation receipts</li>
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
                    <p class="text-slate-500 mb-4">Federal Reserve's instant payment service with native ISO 20022 message format. pacs.008 credit transfers and pacs.002 status reports with UETR tracking.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>ISO 20022 native pacs.008 messages</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>UETR end-to-end tracking</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>24/7/365 Federal Reserve settlement</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>R-message (return) support</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- EU Rails -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">EU Payment Rails</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                SEPA Credit Transfer, Direct Debit, and Instant Credit Transfer message modules — with mandate management, built to the EPC rulebook formats.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-teal-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SEPA Credit Transfer</h3>
                    <p class="text-slate-500 text-sm mb-4">Pan-European credit transfers using pain.001 XML with IBAN/BIC routing. Batch SCT file generation for bulk payout workflows.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pain.001 XML generation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>T+1 standard settlement</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Structured remittance information</li>
                    </ul>
                </div>

                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SEPA Direct Debit</h3>
                    <p class="text-slate-500 text-sm mb-4">Mandate-based collection using pain.008 XML. Core and B2B SDD schemes with pre-notification requirements and mandate lifecycle management.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>pain.008 mandate collection</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Core and B2B scheme support</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mandate storage and amendment</li>
                    </ul>
                </div>

                <div class="rail-card card-feature !p-8">
                    <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold mb-2">SCT Inst (SEPA Instant)</h3>
                    <p class="text-slate-500 text-sm mb-4">SEPA Instant Credit Transfer message module (10-second scheme window, €100,000 limit).</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>10-second scheme settlement window</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>24/7/365 availability</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>EPC SCT Inst rulebook v1.0</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Intelligent Routing -->
    <section id="routing" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Intelligent Rail Routing</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                An ML-style scoring engine evaluates every candidate rail in real time and selects the optimal path — with configurable weights and full decision audit logging.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Scoring Factors</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Success Rate</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-green-500 rounded-full" style="width:85%"></div></div>
                                <span class="text-xs text-gray-500">85%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Latency</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-blue-500 rounded-full" style="width:70%"></div></div>
                                <span class="text-xs text-gray-500">70%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Transaction Cost</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-purple-500 rounded-full" style="width:60%"></div></div>
                                <span class="text-xs text-gray-500">60%</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-sm">Rail Availability</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden"><div class="h-2 bg-orange-500 rounded-full" style="width:95%"></div></div>
                                <span class="text-xs text-gray-500">95%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Failover &amp; Audit</h3>
                    <p class="text-slate-500 mb-5">When the primary rail is unavailable or returns an error, the router automatically falls back to the next-ranked rail. Every routing decision is logged with full scoring detail.</p>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Configurable failover chain (up to 3 rails)</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Full decision audit log with factor scores</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Override API for manual rail selection</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Real-time health check per rail</li>
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
                <p class="text-lg text-slate-500 mb-10">ISO 8583 message processing with bitmap codec (card-network message format). Authorization, reversal, and settlement messages with MTI routing.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                        <h3 class="font-semibold mb-2">Authorization</h3>
                        <p class="text-sm text-slate-500">MTI 0100/0110 authorization request and response with data element mapping and bitmap encoding.</p>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg></div>
                        <h3 class="font-semibold mb-2">Reversal</h3>
                        <p class="text-sm text-slate-500">MTI 0400/0410 reversal handling for partial and full authorization cancellations.</p>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center mb-4"><svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg></div>
                        <h3 class="font-semibold mb-2">Settlement</h3>
                        <p class="text-sm text-slate-500">MTI 0500/0510 settlement with daily batch reconciliation and interchange fee calculation.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">One API for Every Payment Rail</h2>
            <p class="text-lg text-slate-400 mb-8">Model routing across ACH, Fedwire, RTP, FedNow, and SEPA with one API (reference implementation — no live scheme settlement).</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Start Free Trial</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
