@extends('layouts.public')

@section('title', 'Security Architecture | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Security Architecture | ' . config('brand.name', 'Zelta'),
        'description' => 'How the ' . config('brand.name', 'Zelta') . ' open-source code protects data, keys and sign-ins. It covers tamper checks, key splitting, passkeys, zero-knowledge proofs and readiness tooling for security audits. No certification is held.',
        'keywords' => config('brand.name', 'Zelta') . ' security, blockchain security, cybersecurity, data protection',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="service" :data="[
        'name' => config('brand.name', 'Zelta') . ' Security',
        'description' => 'Security architecture of the ' . config('brand.name', 'Zelta') . ' open-source platform',
        'category' => 'Software Security'
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
                    The platform protects data in several layers. It signs records with keyed hashes to detect tampering, and it can keep keys in hardware security modules or split them with Shamir secret sharing. Every security event is written to an audit log.
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
                        This page lists the security patterns in the open-source code. Each feature is marked as <strong>implemented</strong> or <strong>planned</strong>. Run your own security review before you use the code in production.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enterprise Security Standards -->
    <section class="py-24 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14 animate-on-scroll">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Security Controls</h2>
                <p class="text-lg text-slate-500">These controls cover the infrastructure and the application.</p>
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
                        <li class="list-check">Encryption in transit, set up by the operator</li>
                        <li class="list-check">Rate limiting and automatic blocking of network addresses</li>
                        <li class="list-check">Data kept in separate regions when configured</li>
                        <li class="list-check">ISO 27001 readiness tooling, with no certification held</li>
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
                        <li class="list-check">Two-factor sign-in</li>
                        <li class="list-check">Password rules</li>
                        <li class="list-check">Session limits and timeouts</li>
                        <li class="list-check">Protection against forged requests and script injection</li>
                        <li class="list-check">Protection against database injection</li>
                        <li class="list-check">Token sign-in and rate limits for the API</li>
                    </ul>
                </div>
            </div>

            <!-- Compliance -->
            <div class="card-feature !p-6 animate-on-scroll stagger-2">
                <div class="flex items-center gap-3 mb-5">
                    <div class="icon-box bg-slate-100">
                        <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-display text-lg font-bold text-slate-900">Compliance and Standards</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @php
                        $standards = [
                            ['title' => 'Data Protection Tooling', 'desc' => 'Tools for the European Union General Data Protection Regulation keep records of processing, impact assessments, breach notices, consent and retention rules.'],
                            ['title' => 'SOC 2 Type II Readiness', 'desc' => 'The tooling monitors controls and collects evidence for an audit. No certification is held.'],
                            ['title' => 'Card Data Security Readiness', 'desc' => 'The tooling helps prepare for an audit against the Payment Card Industry Data Security Standard. It helps with scoping, gap analysis and tracking fixes. No certification is held.'],
                            ['title' => 'Compliance Tooling', 'desc' => 'Reporting modules follow the formats of the Markets in Financial Instruments Directive, the Markets in Crypto-Assets Regulation and the Travel Rule. Workflow modules support anti-money laundering and identity checks.'],
                            ['title' => 'Data Residency', 'desc' => 'Settings let an operator keep data in separate regions.'],
                            ['title' => 'ISO 27001 Readiness', 'desc' => 'The tooling helps prepare for an ISO 27001 audit. No certification is held.'],
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
                <p class="text-lg text-slate-500">These security measures are implemented in the code.</p>
            </div>

            @php
                $features = [
                    ['title' => 'Performance Monitoring', 'desc' => 'Scheduled checks track performance and system health every five minutes.', 'color' => 'teal', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['title' => 'Two-Factor Authentication', 'desc' => 'Every user can turn on two-factor sign-in. Administrator accounts have extra security options.', 'color' => 'blue', 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
                    ['title' => 'Rate Limiting', 'desc' => 'Each type of request has its own limit, and trusted users get higher limits. This slows down flooding and password guessing.', 'color' => 'slate', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['title' => 'Address Blocking', 'desc' => 'A network address is blocked after 10 failed attempts. Blocks can be temporary or permanent.', 'color' => 'red', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'],
                    ['title' => 'Session Security', 'desc' => 'A user can have three sessions at a time by default. Older sessions are closed automatically.', 'color' => 'amber', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['title' => 'Audit Logging', 'desc' => 'Transactions and security events are written to an audit trail.', 'color' => 'blue', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['title' => 'Biometric Authentication', 'desc' => 'The mobile app can sign people in with a fingerprint or face scan. The server then issues a signed token.', 'color' => 'teal', 'icon' => 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4', 'badge' => 'v2.2.0'],
                    ['title' => 'Hardware Wallets', 'desc' => 'Ledger and Trezor hardware wallets can sign transactions, so the private key never leaves the device.', 'color' => 'slate', 'icon' => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', 'badge' => 'v2.1.0'],
                    ['title' => 'Zero-Knowledge Proofs', 'desc' => 'Zero-knowledge proofs let a person prove identity facts without showing documents. The module also supports proofs that funds are not linked to sanctioned sources, and Merkle tree commitments.', 'color' => 'teal', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'badge' => 'v2.4.0'],
                    ['title' => 'Passkey Authentication', 'desc' => 'People can sign in with a passkey instead of a password. Passkeys resist phishing.', 'color' => 'blue', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z', 'badge' => 'v2.7.0'],
                    ['title' => 'SOC 2 Type II Readiness Tooling', 'desc' => 'The tooling monitors controls and collects evidence for an audit. No certification is held.', 'color' => 'teal', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'badge' => 'v3.5.0'],
                    ['title' => 'Passkey Checks', 'desc' => 'Each passkey sign-in checks the site identifier, the user presence and verification flags, the key algorithm and the origin.', 'color' => 'slate', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'badge' => 'v5.9.0'],
                    ['title' => 'Token Permissions', 'desc' => 'Each API token carries read, write or delete permissions. A middleware checks the permission for every request, and the test suite checks it too.', 'color' => 'blue', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'badge' => 'v5.12.0'],
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
                            ['title' => 'Fraud Detection with Machine Learning', 'desc' => 'Machine learning models will score transactions for fraud as they happen.', 'label' => 'In Development'],
                            ['title' => 'Standard Post-Quantum Algorithms', 'desc' => 'The current post-quantum layer is a hybrid stand-in built from X25519, Ed25519 and a hash-based layer. It is not yet a certified ML-KEM or ML-DSA implementation. It will move to the standard algorithms when PHP supports them.', 'label' => 'Planned'],
                            ['title' => 'Faster Monitoring', 'desc' => 'Health checks will run more often than every five minutes.', 'label' => 'Planned'],
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
                <p class="text-lg text-slate-500">These habits keep your account safe.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-on-scroll stagger-1">
                <div class="card-feature !p-6 border-l-4 !border-l-teal-500">
                    <h3 class="font-display text-base font-bold text-slate-900 mb-4">Do's</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li class="list-check">Turn on two-factor sign-in</li>
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
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>Use public Wi-Fi for banking</li>
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Report a Security Issue</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                If you find a security issue, please report it privately and not in a public issue. The security policy on GitHub explains how.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ config('brand.github_url') }}/security" target="_blank" rel="noopener" class="btn-primary px-8 py-4 text-lg">
                    Report a Vulnerability
                </a>
                <a href="{{ route('compliance') }}" class="btn-outline px-8 py-4 text-lg">
                    View Compliance
                </a>
            </div>
        </div>
    </section>
@endsection
