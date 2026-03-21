@extends('layouts.public')

@section('title', 'Visa CLI - Programmatic Card Payments | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Visa CLI - Programmatic Card Payments for AI Agents',
        'description' => 'Enable AI agents and developers to make programmatic Visa card payments. MCP tools, spending limits, invoice collection, and card enrollment.',
        'keywords' => 'visa cli, programmatic payments, ai agent payments, visa card api, mcp tools, spending limits, invoice payments',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Visa CLI', 'url' => url('/features/visa-cli')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/10 border border-blue-500/20 rounded-full text-sm text-blue-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Beta Integration
                    </span>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'Visa CLI', 'url' => url('/features/visa-cli')]
                ]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Visa CLI <span class="text-gradient">Payment Rail</span>
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto mb-10">
                    Programmatic Visa card payments for AI agents and developer billing.
                    Pay for APIs, datasets, and services on demand — no API keys to manage.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">How It Works</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Three steps from request to payment — fully automated for AI agents.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">1</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Agent Requests Payment</h3>
                    <p class="text-slate-500">AI agent or developer invokes the <code class="text-sm bg-slate-100 px-1 rounded">visacli.payment</code> MCP tool with a target URL and amount.</p>
                </div>
                <div class="text-center p-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">2</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Spending Limit Check</h3>
                    <p class="text-slate-500">Per-agent daily and per-transaction limits enforced atomically with row-level locking. No budget overruns.</p>
                </div>
                <div class="text-center p-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">3</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Visa Payment Executed</h3>
                    <p class="text-slate-500">Payment processed via enrolled Visa card. Full event-sourced audit trail with immutable payment records.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Capabilities -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Core Capabilities</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">MCP Tools for AI Agents</h3>
                    <p class="text-slate-500 text-sm"><code>visacli.payment</code> and <code>visacli.cards</code> — registered in the MCP tool registry for autonomous AI agent workflows.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Spending Limits</h3>
                    <p class="text-slate-500 text-sm">Per-agent daily budgets with atomic row-level locking. Auto-pay controls and per-transaction caps prevent runaway spending.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Invoice Payment Gateway</h3>
                    <p class="text-slate-500 text-sm">Collect partner billing payments via <code>POST /billing/invoices/{id}/pay</code>. Fills the missing payment rail in the BaaS billing system.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Card Enrollment Bridge</h3>
                    <p class="text-slate-500 text-sm">Enroll Visa CLI cards and sync them to the CardIssuance domain. Event-driven DDD boundary sync maintains a unified card view.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Event-Sourced Audit Trail</h3>
                    <p class="text-slate-500 text-sm">Every payment dispatches <code>ShouldBeStored</code> events — PaymentInitiated, Completed, Failed — for immutable financial audit.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Artisan CLI Commands</h3>
                    <p class="text-slate-500 text-sm"><code>visa:status</code>, <code>visa:enroll</code>, <code>visa:pay</code> — manage the Visa CLI integration from the command line.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Quick Start</h2>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div>
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Enable in .env</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>VISACLI_ENABLED=true
VISACLI_DRIVER=demo          # or "process" for real binary
VISACLI_DAILY_LIMIT=10000    # $100.00 daily limit
VISACLI_PER_TX_LIMIT=1000    # $10.00 per transaction</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div>
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Check status</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>php artisan visa:status</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div>
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Make a payment</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>php artisan visa:pay https://api.example.com/data --amount=500 --agent=my-agent</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Ready to integrate?</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Visa CLI works alongside <a href="{{ route('features.show', 'x402-protocol') }}" class="text-blue-400 hover:underline">x402 Protocol</a> — choose the payment rail that fits your use case.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary">Developer Docs</a>
                <a href="{{ url('/demo') }}" class="btn-outline-light">Try Demo</a>
            </div>
        </div>
    </section>

@endsection
