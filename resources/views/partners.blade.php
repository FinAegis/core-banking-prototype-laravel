@extends('layouts.public')

@section('title', 'Banking Partners - Multi-Bank Architecture | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Banking Partners - Multi-Bank Architecture | ' . config('brand.name', 'Zelta') . '',
        'description' => config('brand.name', 'Zelta') . ' multi-bank architecture with pluggable connectors for institutional partners. Distributed fund security across licensed European banks.',
        'keywords' => config('brand.name', 'Zelta') . ' partners, banking partners, multi-bank, deposit protection, EU banking, fund distribution',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Partners', 'url' => url('/partners')]
    ]" />
@endsection

@section('content')
    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 right-1/4 w-80 h-80 bg-blue-500/8 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/[0.04] border border-white/[0.08] rounded-2xl mb-6">
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                @include('partials.breadcrumb', ['items' => [['name' => 'Partners', 'url' => url('/partners')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Bank <span class="text-gradient">Architecture</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Distributed fund security across licensed European banks with pluggable connectors and full deposit insurance coverage.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Architecture Notice -->
    <section class="py-6 bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-stat flex items-start gap-3 border-l-4 border-amber-400 bg-amber-50/50">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-display text-sm font-semibold text-slate-900">Multi-Bank Architecture</h3>
                    <p class="text-sm text-slate-600 mt-0.5">
                        {{ config('brand.name', 'Zelta') }} supports <strong>multi-bank architecture</strong> with pluggable connectors for institutional partners.
                        The bank integrations shown are reference implementations demonstrating production integration patterns.
                        Live bank partnerships require separate commercial agreements.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Partner Banks -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Reference Banking Partners</h2>
                <p class="text-lg text-slate-500">Fund distribution across licensed banks with full deposit insurance</p>
            </div>

            @php
                $banks = [
                    ['name' => 'Paysera Bank', 'license' => 'EMI License (EU)', 'country' => 'Lithuania', 'founded' => '2004', 'protection' => '€100,000', 'color' => 'teal', 'badge' => 'Primary Partner', 'badgeColor' => 'success'],
                    ['name' => 'Deutsche Bank', 'license' => 'Full Banking License', 'country' => 'Germany', 'founded' => '1870', 'protection' => '€100,000', 'color' => 'blue', 'badge' => 'Corporate Banking', 'badgeColor' => 'info'],
                    ['name' => 'Santander Bank', 'license' => 'Full Banking License', 'country' => 'Spain', 'founded' => '1857', 'protection' => '€100,000', 'color' => 'red', 'badge' => 'Retail Banking', 'badgeColor' => 'accent'],
                    ['name' => 'Revolut Bank', 'license' => 'Full Banking License', 'country' => 'Lithuania', 'founded' => '2015', 'protection' => '€100,000', 'color' => 'violet', 'badge' => 'Digital Banking', 'badgeColor' => 'info'],
                    ['name' => 'N26 Bank', 'license' => 'Full Banking License', 'country' => 'Germany', 'founded' => '2013', 'protection' => '€100,000', 'color' => 'slate', 'badge' => 'Mobile Banking', 'badgeColor' => 'info'],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($banks as $i => $bank)
                <div class="card-feature text-center !p-8 animate-on-scroll stagger-{{ ($i % 6) + 1 }}">
                    <div class="w-16 h-16 bg-{{ $bank['color'] }}-50 rounded-full flex items-center justify-center mx-auto mb-5">
                        <svg class="w-8 h-8 text-{{ $bank['color'] }}-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V5l-9-4z"/></svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-1">{{ $bank['name'] }}</h3>
                    <p class="text-sm text-slate-500 mb-4">{{ $bank['license'] }}</p>
                    <div class="space-y-1.5 text-sm text-slate-600 mb-5">
                        <p><strong class="text-slate-900">Protection:</strong> {{ $bank['protection'] }}</p>
                        <p><strong class="text-slate-900">Country:</strong> {{ $bank['country'] }}</p>
                        <p><strong class="text-slate-900">Founded:</strong> {{ $bank['founded'] }}</p>
                    </div>
                    <span class="badge badge-{{ $bank['badgeColor'] }}">{{ $bank['badge'] }}</span>
                </div>
                @endforeach

                <!-- Future Partner Slot -->
                <div class="card-feature text-center !p-8 !border-2 !border-dashed !border-slate-200 !bg-slate-50/50 animate-on-scroll stagger-6">
                    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-5">
                        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-400 mb-1">Your Bank Next?</h3>
                    <p class="text-sm text-slate-400 mb-4">Partnership Opportunities</p>
                    <p class="text-sm text-slate-500 mb-5">
                        We're expanding our network of banking partners for better coverage and security.
                    </p>
                    <a href="{{ route('support.contact') }}" class="text-blue-600 font-semibold text-sm hover:text-blue-700 transition">
                        Partner With Us &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Multi-Bank -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Why Multi-Bank Distribution?</h2>
                <p class="text-lg text-slate-500">Maximum security through diversification</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-on-scroll stagger-1">
                @php
                    $benefits = [
                        ['title' => 'Enhanced Security', 'desc' => 'Funds never concentrated in a single institution, reducing risk and increasing protection.', 'color' => 'teal', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                        ['title' => 'Higher Insurance Coverage', 'desc' => 'Up to €500,000 total deposit protection across multiple banks, far exceeding single-bank limits.', 'color' => 'blue', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'],
                        ['title' => 'Improved Uptime', 'desc' => 'If one bank experiences issues, other accounts remain fully operational, ensuring continuous access.', 'color' => 'amber', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ];
                @endphp

                @foreach($benefits as $b)
                <div class="card-feature text-center !p-8">
                    <div class="icon-box-lg bg-{{ $b['color'] }}-50 mx-auto mb-5">
                        <svg class="w-6 h-6 text-{{ $b['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $b['icon'] }}"/></svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-3">{{ $b['title'] }}</h3>
                    <p class="text-slate-500 leading-relaxed">{{ $b['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Regulatory Compliance -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Regulatory Compliance</h2>
                <p class="text-lg text-slate-500">All partners maintain the highest regulatory standards</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 animate-on-scroll stagger-1">
                @php
                    $compliance = [
                        ['title' => 'EU Licensed', 'desc' => 'Valid EU banking or EMI licenses', 'color' => 'blue', 'icon' => 'M12 1L3 5v6c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V5l-9-4z'],
                        ['title' => 'GDPR Compliant', 'desc' => 'European data protection compliance', 'color' => 'teal', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['title' => 'AML/KYC', 'desc' => 'Anti-money laundering procedures', 'color' => 'amber', 'icon' => 'M12 1L3 5v6c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V5l-9-4z'],
                        ['title' => 'PCI DSS', 'desc' => 'Payment card data security', 'color' => 'slate', 'icon' => 'M12 1L3 5v6c0 5.55 3.84 9.74 9 11 5.16-1.26 9-5.45 9-11V5l-9-4z'],
                    ];
                @endphp

                @foreach($compliance as $c)
                <div class="card-feature text-center !p-6">
                    <div class="icon-box bg-{{ $c['color'] }}-50 mx-auto mb-3">
                        <svg class="w-5 h-5 text-{{ $c['color'] }}-600" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $c['icon'] }}"/></svg>
                    </div>
                    <h3 class="font-display text-sm font-bold text-slate-900 mb-1">{{ $c['title'] }}</h3>
                    <p class="text-xs text-slate-500">{{ $c['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Experience Multi-Bank Security</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Join {{ config('brand.name', 'Zelta') }} and benefit from distributed fund protection across our trusted network of banking partners.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get Started
                </a>
                <a href="{{ route('compliance') }}" class="btn-outline px-8 py-4 text-lg">
                    View Compliance
                </a>
            </div>
        </div>
    </section>
@endsection
