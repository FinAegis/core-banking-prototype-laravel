@extends('layouts.public')

@section('title', 'Card-Payment Command-Line Adapter | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Card-Payment Command-Line Adapter | ' . config('brand.name', 'Zelta'),
        'description' => 'This adapter connects a third-party card-payment command-line tool. AI agents can use it to pay for APIs by card, within spending limits and with a full audit trail.',
        'keywords' => 'card-payment command-line adapter, ai agent payments, Model Context Protocol tools, spending limits, invoice payments, agent commerce',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Card-Payment Adapter', 'url' => url('/features/visa-cli')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 -right-32 w-80 h-80 bg-blue-500/10 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-blue-500/10 border border-blue-500/20 rounded-full text-sm text-blue-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        v7.18.1
                    </span>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'Card-Payment Adapter', 'url' => url('/features/visa-cli')]
                ]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Card-Payment <span class="text-gradient">Adapter</span>
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto mb-10">
                    This adapter lets AI agents pay for APIs and services by card. It works through visa-cli, a third-party command-line tool for card payments. The tool is not affiliated with or endorsed by Visa. Each agent has its own budget. Parallel requests cannot push an agent over its spending limit. Every payment is kept in an audit trail. Integration adapters for third-party APIs; no partnership or endorsement implied.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-8">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        2 agent tools
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        3 console commands
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Unit and integration tests
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Demo and live drivers
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">How It Works</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">A payment goes through three steps. The agent does not need a person to help, and compliance teams can audit every step.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">1</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Agent Requests Payment</h3>
                    <p class="text-slate-500 text-sm">An AI agent calls the <code class="text-xs bg-white px-1.5 py-0.5 rounded border border-slate-200">visacli.payment</code> tool over the Model Context Protocol. A developer can instead call <code class="text-xs bg-white px-1.5 py-0.5 rounded border border-slate-200">POST /billing/invoices/{id}/pay</code>.</p>
                </div>
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">2</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Budget Reserved</h3>
                    <p class="text-slate-500 text-sm">The adapter checks the spending limit and reserves the amount while it holds a lock on the database row. Two requests from the same agent at the same time cannot go over the daily budget.</p>
                </div>
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">3</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Payment Settles</h3>
                    <p class="text-slate-500 text-sm">The payment is made with the card that is enrolled in the third-party tool. Each change of status is stored as a <code class="text-xs bg-white px-1.5 py-0.5 rounded border border-slate-200">ShouldBeStored</code> event that cannot be changed, so it can be audited later.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">What Agents Pay For</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">The adapter is meant for AI agents that buy services on their own.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">API-Metered Services</h3>
                    <p class="text-slate-500 text-sm">The agent can pay for any API that charges per call. Examples are image generation, large language models, speech synthesis and translation. The agent decides when to buy and what to buy.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-sky-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Datasets &amp; Research</h3>
                    <p class="text-slate-500 text-sm">A research pipeline can buy data through its agent. Examples are market data feeds, satellite images, academic papers and private datasets.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Developer Invoice Collection</h3>
                    <p class="text-slate-500 text-sm">The banking-as-a-service module bills its partners with monthly invoices from $99 to $1,999. One API call collects such an invoice through the existing billing system.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Capabilities -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Core Capabilities</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">The adapter has six parts. They cover agent payments, card enrolment, invoice collection and tools for operators.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Tools for AI Agents</h3>
                    <p class="text-slate-500 text-sm">The <code>visacli.payment</code> tool makes a payment. The <code>visacli.cards</code> tool lists the enrolled cards. Both are in the internal Model Context Protocol tool registry, next to the x402 and Agent Protocol tools.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Safe Spending Limits</h3>
                    <p class="text-slate-500 text-sm">Each agent has a daily budget and a limit for each payment. The adapter enforces them with <code>lockForUpdate()</code>. Parallel requests from the same agent cannot go over the budget.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Invoice Payment Gateway</h3>
                    <p class="text-slate-500 text-sm">Partner invoices are paid through <code>POST /billing/invoices/{'{id}'}/pay</code>. On success the adapter calls <code>PartnerInvoice::markAsPaid()</code>. This gives the banking-as-a-service module a way to collect payments.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Card Enrollment Bridge</h3>
                    <p class="text-slate-500 text-sm">Enrolled cards are copied to the CardIssuance domain through <code>VisaCliCardEnrolled</code> events. You see all cards in one place, and the two domains stay separate.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Event-Sourced Audit Trail</h3>
                    <p class="text-slate-500 text-sm">The adapter records five domain events with Spatie Event Sourcing. They are <code>PaymentInitiated</code>, <code>Completed</code>, <code>Failed</code>, <code>CardEnrolled</code> and <code>CardRemoved</code>, and they cannot be changed.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Console Commands</h3>
                    <p class="text-slate-500 text-sm">Operators have three Artisan commands. <code>visa:status</code> shows the state of the adapter, <code>visa:enroll</code> enrols a card and <code>visa:pay</code> makes a payment by hand. Each command asks for confirmation before it acts.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Domain Architecture</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">The adapter is its own domain with a demo driver and a live driver. It does not depend on the CardIssuance or x402 domains.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div>
                    <h3 class="font-semibold text-lg text-slate-900 mb-4">Domain Structure</h3>
                    <div class="bg-slate-900 rounded-xl p-6 font-mono text-sm text-slate-300 overflow-x-auto">
<pre><span class="text-slate-500">app/Domain/VisaCli/</span>
  <span class="text-blue-400">Contracts/</span>     VisaCliClientInterface
                 VisaCliPaymentGatewayInterface
  <span class="text-emerald-400">Services/</span>      DemoVisaCliClient <span class="text-slate-500">(cache-based mock)</span>
                 VisaCliProcessClient <span class="text-slate-500">(real binary)</span>
                 VisaCliPaymentService
                 VisaCliSpendingLimitService
                 VisaCliCardEnrollmentService
                 VisaCliPaymentGatewayService
  <span class="text-amber-400">Models/</span>        VisaCliPayment
                 VisaCliEnrolledCard
                 VisaCliSpendingLimit
  <span class="text-purple-400">Events/</span>        PaymentInitiated, Completed, Failed
                 CardEnrolled, CardRemoved</pre>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-lg text-slate-900 mb-4">Security Hardening</h3>
                    <div class="space-y-4">
                        <div class="bg-white rounded-xl p-4 border border-slate-100">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="font-semibold text-sm text-slate-900">Blocked internal addresses</span>
                            </div>
                            <p class="text-xs text-slate-500 ml-5">A payment URL may not point to an internal address such as localhost, 169.254.x, 10.x or 192.168.x. Only http and https are allowed.</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-slate-100">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="font-semibold text-sm text-slate-900">Locked spending checks</span>
                            </div>
                            <p class="text-xs text-slate-500 ml-5">The check and the reservation happen under <code>lockForUpdate()</code>. Parallel requests cannot go over the budget.</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-slate-100">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="font-semibold text-sm text-slate-900">Signed webhooks</span>
                            </div>
                            <p class="text-xs text-slate-500 ml-5">Each webhook must carry a valid SHA-256 signature and a timestamp from the last 5 minutes. This stops old messages from being sent again. The production site rejects unsigned webhooks.</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-slate-100">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span class="font-semibold text-sm text-slate-900">Log Redaction</span>
                            </div>
                            <p class="text-xs text-slate-500 ml-5">GitHub tokens are removed from the tool's error output before it is written to the log.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Quick Start</h2>
                <p class="text-slate-500 mt-4">Three commands take you from setup to a first payment.</p>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Switch it on in .env</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>VISACLI_ENABLED=true
VISACLI_DRIVER=demo          <span class="text-slate-500"># or "process" for real visa-cli binary</span>
VISACLI_DAILY_LIMIT=10000    <span class="text-slate-500"># $100.00 daily limit (cents)</span>
VISACLI_PER_TX_LIMIT=1000    <span class="text-slate-500"># $10.00 per transaction (cents)</span></code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Verify the integration</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> php artisan visa:status

<span class="text-slate-500">Visa CLI Status</span>
+---------------+-----------+
| Property      | Value     |
+---------------+-----------+
| Initialized   | Yes       |
| Driver        | demo      |
| Enrolled Cards| 0         |
+---------------+-----------+</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Make a payment</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> php artisan visa:pay https://api.example.com/generate --amount=500 --agent=image-bot

<span class="text-slate-500">Processing payment...</span>
  URL:    https://api.example.com/generate
  Amount: $5.00
  Agent:  image-bot

<span class="text-emerald-400">Payment completed!</span>
+------------+------------------------------+
| Reference  | visa_pay_demo_a1b2c3d4e5f6   |
| Status     | completed                    |
| Amount     | $5.00                        |
+------------+------------------------------+</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Visa CLI vs x402 -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900">Card-payment tool compared with x402</h2>
                <p class="text-slate-500 mt-4">The two payment rails suit different cases. You can use both in the same platform.</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left py-4 px-6 font-semibold text-slate-900"></th>
                            <th class="text-center py-4 px-6 font-semibold text-blue-600">Card-payment tool</th>
                            <th class="text-center py-4 px-6 font-semibold text-emerald-600">x402 Protocol</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="py-3 px-6 text-slate-700">Payment method</td><td class="py-3 px-6 text-center text-slate-600">Card, through the third-party tool</td><td class="py-3 px-6 text-center text-slate-600">USDC on-chain</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Settlement</td><td class="py-3 px-6 text-center text-slate-600">Card network, in 1 to 2 days</td><td class="py-3 px-6 text-center text-slate-600">Instant on-chain</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Best for</td><td class="py-3 px-6 text-center text-slate-600">Traditional APIs and software services</td><td class="py-3 px-6 text-center text-slate-600">APIs that accept crypto</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Sign-in</td><td class="py-3 px-6 text-center text-slate-600">GitHub account and an enrolled card</td><td class="py-3 px-6 text-center text-slate-600">Wallet signature</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Agent spending limits</td><td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td><td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Agent tool</td><td class="py-3 px-6 text-center"><code class="text-xs">visacli.payment</code></td><td class="py-3 px-6 text-center"><code class="text-xs">x402.payment</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Try the card-payment adapter</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Switch on the demo driver and run <code class="text-blue-300">visa:status</code>. This takes about a minute.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary px-8 py-4 text-lg">Developer Docs</a>
                <a href="{{ route('features.show', 'x402-protocol') }}" class="btn-outline px-8 py-4 text-lg">Compare with x402</a>
            </div>
            <p class="mt-8 text-slate-500 text-sm">
                Related: <a href="{{ url('/features/zelta-cli') }}" class="underline hover:text-white transition text-slate-400">Zelta CLI</a> &middot;
                <a href="{{ url('/features/machine-payments') }}" class="underline hover:text-white transition text-slate-400">Machine Payments</a>
            </p>
        </div>
    </section>

@endsection
