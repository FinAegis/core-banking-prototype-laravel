@extends('layouts.public')

@section('title', 'Open Banking & PSD2 Modules - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Open Banking & PSD2 Modules',
        'description' => 'Open Banking modules modelled on PSD2: consent lifecycle, AISP/PISP flows, Berlin Group NextGenPSD2 and UK Open Banking adapters, and eIDAS TPP certificate validation.',
        'keywords' => 'Open Banking, PSD2, AISP, PISP, consent lifecycle, Berlin Group, NextGenPSD2, UK Open Banking, TPP, eIDAS, QWAC, payment initiation, account information',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Open Banking & PSD2', 'url' => url('/features/open-banking')]
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
    .consent-step {
        position: relative;
    }
    .consent-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 2.25rem;
        left: 1.75rem;
        width: 2px;
        height: calc(100% + 1rem);
        background: linear-gradient(to bottom, #14b8a6, #e2e8f0);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center bg-teal-500/10 border border-teal-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-teal-400 text-sm font-medium">PSD2-Modelled</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Open Banking &amp; PSD2</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Consent-driven account access and payment initiation modules modelled on PSD2 AISP and PISP flows, with Berlin Group NextGenPSD2 and UK Open Banking adapters and eIDAS TPP certificate validation. They do not by themselves make a deployment compliant.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#adapters" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        View Adapters
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-teal-500/20 to-transparent"></div>
    </section>

    <!-- Consent Lifecycle -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Consent Lifecycle</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Every data access and payment initiation is gated by an explicit, auditable consent object. Consents follow a defined lifecycle from creation through authorisation to expiry or revocation.
            </p>

            <div class="max-w-2xl mx-auto space-y-6">
                <div class="consent-step flex items-start gap-6 pl-2">
                    <div class="w-14 h-14 bg-teal-50 rounded-full flex items-center justify-center flex-shrink-0 border-2 border-teal-200 z-10">
                        <span class="text-teal-700 font-bold text-lg">1</span>
                    </div>
                    <div class="pt-2">
                        <h3 class="text-lg font-bold mb-1">Create Consent</h3>
                        <p class="text-slate-500 text-sm">TPP requests a consent object specifying scope (accounts, balances, transactions), permissions, and expiry date. Returned consent ID is used in all subsequent requests.</p>
                    </div>
                </div>
                <div class="consent-step flex items-start gap-6 pl-2">
                    <div class="w-14 h-14 bg-teal-50 rounded-full flex items-center justify-center flex-shrink-0 border-2 border-teal-200 z-10">
                        <span class="text-teal-700 font-bold text-lg">2</span>
                    </div>
                    <div class="pt-2">
                        <h3 class="text-lg font-bold mb-1">Authorize Consent</h3>
                        <p class="text-slate-500 text-sm">PSU is redirected to the ASPSP authorization server. After strong customer authentication (SCA), consent transitions to AUTHORISED status.</p>
                    </div>
                </div>
                <div class="consent-step flex items-start gap-6 pl-2">
                    <div class="w-14 h-14 bg-teal-50 rounded-full flex items-center justify-center flex-shrink-0 border-2 border-teal-200 z-10">
                        <span class="text-teal-700 font-bold text-lg">3</span>
                    </div>
                    <div class="pt-2">
                        <h3 class="text-lg font-bold mb-1">Use Consent</h3>
                        <p class="text-slate-500 text-sm">Authorized consents gate AISP account queries and PISP payment initiations. Usage is logged with timestamp and TPP identity for full audit trail.</p>
                    </div>
                </div>
                <div class="flex items-start gap-6 pl-2">
                    <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center flex-shrink-0 border-2 border-slate-200 z-10">
                        <span class="text-slate-500 font-bold text-lg">4</span>
                    </div>
                    <div class="pt-2">
                        <h3 class="text-lg font-bold mb-1">Expire or Revoke</h3>
                        <p class="text-slate-500 text-sm">Consents automatically expire at their defined date or can be revoked by the PSU at any time. Revoked consents are immediately rejected by middleware.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- AISP & PISP -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">AISP &amp; PISP Modules</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Two independent modules modelled on the Account Information and Payment Initiation service roles, each enforced by separate consent scopes.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-teal-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">AISP — Account Information</h3>
                    <p class="text-slate-500 mb-5">Read-only access to account data gated by consent. Supports accounts list, balance queries, and transaction history with pagination.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>List accounts with IBAN and currency</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Real-time and available balance queries</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Transaction history with date range filters</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Consent-gated frequency limiting</li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">PISP — Payment Initiation</h3>
                    <p class="text-slate-500 mb-5">Initiate domestic and cross-border payments on behalf of the PSU. Consent verification is enforced before every payment submission.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Domestic and SEPA payment initiation</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Pre-authorisation consent check</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Payment status polling endpoint</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Idempotency key support</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Adapters -->
    <section id="adapters" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Format Adapters</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Native adapters for both major Open Banking standards. Swap between formats without changing your business logic.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="protocol-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">Berlin Group NextGenPSD2</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Implements the NextGenPSD2 XS2A Framework specification used across EU ASPSPs. Supports all mandatory and recommended endpoints with consent object model.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Consent creation and authorisation flows</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>AIS and PIS endpoint naming</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>SCA redirect and decoupled approaches</li>
                    </ul>
                </div>

                <div class="protocol-card card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold">UK Open Banking</h3>
                    </div>
                    <p class="text-slate-500 mb-4">Implements the OBIE (Open Banking Implementation Entity) Read/Write API specification v3.1+. Used by UK-regulated ASPSPs and TPPs.</p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Account and transaction resources</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Domestic and international payments</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>FAPI security profile</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Security -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Security &amp; Compliance</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Every layer of the Open Banking stack is hardened — from TPP certificate validation at the TLS layer to consent enforcement middleware on every API call.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card-feature !p-8 text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">eIDAS / QWAC</h3>
                    <p class="text-slate-500 text-sm">TPP certificates validated against eIDAS trust anchors. QWAC (Qualified Website Authentication Certificate) checked for organisational identity and PSD2 roles.</p>
                </div>
                <div class="card-feature !p-8 text-center">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Consent Enforcement</h3>
                    <p class="text-slate-500 text-sm">Every AISP and PISP request passes through consent enforcement middleware. Expired, revoked, or scope-mismatched consents return 403 immediately.</p>
                </div>
                <div class="card-feature !p-8 text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Frequency Limiting</h3>
                    <p class="text-slate-500 text-sm">Per-consent access frequency limits modelled on the EBA guidelines under PSD2.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Build Open Banking Products Faster</h2>
            <p class="text-lg text-slate-400 mb-8">The consent engine, adapters, and security middleware are included. They do not by themselves make a deployment compliant.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Start Free Trial</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
