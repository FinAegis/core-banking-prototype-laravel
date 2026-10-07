@extends('layouts.public')

@section('title', 'Bank Connector Architecture | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Bank Connector Architecture | ' . config('brand.name', 'Zelta'),
        'description' => config('brand.name', 'Zelta') . ' multi-bank architecture with pluggable bank connectors (reference implementations).',
        'keywords' => config('brand.name', 'Zelta') . ', bank connectors, multi-bank architecture, integration adapters, open banking',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Bank Connectors', 'url' => url('/partners')]
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
                @include('partials.breadcrumb', ['items' => [['name' => 'Bank Connectors', 'url' => url('/partners')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Bank <span class="text-gradient">Architecture</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Pluggable bank-connector architecture (reference implementations). Integration adapters for third-party APIs; no partnership or endorsement implied.
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
                        {{ config('brand.name', 'Zelta') }} supports a <strong>multi-bank architecture</strong> with pluggable connectors.
                        The bank connectors are reference implementations demonstrating integration patterns.
                        Integration adapters for third-party APIs; no partnership or endorsement implied.
                    </p>
                    <p class="text-sm text-slate-600 mt-1">
                        FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
                        FinAegis is a software project and does not hold any banking, e-money, payment-institution, crypto-asset service provider or token-issuer licence or authorisation.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Multi-Bank -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Why a Multi-Bank Architecture?</h2>
                <p class="text-lg text-slate-500">Design goals of the connector architecture</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-on-scroll stagger-1">
                @php
                    $benefits = [
                        ['title' => 'Pluggable Connectors', 'desc' => 'Bank and EMI APIs are integrated through pluggable connectors (reference implementations).', 'color' => 'teal', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                        ['title' => 'Improved Uptime', 'desc' => 'If one bank connector experiences issues, the other connectors remain operational.', 'color' => 'amber', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
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

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Explore the Architecture</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                The bank connectors are reference implementations in the open-source codebase.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get Started
                </a>
                <a href="{{ route('compliance') }}" class="btn-outline px-8 py-4 text-lg">
                    View Compliance Tooling
                </a>
            </div>
        </div>
    </section>
@endsection
