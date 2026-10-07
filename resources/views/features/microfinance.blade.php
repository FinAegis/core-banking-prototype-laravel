@extends('layouts.public')

@section('title', 'Microfinance Suite - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Microfinance Suite',
        'description' => 'Complete inclusion banking with group lending, IFRS loan provisioning, share accounts, teller operations, field officer tools, and savings products.',
        'keywords' => 'microfinance, group lending, joint liability, IFRS provisioning, cooperative shares, teller operations, field officer, savings products, financial inclusion, MFI software',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Microfinance Suite', 'url' => url('/features/microfinance')]
    ]" />
@endsection

@push('styles')
<style>
    .module-card {
        transition: all 0.3s ease;
    }
    .module-card:hover {
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
                <div class="inline-flex items-center bg-green-500/10 border border-green-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-green-400 text-sm font-medium">Financial Inclusion</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Microfinance Suite</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Financial inclusion modules. MFI software with group lending, IFRS loan provisioning, cooperative share accounts, teller operations, field officer tools, and savings products — all in one API.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#modules" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Explore Modules
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-green-500/20 to-transparent"></div>
    </section>

    <!-- Module Overview -->
    <section id="modules" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Complete MFI Platform</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Six integrated modules covering every aspect of microfinance operations — from field collection to regulatory provisioning.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Group Lending -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Group Lending</h3>
                    <p class="text-slate-500 text-sm mb-4">Joint liability lending groups with center hierarchy. Meeting scheduling, attendance tracking, and group loan disbursement with shared guarantee structure.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Joint liability group structure</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Center and village hierarchy</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Meeting management and attendance</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Group loan cycle tracking</li>
                    </ul>
                </div>

                <!-- IFRS Provisioning -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-red-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">IFRS Loan Provisioning</h3>
                    <p class="text-slate-500 text-sm mb-4">Loan loss provisioning modelled on IFRS 9, with four classification stages. Configurable provision rates per stage with automatic staging based on days past due.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Standard / Substandard / Doubtful / Loss</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Configurable provision rates per stage</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Automatic DPD-based reclassification</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Provision expense GL posting</li>
                    </ul>
                </div>

                <!-- Share Accounts -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Share Accounts</h3>
                    <p class="text-slate-500 text-sm mb-4">Cooperative member share accounts with share certificate management. Dividend calculation at period close and distribution to member accounts.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Member share certificates</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Share purchase and redemption</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Dividend calculation per period</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Automatic distribution to savings</li>
                    </ul>
                </div>

                <!-- Teller Operations -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Teller Operations</h3>
                    <p class="text-slate-500 text-sm mb-4">Branch vault management with teller cash allocation. Cash-in and cash-out transactions with balance guards preventing over-disbursement.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Vault and till management</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Cash-in / cash-out with balance guard</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Teller opening and closing entries</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Denomination tracking</li>
                    </ul>
                </div>

                <!-- Field Officer -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-orange-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Field Officer Tools</h3>
                    <p class="text-slate-500 text-sm mb-4">Territory assignment and collection sheet generation for field agents. Sync collections made in the field back to core with offline-first mobile support.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Territory and route assignment</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Daily collection sheet generation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Mobile sync for offline collections</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Performance dashboard per officer</li>
                    </ul>
                </div>

                <!-- Savings Products -->
                <div class="module-card card-feature !p-8">
                    <div class="w-14 h-14 bg-teal-50 rounded-lg flex items-center justify-center mb-5">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Savings Products</h3>
                    <p class="text-slate-500 text-sm mb-4">Flexible savings product engine with simple and compound interest calculation. Dormancy tracking with auto-notification and configurable reactivation fees.</p>
                    <ul class="space-y-1 text-xs text-gray-500">
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Simple and compound interest</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Dormancy detection and notification</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Minimum balance enforcement</li>
                        <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Tiered interest rate schedules</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- IFRS Provisioning Detail -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">IFRS Loan Classification</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Automatically classify loans into four IFRS stages based on days past due (DPD) and calculate provision amounts using configurable rates.
            </p>

            <div class="max-w-3xl mx-auto">
                <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="text-left px-6 py-4 font-semibold">Stage</th>
                                <th class="text-left px-6 py-4 font-semibold">DPD Trigger</th>
                                <th class="text-left px-6 py-4 font-semibold">Default Rate</th>
                                <th class="text-left px-6 py-4 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">Standard</td>
                                <td class="px-6 py-4 text-gray-500">0–30 days</td>
                                <td class="px-6 py-4 font-mono text-green-700">1%</td>
                                <td class="px-6 py-4"><span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">Performing</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">Substandard</td>
                                <td class="px-6 py-4 text-gray-500">31–90 days</td>
                                <td class="px-6 py-4 font-mono text-yellow-700">10%</td>
                                <td class="px-6 py-4"><span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded">Watch</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">Doubtful</td>
                                <td class="px-6 py-4 text-gray-500">91–180 days</td>
                                <td class="px-6 py-4 font-mono text-orange-700">50%</td>
                                <td class="px-6 py-4"><span class="bg-orange-100 text-orange-800 text-xs font-medium px-2 py-1 rounded">Non-performing</span></td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium">Loss</td>
                                <td class="px-6 py-4 text-gray-500">181+ days</td>
                                <td class="px-6 py-4 font-mono text-red-700">100%</td>
                                <td class="px-6 py-4"><span class="bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded">Write-off</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-400 text-center mt-4">All provision rates are configurable per product type and jurisdiction.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Build Your MFI on Open-Source Software</h2>
            <p class="text-lg text-slate-400 mb-8">Everything from group loan disbursement to regulatory provisioning — API-first and built for the field.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Start Free Trial</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">View API Docs</a>
            </div>
        </div>
    </section>

@endsection
