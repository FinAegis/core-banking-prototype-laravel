@extends('layouts.public')

@section('title', 'Security - Bank-Grade Protection | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Security - Bank-Grade Protection | ' . config('brand.name', 'Zelta') . '',
        'description' => config('brand.name', 'Zelta') . ' security overview - Bank-grade security meets blockchain immutability. Learn about our security measures and best practices.',
        'keywords' => config('brand.name', 'Zelta') . ' security, bank-grade security, blockchain security, secure banking, cybersecurity, data protection',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="service" :data="[
        'name' => config('brand.name', 'Zelta') . ' Security',
        'description' => 'Bank-grade security for the ' . config('brand.name', 'Zelta') . ' platform',
        'category' => 'Financial Security'
    ]" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Security', 'url' => url('/security')]
    ]" />
@endsection

@section('content')
    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 right-1/4 w-80 h-80 bg-teal-500/8 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/[0.04] border border-white/[0.08] rounded-2xl mb-6">
                    <svg class="w-8 h-8 text-teal-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                    </svg>
                </div>
                @include('partials.breadcrumb', ['items' => [['name' => 'Security', 'url' => url('/security')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Security <span class="text-gradient">Architecture</span></h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    Multi-layered security with HMAC integrity verification, HSM key management, Shamir secret sharing, and comprehensive audit trails.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Status Notice -->
    <section class="py-6 bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="card-stat flex items-start gap-3 border-l-4 border-amber-400 bg-amber-50/50">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="font-display text-sm font-semibold text-slate-900">Security Implementation Status</h3>
                    <p class="text-sm text-slate-600 mt-0.5">
                        Production-grade security patterns throughout. Features below are marked as <strong>implemented</strong> or <strong>planned</strong>.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enterprise Security Standards -->
    <section class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Enterprise Security Standards</h2>
                <p class="text-lg text-slate-500">Comprehensive security at every level</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 animate-on-scroll stagger-1">
                <!-- Infrastructure -->
                <div class="card-feature !p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="icon-box bg-teal-50">
                            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900">Infrastructure Security</h3>
                    </div>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li class="list-check">End-to-end encryption (TLS 1.3)</li>
                        <li class="list-check">DDoS protection & rate limiting</li>
                        <li class="list-check">Multi-region data redundancy</li>
                        <li class="list-check">24/7 security monitoring</li>
                        <li class="list-check">Regular penetration testing</li>
                        <li class="list-check">ISO 27001 compliance ready</li>
                    </ul>
                </div>

                <!-- Application -->
                <div class="card-feature !p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="icon-box bg-blue-50">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="font-display text-lg font-bold text-slate-900">Application Security</h3>
                    </div>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li class="list-check">Multi-factor authentication (2FA)</li>
                        <li class="list-check">Advanced password policies</li>
                        <li class="list-check">Session security & timeout</li>
                        <li class="list-check">CSRF & XSS protection</li>
                        <li class="list-check">SQL injection prevention</li>
                        <li class="list-check">API authentication & rate limiting</li>
                    </ul>
                </div>
            </div>

            <!-- Compliance -->
            <div class="card-feature !p-6 animate-on-scroll stagger-2">
                <div class="flex items-center gap-3 mb-5">
                    <div class="icon-box bg-slate-100">
                        <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900">Compliance & Standards</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @php
                        $standards = [
                            ['title' => 'GDPR Enhanced', 'desc' => 'ROPA, DPIA, breach notification, consent management v2, and data retention policies (v3.5.0)'],
                            ['title' => 'SOC 2 Type II', 'desc' => 'Continuous control monitoring, evidence collection, and audit readiness tooling (v3.5.0)'],
                            ['title' => 'PCI DSS Readiness', 'desc' => 'Payment card industry compliance with scoping, gap analysis, and remediation tracking (v3.5.0)'],
                            ['title' => 'Financial Compliance', 'desc' => 'KYC/AML procedures, MiFID II, MiCA, and Travel Rule regulatory reporting'],
                            ['title' => 'Multi-Region Deploy', 'desc' => 'Data sovereignty compliance with multi-region deployment support (v3.5.0)'],
                            ['title' => 'Industry Standards', 'desc' => 'ISO 27001 readiness and comprehensive security framework alignment'],
                        ];
                    @endphp
                    @foreach($standards as $item)
                    <div class="card-stat">
                        <h4 class="font-display text-sm font-semibold text-slate-900 mb-1">{{ $item['title'] }}</h4>
                        <p class="text-xs text-slate-500 leading-relaxed">{{ $item['desc'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- Implemented Features -->
    <section class="py-24 bg-slate-50/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <span class="badge badge-success mb-4">Currently Implemented</span>
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Security Features</h2>
                <p class="text-lg text-slate-500">Production-ready security measures</p>
            </div>

            @php
                $features = [
                    ['title' => 'Performance Monitoring', 'desc' => 'Near real-time system monitoring with 5-minute granularity, tracking performance metrics and system health.', 'color' => 'teal', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['title' => 'Two-Factor Authentication', 'desc' => 'Available for all users with enhanced security options for administrative accounts.', 'color' => 'blue', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                    ['title' => 'Advanced Rate Limiting', 'desc' => 'Dynamic rate limiting with user trust levels and tier-aware throttling against DDoS and brute force.', 'color' => 'slate', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['title' => 'IP Blocking', 'desc' => 'Automatic IP blocking after 10 failed attempts, with temporary and permanent blacklist support.', 'color' => 'red', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
                    ['title' => 'Session Security', 'desc' => 'Maximum 5 concurrent sessions per user with automatic cleanup of old sessions.', 'color' => 'amber', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['title' => 'Audit Logging', 'desc' => 'Comprehensive audit trails for all transactions and security-relevant events.', 'color' => 'blue', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['title' => 'Biometric Authentication', 'desc' => 'Fingerprint and facial recognition via BiometricAuthenticationService with JWT-based biometric tokens.', 'color' => 'teal', 'icon' => 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4', 'badge' => 'v2.2.0'],
                    ['title' => 'Hardware Security Keys', 'desc' => 'FIDO2/WebAuthn hardware wallet support via HardwareWalletManager with Ledger and Trezor signing.', 'color' => 'slate', 'icon' => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', 'badge' => 'v2.1.0'],
                    ['title' => 'Zero-Knowledge Proofs', 'desc' => 'Privacy-preserving ZK-KYC verification, Proof of Innocence, Merkle tree commitments, and delegated proofs.', 'color' => 'teal', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'badge' => 'v2.4.0'],
                    ['title' => 'Passkey Authentication', 'desc' => 'Passwordless authentication using FIDO2 passkeys for seamless, phishing-resistant login.', 'color' => 'blue', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'badge' => 'v2.7.0'],
                    ['title' => 'SOC 2 Type II Compliance', 'desc' => 'Continuous control monitoring, evidence collection, and audit readiness tooling.', 'color' => 'teal', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'badge' => 'v3.5.0'],
                    ['title' => 'WebAuthn Hardened', 'desc' => 'rpIdHash, UP/UV flags, COSE alg/curve validation, origin checking — full FIDO2 specification compliance.', 'color' => 'slate', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'badge' => 'v5.9.0'],
                    ['title' => 'Scope-Based CI Enforcement', 'desc' => 'Comprehensive Sanctum ability enforcement across all test files — read/write/delete scopes validated in CI for every endpoint.', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'badge' => 'v5.12.0'],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($features as $i => $feat)
                <div class="card-feature animate-on-scroll stagger-{{ ($i % 6) + 1 }}">
                    <div class="flex items-start gap-4">
                        <div class="icon-box bg-{{ $feat['color'] }}-50 mt-0.5">
                            <svg class="w-5 h-5 text-{{ $feat['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feat['icon'] }}"/></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-display text-base font-bold text-slate-900 mb-1">{{ $feat['title'] }}</h3>
                            @if(!empty($feat['badge']))
                            <span class="badge badge-success mb-2">Implemented {{ $feat['badge'] }}</span>
                            @endif
                            <p class="text-sm text-slate-500 leading-relaxed">{{ $feat['desc'] }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Roadmap -->
            <div class="mt-16 animate-on-scroll">
                <div class="text-center mb-10">
                    <span class="badge badge-warning mb-4">On Our Roadmap</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    @php
                        $roadmap = [
                            ['title' => 'AI Fraud Detection', 'desc' => 'Machine learning models for real-time fraud detection and prevention.', 'label' => 'In Development'],
                            ['title' => '24/7 Security Operations', 'desc' => 'Dedicated security operations center for incident response.', 'label' => 'Future'],
                            ['title' => 'Real-time Monitoring', 'desc' => 'Enhance monitoring from 5-minute to sub-second granularity.', 'label' => 'Upgrade Planned'],
                        ];
                    @endphp
                    @foreach($roadmap as $item)
                    <div class="card-feature opacity-70">
                        <h3 class="font-display text-base font-bold text-slate-900 mb-1">{{ $item['title'] }}</h3>
                        <span class="badge badge-warning mb-2">{{ $item['label'] }}</span>
                        <p class="text-sm text-slate-500">{{ $item['desc'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- User Security Tips -->
    <section class="py-24 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Protect Your Account</h2>
                <p class="text-lg text-slate-500">Best practices to keep your account secure</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-on-scroll stagger-1">
                <div class="card-feature !p-6 border-l-4 !border-l-teal-500">
                    <h3 class="font-display text-base font-bold text-slate-900 mb-4">Do's</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li class="list-check">Enable two-factor authentication (2FA)</li>
                        <li class="list-check">Use a unique, strong password</li>
                        <li class="list-check">Verify email sender addresses</li>
                        <li class="list-check">Keep your devices updated</li>
                        <li class="list-check">Review account activity regularly</li>
                    </ul>
                </div>

                <div class="card-feature !p-6 border-l-4 !border-l-red-400">
                    <h3 class="font-display text-base font-bold text-slate-900 mb-4">Don'ts</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Share your password or API keys</li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Click on suspicious links</li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Use public WiFi for banking</li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Install unverified browser extensions</li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Ignore security warnings</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Security First Approach</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                We take security seriously. Our team works around the clock to ensure your assets and data are protected.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('support') }}" class="btn-primary px-8 py-4 text-lg">
                    Contact Security Team
                </a>
                <a href="{{ route('compliance') }}" class="btn-outline px-8 py-4 text-lg">
                    View Compliance
                </a>
            </div>
        </div>
    </section>
@endsection
