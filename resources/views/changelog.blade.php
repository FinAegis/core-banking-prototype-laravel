@extends('layouts.public')

@section('title', 'Changelog | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Changelog | ' . config('brand.name', 'Zelta'),
        'description' => 'Release history for the Zelta core banking platform. Track every feature shipped, bug fixed, and improvement made — v7.0 through v7.10.',
        'keywords' => 'changelog, release notes, updates, ' . config('brand.name', 'Zelta') . ', version history, core banking',
    ])

    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Changelog', 'url' => url('/changelog')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 right-1/4 w-72 h-72 bg-teal-500/6 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-sm text-teal-400 mb-8">
                    Release History
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Changelog
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto">
                    A complete record of every release — features shipped, bugs fixed, and improvements made to the {{ config('brand.name', 'Zelta') }} core banking platform.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Timeline -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @php
                $releases = [
                    [
                        'version' => 'v7.10.0',
                        'date' => 'April 7, 2026',
                        'label' => 'Webhook Architecture Refactor',
                        'label_color' => 'blue',
                        'badge_color' => 'bg-blue-100 text-blue-700 border-blue-200',
                        'dot_color' => 'bg-blue-500',
                        'items' => [
                            'Webhook Infrastructure — webhook_endpoints table with per-user address monitoring, AlchemyWebhookManager, SmartAccountObserver, evm:sync-webhooks command, and config cleanup',
                            'Webhook Hardening — Unique (tx_hash, chain) constraint, ProcessAlchemyWebhookJob and ProcessHeliusWebhookJob for async queue-based processing, Cache-based deduplication, spam filter, and reorg detection',
                            'Per-User Sharding — Per-user webhook endpoints with encrypted signing keys and 100K address sharding for scalable on-chain monitoring',
                            'Mobile Backend — CardWaitlistController, TrustCertPaymentController with 3 payment methods (wallet, card, IAP), and RequireKycVerification middleware for Level 0 user restrictions',
                            'Ramp Migration — StripeBridgeService and generic RampWebhookController replacing Onramper with Stripe Bridge for fiat on/off-ramp',
                            'Security — bcmath for all financial amounts, encrypted stripe_client_secret, webhook timestamp tolerance, IAP production gate, 15 security findings identified and fixed via post-phase review',
                        ],
                    ],
                    [
                        'version' => 'v7.9.0',
                        'date' => 'April 1, 2026',
                        'label' => 'Compliance & Polish',
                        'label_color' => 'emerald',
                        'badge_color' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                        'dot_color' => 'bg-emerald-500',
                        'items' => [
                            'Address Screening — Multi-layer OFAC SDN + GoPlus Security API + Chainalysis sanctions checking',
                            'Website — Professional copywriting pass, fixed broken social links, consistent branding across all pages',
                            'Developer Portal — Fixed Blade compile error on code examples, corrected API URLs and env vars',
                            'SEO — Schema.org markup and breadcrumbs added to subproduct pages',
                            'Mobile Jobs — Fixed tenant context verification in global scheduled jobs',
                        ],
                    ],
                    [
                        'version' => 'v7.8.2',
                        'date' => 'April 1, 2026',
                        'label' => 'Maintenance',
                        'label_color' => 'slate',
                        'badge_color' => 'bg-slate-100 text-slate-700 border-slate-200',
                        'dot_color' => 'bg-slate-500',
                        'items' => [
                            'Registration — Mobile API signup no longer blocked by admin registration gate',
                            'Developer Portal — Honest SDK install commands, OpenAPI spec link, consolidated rate limits',
                            'Infrastructure — Daily log rotation, CRON expression fix, CORS header update',
                        ],
                    ],
                    [
                        'version' => 'v7.8.1',
                        'date' => 'March 31, 2026',
                        'label' => 'Website Polish',
                        'label_color' => 'violet',
                        'badge_color' => 'bg-violet-100 text-violet-700 border-violet-200',
                        'dot_color' => 'bg-violet-500',
                        'items' => [
                            'GCU page redesigned — migrated to unified brand layout',
                            'Platform page simplified — module cards replaced with features link',
                            'Public changelog page added at /changelog',
                        ],
                    ],
                    [
                        'version' => 'v7.8.0',
                        'date' => 'March 30, 2026',
                        'label' => 'Standards & Compliance',
                        'label_color' => 'indigo',
                        'badge_color' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                        'dot_color' => 'bg-indigo-500',
                        'items' => [
                            'ISO 20022 — Full pacs, pain, and camt message suite with standards-compliant schema validation',
                            'Open Banking PSD2 — AISP/PISP consent lifecycle, Berlin Group and UK Open Banking adapters',
                            'ISO 8583 — Card transaction messaging with PIN management and authorization flows',
                            'US Payment Rails — ACH, Fedwire, RTP, FedNow with intelligent rail routing and same-day settlement',
                            'Interledger Protocol — ILP connector, Open Payments (GNAP), and cross-currency streaming quotes',
                            'Double-Entry Ledger — Chart of accounts, journal entries, GL auto-posting, optional TigerBeetle driver',
                            'Microfinance — Group lending, IFRS provisioning, share accounts, teller operations, field officer tools',
                        ],
                    ],
                    [
                        'version' => 'v7.1.1',
                        'date' => 'March 29, 2026',
                        'label' => 'Security Patch',
                        'label_color' => 'red',
                        'badge_color' => 'bg-red-100 text-red-700 border-red-200',
                        'dot_color' => 'bg-red-500',
                        'items' => [
                            'JIT funding TOCTOU fix — Eliminated race condition in just-in-time funding that could allow double-spend',
                            'Webhook SSRF prevention — Added URL allowlist validation and DNS rebinding protection for outbound webhooks',
                            'Rate limiter hardening — Switched to atomic Cache::add + increment pattern to prevent counter bypass under concurrency',
                            'Threat model remediation — All 15 items from the v7.6 threat model audit resolved and verified',
                        ],
                    ],
                    [
                        'version' => 'v7.1.0',
                        'date' => 'March 29, 2026',
                        'label' => 'Production Hardening',
                        'label_color' => 'amber',
                        'badge_color' => 'bg-amber-100 text-amber-700 border-amber-200',
                        'dot_color' => 'bg-amber-500',
                        'items' => [
                            'Prometheus observability — Full metrics export for API latency, queue depth, error rates, and domain events',
                            'Mobile compatibility — Responsive layout fixes across dashboard, account, and GCU trading screens',
                            'Smoke test suite — Production environment smoke tests covering auth, payments, GCU, and API health checks',
                            'Security audit preparation — PHPStan Level 8 compliance, PHPCS clean, zero critical findings pre-audit',
                            'Helm chart — Kubernetes deployment chart with horizontal pod autoscaling and Redis Streams support',
                        ],
                    ],
                    [
                        'version' => 'v7.0.0',
                        'date' => 'March 28, 2026',
                        'label' => 'Production Release',
                        'label_color' => 'green',
                        'badge_color' => 'bg-green-100 text-green-700 border-green-200',
                        'dot_color' => 'bg-green-500',
                        'items' => [
                            'Web3 consolidation — Unified EthRpcClient and AbiEncoder under app/Infrastructure/Web3/ with legacy adapter shim',
                            'Zelta SDK v1.0 — Composer-installable payment SDK with transparent x402 + MPP auto-retry and fallback logic',
                            'Production guards — Demo-only service gates check app()->environment(\'production\') and throw safely',
                            'Post-quantum crypto — ML-KEM-768 and ML-DSA-65 hybrid encryption integrated into key storage and signing flows',
                            'Event sourcing v7.7+ — Domain-specific event tables, Spatie upgrade, full aggregate replay support',
                        ],
                    ],
                ];
            @endphp

            <div class="relative">
                <!-- Timeline line -->
                <div class="absolute left-6 top-0 bottom-0 w-px bg-slate-200 hidden sm:block"></div>

                <div class="space-y-16">
                    @foreach($releases as $release)
                    <div class="relative sm:pl-16">
                        <!-- Dot -->
                        <div class="absolute left-4 top-1 w-4 h-4 rounded-full border-2 border-white shadow-sm {{ $release['dot_color'] }} hidden sm:block"></div>

                        <!-- Header -->
                        <div class="flex flex-wrap items-center gap-3 mb-6">
                            <span class="font-display text-2xl font-bold text-slate-900">{{ $release['version'] }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $release['badge_color'] }}">
                                {{ $release['label'] }}
                            </span>
                            <span class="text-sm text-slate-400">{{ $release['date'] }}</span>
                        </div>

                        <!-- Items -->
                        <div class="card-feature !p-6">
                            <ul class="space-y-3">
                                @foreach($release['items'] as $item)
                                <li class="flex items-start gap-3 text-sm text-slate-600">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ $item }}
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-slate-50 border-t border-slate-100 py-16">
        <div class="max-w-3xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-2xl font-bold text-slate-900 mb-4">Stay up to date</h2>
            <p class="text-slate-500 mb-8">
                Follow releases on GitHub or star the repository to get notified when new versions ship.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ config('brand.github_url') }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-fa-navy text-white rounded-lg hover:bg-opacity-90 transition font-semibold text-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    Star on GitHub
                </a>
                <a href="{{ route('platform') }}" class="inline-flex items-center justify-center px-6 py-3 border border-slate-300 text-slate-700 rounded-lg hover:border-slate-400 transition font-semibold text-sm">
                    View Platform
                </a>
            </div>
        </div>
    </section>

@endsection
