@extends('layouts.public')

@section('title', 'Regulatory Technology and Compliance - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Regulatory Technology and Compliance',
        'description' => 'The regulatory technology modules build reports and exchange data in the formats that European Union and other rules describe. They are modelled on the Markets in Financial Instruments Directive, the Markets in Crypto-Assets Regulation and the Travel Rule. They do not by themselves make a deployment compliant.',
        'keywords' => 'regulatory technology, compliance, Markets in Financial Instruments Directive, Markets in Crypto-Assets Regulation, Travel Rule, Financial Action Task Force, regulatory reporting, jurisdiction adapters, identity checks, anti-money laundering, ' . config('brand.name', 'Zelta'),
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Regulatory Technology and Compliance', 'url' => url('/features/regtech-compliance')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Regulatory Technology and Compliance</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    These modules build regulatory reports and exchange the data that rules require. They are modelled on the European Union Markets in Financial Instruments Directive, the European Union Markets in Crypto-Assets Regulation and the Travel Rule. They do not by themselves make a deployment compliant.
                </p>
                <p class="text-sm text-slate-500 max-w-3xl mx-auto mt-4">
                    FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Overview Cards -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="regulation-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Markets in Financial Instruments Reporting</h3>
                    <p class="text-slate-500">This module analyses how well trades were executed and manages instrument reference data. It builds transaction reports in the format that the Markets in Financial Instruments Directive describes.</p>
                </div>

                <div class="regulation-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Markets in Crypto-Assets Checks</h3>
                    <p class="text-slate-500">This module checks a crypto-asset white paper against a list of required content. It also monitors token reserves and records whether the operator holds an authorisation. It is modelled on the Markets in Crypto-Assets Regulation.</p>
                </div>

                <div class="regulation-card card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Travel Rule</h3>
                    <p class="text-slate-500">The Travel Rule comes from the Financial Action Task Force. This module exchanges data about the sender and the receiver of a transfer above a threshold that you set.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Regulation Detail Sections -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Regulatory Frameworks</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-indigo-900">Markets in Financial Instruments Reporting</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Best Execution Analysis</h4>
                                <p class="text-slate-600">The module compares execution quality across venues by price, cost and speed.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Instrument Reference Data</h4>
                                <p class="text-slate-600">The module manages instrument codes and legal entity identifiers. Transaction reports need these codes.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Transaction Reporting</h4>
                                <p class="text-slate-600">The module builds transaction reports. A submission adapter models how a report would go to an approved reporting service, but it does not connect to one.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-purple-900">Markets in Crypto-Assets Modules</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">White Paper Checks</h4>
                                <p class="text-slate-600">The module checks a white paper for the required disclosures and risk warnings.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Reserve Management</h4>
                                <p class="text-slate-600">The module reports whether the reserves behind a stablecoin cover the tokens in circulation.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-purple-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Authorisation Status</h4>
                                <p class="text-slate-600">The module records whether the operator holds a crypto-asset service provider authorisation. It does not grant one.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Jurisdiction Adapters -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Multi-Jurisdiction Adapters</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <p class="text-lg text-slate-500 mb-6">
                        The {{ config('brand.name', 'Zelta') }} regulatory engine uses one adapter for each jurisdiction. Each adapter models the published rules of its market. The adapters run in sandbox mode and do not connect to any regulator.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-indigo-600 mb-1">United States</div>
                            <p class="text-sm text-slate-500">Financial Crimes Enforcement Network</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600 mb-1">European Union</div>
                            <p class="text-sm text-slate-500">European Securities and Markets Authority</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-600 mb-1">United Kingdom</div>
                            <p class="text-sm text-slate-500">Financial Conduct Authority</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-pink-600 mb-1">Singapore</div>
                            <p class="text-sm text-slate-500">Monetary Authority of Singapore</p>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 mt-4">The adapters are modelled on published requirements. No regulator has approved or endorsed them.</p>
                </div>

                <div class="bg-gray-50 rounded-2xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-slate-900">Travel Rule Module</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-white rounded-lg">
                            <span class="text-slate-500">Threshold Check</span>
                            <span class="text-lg font-bold text-indigo-600">By Jurisdiction</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-white rounded-lg">
                            <span class="text-slate-500">Sender Data</span>
                            <span class="text-lg font-bold text-purple-600">Checked</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-white rounded-lg">
                            <span class="text-slate-500">Receiver Data</span>
                            <span class="text-lg font-bold text-green-600">Checked</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-white rounded-lg">
                            <span class="text-slate-500">Exchange Between Providers</span>
                            <span class="text-lg font-bold text-pink-600">Modelled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Features Checklist -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Compliance Orchestration</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Regulatory Reporting</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Reports built on a schedule that you set</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Workflows for suspicious activity reports</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Cross-border transfers checked against Travel Rule thresholds</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Screening against sanctions lists</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>An audit trail stored as events that cannot be changed</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>A compliance dashboard with risk scores and alerts</span>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Orchestration Engine</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>One compliance pipeline for all configured rule sets</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Jurisdiction adapters that you can add or switch off</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>A filing calendar with due dates for each regulator</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Checks before a transaction, which stop it at the first failure</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Filing schedules stored for each regulator</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>An API for connecting other compliance tools</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Open-Source Compliance Tooling</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get Started
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    Compliance API Docs
                </a>
            </div>
        </div>
    </section>

@endsection
