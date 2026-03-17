@extends('layouts.public')

@section('title', 'Compliance - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Compliance-Ready Architecture - ' . config('brand.name', 'Zelta') . '',
        'description' => config('brand.name', 'Zelta') . ' compliance-ready architecture. Built to meet EU regulatory standards with PSD2, EMD2, MiCA, KYC/AML, GDPR, and Travel Rule adapters.',
        'keywords' => config('brand.name', 'Zelta') . ', compliance, regulation, PSD2, EMD2, MiCA, KYC, AML, GDPR, security, Travel Rule, MiFID II',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Compliance', 'url' => url('/compliance')]
    ]" />
@endsection

@section('content')
    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 left-1/4 w-80 h-80 bg-blue-500/8 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-teal-500/6 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/[0.04] border border-white/[0.08] rounded-2xl mb-6">
                    <svg class="w-8 h-8 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                @include('partials.breadcrumb', ['items' => [['name' => 'Compliance', 'url' => url('/compliance')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Compliance-Ready <span class="text-gradient">Architecture</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Built for EU regulatory compliance from day one. PSD2, EMD2, MiCA, KYC/AML, GDPR, and MiFID II adapters with jurisdiction-aware routing.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Regulatory Strategy Notice -->
    <section class="py-6 bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-stat flex items-start gap-3 border-l-4 border-teal-400 bg-teal-50/50">
                <svg class="w-5 h-5 text-teal-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="font-display text-sm font-semibold text-slate-900">Designed for EU Regulatory Compliance</h3>
                    <p class="text-sm text-slate-600 mt-0.5">
                        Our platform architecture supports EMI licensing requirements and integrates with licensed partners for compliant operations across the European Union.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Primary Regulations -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Regulatory Framework Support</h2>
                <p class="text-lg text-slate-500">Comprehensive adapters for EU financial regulations</p>
            </div>

            @php
                $regulations = [
                    ['title' => 'PSD2 Compatible', 'desc' => 'Architecture designed to support Payment Services Directive 2 requirements when operating with licensed partners.', 'color' => 'blue', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'items' => ['Strong Customer Authentication (SCA)', 'Open Banking API standards', 'Account Information Services (AIS)', 'Payment Initiation Services (PIS)']],
                    ['title' => 'EMD2 Ready', 'desc' => 'Platform architecture prepared for Electronic Money Directive 2 compliance requirements.', 'color' => 'teal', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'items' => ['Support for fund safeguarding', 'Customer fund segregation', 'E-money redemption workflows', 'Operational resilience features']],
                    ['title' => 'MiCA Compatible', 'desc' => 'Infrastructure designed with Markets in Crypto-Assets regulation in mind for digital asset operations.', 'color' => 'amber', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', 'items' => ['Asset-referenced token framework', 'E-money token compliance', 'Reserve asset management', 'Stability mechanism requirements']],
                    ['title' => 'KYC/AML Framework', 'desc' => 'Multi-tier identity verification with Ondato integration and Chainalysis sanctions screening.', 'color' => 'blue', 'icon' => 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2', 'items' => ['National ID & passport verification', 'Liveness check & selfie matching', 'PEP & sanctions screening', 'Automated risk scoring']],
                    ['title' => 'GDPR Enhanced', 'desc' => 'Full data protection framework with ROPA, DPIA, breach notification, and consent management v2.', 'color' => 'teal', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'items' => ['Full data portability & export', 'Right to deletion & anonymization', 'Automated retention policies', 'Granular consent management']],
                    ['title' => 'MiFID II & Travel Rule', 'desc' => 'Financial instruments reporting and crypto transfer data requirements with jurisdiction adapters.', 'color' => 'slate', 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3', 'items' => ['Transaction reporting adapters', 'Jurisdiction-aware routing', 'Travel Rule VASP compliance', 'Regulatory report generation']],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($regulations as $i => $reg)
                <div class="card-feature !p-6 animate-on-scroll stagger-{{ ($i % 6) + 1 }}">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="icon-box bg-{{ $reg['color'] }}-50">
                            <svg class="w-5 h-5 text-{{ $reg['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $reg['icon'] }}"/></svg>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900">{{ $reg['title'] }}</h3>
                    </div>
                    <p class="text-sm text-slate-500 mb-4 leading-relaxed">{{ $reg['desc'] }}</p>
                    <ul class="space-y-2 text-sm text-slate-600">
                        @foreach($reg['items'] as $item)
                        <li class="list-check">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- KYC Tiers -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">KYC/AML System Architecture</h2>
                <p class="text-lg text-slate-500">Multi-tier verification with progressive access levels</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-on-scroll stagger-1">
                @php
                    $tiers = [
                        ['level' => '1', 'name' => 'Basic Verification', 'color' => 'blue', 'limit' => '10,000', 'checks' => ['National ID verification', 'Selfie with liveness check', 'Automated risk scoring']],
                        ['level' => '2', 'name' => 'Enhanced Verification', 'color' => 'teal', 'limit' => '50,000', 'checks' => ['Passport verification', 'Proof of address', 'PEP & sanctions screening']],
                        ['level' => '3', 'name' => 'Full Verification', 'color' => 'amber', 'limit' => 'Unlimited', 'checks' => ['All previous checks', 'Source of funds verification', 'Enhanced due diligence']],
                    ];
                @endphp

                @foreach($tiers as $tier)
                <div class="card-feature !p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-display text-lg font-bold text-slate-900">{{ $tier['name'] }}</h3>
                        <span class="badge badge-{{ $tier['color'] === 'amber' ? 'warning' : ($tier['color'] === 'teal' ? 'success' : 'info') }}">Level {{ $tier['level'] }}</span>
                    </div>
                    <ul class="space-y-2.5 text-sm text-slate-600 mb-6">
                        @foreach($tier['checks'] as $check)
                        <li class="list-check">{{ $check }}</li>
                        @endforeach
                    </ul>
                    <div class="pt-4 border-t border-slate-100">
                        <div class="text-xs text-slate-500 uppercase tracking-wider mb-1">Daily Limit</div>
                        <div class="font-display text-2xl font-bold text-slate-900">{{ $tier['limit'] === 'Unlimited' ? $tier['limit'] : '€' . $tier['limit'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- AML Framework -->
    <section class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Anti-Money Laundering Framework</h2>
                <p class="text-lg text-slate-500">Real-time monitoring with automated regulatory reporting</p>
            </div>

            <div class="card-feature !p-8 animate-on-scroll stagger-1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div>
                        <h3 class="font-display text-lg font-bold text-slate-900 mb-5">Real-Time Transaction Monitoring</h3>
                        <ul class="space-y-4">
                            @php
                                $monitoring = [
                                    ['title' => 'Pattern Detection', 'desc' => 'Structuring, velocity, and unusual patterns'],
                                    ['title' => 'Threshold Monitoring', 'desc' => '€10,000 CTR threshold tracking'],
                                    ['title' => 'Risk Scoring', 'desc' => 'ML-based transaction risk assessment'],
                                ];
                            @endphp
                            @foreach($monitoring as $item)
                            <li class="flex items-start gap-3">
                                <div class="icon-box bg-blue-50 mt-0.5">
                                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </div>
                                <div>
                                    <div class="font-display text-sm font-semibold text-slate-900">{{ $item['title'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $item['desc'] }}</div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <h3 class="font-display text-lg font-bold text-slate-900 mb-5">Automated Regulatory Reporting</h3>
                        <ul class="space-y-4">
                            @php
                                $reporting = [
                                    ['title' => 'Currency Transaction Reports (CTR)', 'desc' => 'Daily automated generation'],
                                    ['title' => 'Suspicious Activity Reports (SAR)', 'desc' => 'Monthly candidate identification'],
                                    ['title' => 'Compliance Dashboards', 'desc' => 'Real-time metrics and alerts'],
                                ];
                            @endphp
                            @foreach($reporting as $item)
                            <li class="flex items-start gap-3">
                                <div class="icon-box bg-teal-50 mt-0.5">
                                    <svg class="w-4 h-4 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </div>
                                <div>
                                    <div class="font-display text-sm font-semibold text-slate-900">{{ $item['title'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $item['desc'] }}</div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- GDPR & Data Protection -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <span class="badge badge-success mb-4">Implemented v3.5.0</span>
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">GDPR & Data Protection</h2>
                <p class="text-lg text-slate-500">Full European data protection compliance</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 animate-on-scroll stagger-1">
                @php
                    $gdpr = [
                        ['title' => 'Data Export', 'desc' => 'Full data portability support', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['title' => 'Right to Deletion', 'desc' => 'Secure data anonymization', 'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
                        ['title' => 'Retention Policies', 'desc' => 'Automated data lifecycle', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['title' => 'Consent Management', 'desc' => 'Granular privacy controls', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                    ];
                @endphp

                @foreach($gdpr as $item)
                <div class="card-feature text-center !p-6">
                    <div class="icon-box-lg bg-blue-50 mx-auto mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/></svg>
                    </div>
                    <h4 class="font-display text-sm font-bold text-slate-900 mb-1">{{ $item['title'] }}</h4>
                    <p class="text-xs text-slate-500">{{ $item['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Banking Integration Examples -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Example Banking Integrations</h2>
                <p class="text-lg text-slate-500">Reference implementations demonstrating integration patterns</p>
            </div>

            <div class="card-stat flex items-start gap-3 border-l-4 border-amber-400 bg-amber-50/50 mb-8 animate-on-scroll">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-slate-600">
                    <strong>Disclaimer:</strong> These are example integrations demonstrating technical capabilities, not active partnerships or endorsements.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 animate-on-scroll stagger-1">
                @php
                    $integrations = [
                        ['name' => 'Paysera Connector', 'type' => 'EMI Integration Example', 'items' => ['API integration ready', 'Multi-currency support', 'SEPA payments', 'PSD2 compatible APIs']],
                        ['name' => 'Deutsche Bank API', 'type' => 'Banking Integration Demo', 'items' => ['Corporate API access', 'Multi-currency accounts', 'SWIFT connectivity', 'SEPA Instant support']],
                        ['name' => 'Santander Module', 'type' => 'Open Banking Sample', 'items' => ['Open Banking APIs', 'Account aggregation', 'Payment initiation', 'Real-time balances']],
                        ['name' => 'Custom Integrations', 'type' => 'Your Bank Here', 'items' => ['Modular architecture', 'Standard interfaces', 'Webhook support', 'Easy integration']],
                    ];
                @endphp

                @foreach($integrations as $int)
                <div class="card-feature !p-5">
                    <span class="badge badge-info mb-3">Example</span>
                    <h4 class="font-display text-base font-bold text-slate-900 mb-1">{{ $int['name'] }}</h4>
                    <p class="text-xs text-slate-500 mb-3">{{ $int['type'] }}</p>
                    <ul class="space-y-1.5 text-xs text-slate-500">
                        @foreach($int['items'] as $item)
                        <li class="list-check">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>

            <p class="text-center text-sm text-slate-500 mt-6 animate-on-scroll">
                These reference implementations demonstrate integration patterns. No partnerships or endorsements are implied.
            </p>
        </div>
    </section>

    <!-- API Endpoints -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Compliance API Endpoints</h2>
                <p class="text-lg text-slate-500">Programmatic access to compliance features</p>
            </div>

            <div class="card-feature !p-0 overflow-hidden animate-on-scroll stagger-1">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                    <p class="text-sm text-slate-600">
                        <strong class="text-slate-900">Note:</strong> For api.finaegis.org, endpoints are available without the /api prefix.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                    @php
                        $endpoints = [
                            ['title' => 'KYC Endpoints', 'routes' => ['GET /api/compliance/kyc/status', 'GET /api/compliance/kyc/requirements', 'POST /api/compliance/kyc/submit', 'POST /api/compliance/kyc/documents']],
                            ['title' => 'GDPR Endpoints', 'routes' => ['GET /api/compliance/gdpr/consent', 'POST /api/compliance/gdpr/consent', 'POST /api/compliance/gdpr/export', 'POST /api/compliance/gdpr/delete']],
                            ['title' => 'Regulatory Reporting', 'routes' => ['POST /api/regulatory/reports/ctr', 'POST /api/regulatory/reports/sar-candidates', 'GET /api/regulatory/reports', 'GET /api/regulatory/metrics']],
                            ['title' => 'Bank Health & Alerting', 'routes' => ['POST /api/bank-health/check', 'GET /api/bank-health/status', 'GET /api/bank-health/alerts/stats', 'PUT /api/bank-health/alerts/config']],
                        ];
                    @endphp
                    @foreach($endpoints as $group)
                    <div class="p-6">
                        <h4 class="font-display text-sm font-bold text-slate-900 mb-3">{{ $group['title'] }}</h4>
                        <ul class="space-y-2 text-xs text-slate-600 font-mono">
                            @foreach($group['routes'] as $route)
                            <li>{{ $route }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- Security Standards -->
    <section class="py-20 bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Security Architecture</h2>
                <p class="text-slate-400">Industry-standard security practices</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 animate-on-scroll stagger-1">
                @php
                    $standards = [
                        ['label' => 'OWASP', 'desc' => 'Security Guidelines'],
                        ['label' => 'AES-256', 'desc' => 'Data Encryption'],
                        ['label' => 'OAuth 2.0', 'desc' => 'API Security'],
                        ['label' => 'bcrypt', 'desc' => 'Password Hashing'],
                    ];
                @endphp
                @foreach($standards as $std)
                <div class="text-center">
                    <div class="font-display text-xl font-bold text-white mb-1">{{ $std['label'] }}</div>
                    <div class="text-sm text-slate-400">{{ $std['desc'] }}</div>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-12 animate-on-scroll stagger-2">
                @php
                    $metrics = [
                        ['label' => 'Real-time', 'desc' => 'Transaction Logging'],
                        ['label' => 'Automated', 'desc' => 'Risk Scoring'],
                        ['label' => 'Built-in', 'desc' => 'Audit Trail'],
                        ['label' => 'Flexible', 'desc' => 'KYC Workflows'],
                    ];
                @endphp
                @foreach($metrics as $m)
                <div class="card-dark text-center !p-5">
                    <div class="font-display text-lg font-bold text-white mb-1">{{ $m['label'] }}</div>
                    <div class="text-xs text-slate-400">{{ $m['desc'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 animate-on-scroll">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Learn More About Our Compliance Architecture</h2>
            <p class="text-lg text-slate-500 mb-10 max-w-2xl mx-auto">
                Interested in how our platform can support your compliance requirements? Contact us to learn more.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('support.contact') }}" class="btn-primary px-8 py-4 text-lg">
                    Contact Us
                </a>
                <a href="{{ route('developers.show', 'api-docs') }}" class="btn-outline-dark px-8 py-4 text-lg">
                    View API Documentation
                </a>
            </div>
        </div>
    </section>
@endsection
