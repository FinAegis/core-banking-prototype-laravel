@extends('layouts.public')

@section('title', 'Virtuals Protocol - AI Agent Commerce | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Virtuals Protocol - AI Agent Commerce',
        'description' => 'Give autonomous AI agents a compliant bank account via Virtuals Protocol ACP integration. Agent token tracking, aGDP reporting, Pimlico enforcement, and Visa settlement.',
        'keywords' => 'virtuals protocol, acp, ai agent commerce, agent token, agdp, pimlico, autonomous agents, agent banking, zelta',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Virtuals Protocol', 'url' => url('/features/virtuals-protocol')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 -right-32 w-80 h-80 bg-violet-500/10 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-violet-500/10 border border-violet-500/20 rounded-full text-sm text-violet-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        New &mdash; Virtuals Protocol Integration
                    </span>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'Virtuals Protocol', 'url' => url('/features/virtuals-protocol')]
                ]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    AI Agent <span class="text-gradient">Commerce</span>
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto mb-10">
                    Give autonomous agents a compliant bank account. {{ config('brand.name', 'Zelta') }} connects to the Virtuals Protocol
                    Agent Commerce Protocol (ACP) so any tokenized agent can hold funds, pass compliance, and pay with Visa.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-8">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        5 ACP Services
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Agent Token Tracking
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        aGDP Reporting
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Pimlico Enforcement
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-violet-500/20 to-transparent"></div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">How It Works</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">From agent intent to settled payment &mdash; three steps, fully autonomous, fully compliant.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-violet-600">1</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Agent Hires {{ config('brand.name', 'Zelta') }} via ACP</h3>
                    <p class="text-slate-500 text-sm">An autonomous agent browses the ACP marketplace, finds {{ config('brand.name', 'Zelta') }}'s banking services, and initiates a job &mdash; requesting a Visa card, compliance check, or payment execution.</p>
                </div>
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-violet-600">2</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Spending Limits Enforced</h3>
                    <p class="text-slate-500 text-sm">{{ config('brand.name', 'Zelta') }} verifies the agent's TrustCert identity, checks sanctions lists, and applies per-agent daily budgets and per-transaction caps via Pimlico smart-account rules.</p>
                </div>
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-violet-600">3</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Payment Settles via Visa / x402</h3>
                    <p class="text-slate-500 text-sm">The approved transaction settles through Visa card rails for traditional merchants or x402 for crypto-native APIs. Every step is event-sourced for full auditability.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ACP Service Catalog -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">ACP Service Catalog</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Six banking services any Virtuals agent can hire through the Agent Commerce Protocol.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Get a Visa Card</h3>
                    <p class="text-slate-500 text-sm">Provision a virtual Visa card bound to the agent's smart account. Per-agent daily and per-transaction limits enforced on-chain.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded">Per-card fee</span>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Compliance Check</h3>
                    <p class="text-slate-500 text-sm">Run KYC/KYB verification against the agent's TrustCert identity. Returns a compliance score and risk tier used for limit assignment.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Per-check fee</span>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Sanctions Screen</h3>
                    <p class="text-slate-500 text-sm">Screen agent wallet addresses and counterparties against OFAC, EU, and UN sanctions lists in real time before any payment executes.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded">Per-screen fee</span>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Shield Assets</h3>
                    <p class="text-slate-500 text-sm">Move agent funds into a Pimlico-enforced smart account with configurable spend policies, time locks, and multi-sig recovery.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-amber-600 bg-amber-50 px-2 py-1 rounded">Flat monthly fee</span>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Execute Payment</h3>
                    <p class="text-slate-500 text-sm">Authorize and settle a payment via Visa card rails or x402 USDC. Budget atomically reserved under database row lock before execution.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-purple-600 bg-purple-50 px-2 py-1 rounded">Per-transaction fee</span>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Fiat Off-Ramp</h3>
                    <p class="text-slate-500 text-sm">Convert agent token holdings to fiat and wire to a linked bank account. Supports USD, EUR, and GBP with T+1 settlement.</p>
                    <span class="inline-block mt-3 text-xs font-medium text-cyan-600 bg-cyan-50 px-2 py-1 rounded">Percentage fee</span>
                </div>
            </div>
        </div>
    </section>

    <!-- For Agent Developers -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">For Agent Developers</h2>
                <p class="text-slate-500 mt-4">Hire {{ config('brand.name', 'Zelta') }} banking services from any Virtuals agent in a few lines of TypeScript.</p>
            </div>
            <div class="bg-slate-900 rounded-xl p-6 font-mono text-sm text-slate-300 overflow-x-auto">
<pre><span class="text-slate-500">// Install: npm i @virtuals-protocol/acp-node</span>
<span class="text-violet-400">import</span> AcpClient <span class="text-violet-400">from</span> <span class="text-emerald-400">"@virtuals-protocol/acp-node"</span>;

<span class="text-slate-500">// Browse for {{ config('brand.name', 'Zelta') }}'s banking services</span>
<span class="text-blue-400">const</span> services = <span class="text-violet-400">await</span> acpClient.browseAgents({
  query: <span class="text-emerald-400">"visa card compliance"</span>
});

<span class="text-slate-500">// Hire {{ config('brand.name', 'Zelta') }} to provision a card</span>
<span class="text-blue-400">const</span> job = <span class="text-violet-400">await</span> acpClient.initiateJob({
  providerId: <span class="text-emerald-400">"zelta-banking"</span>,
  serviceType: <span class="text-emerald-400">"get-visa-card"</span>,
  params: {
    chain: <span class="text-emerald-400">"base"</span>,
    dailyLimit: <span class="text-amber-400">50000</span>
  }
});

<span class="text-slate-500">// Monitor job status</span>
<span class="text-blue-400">const</span> status = <span class="text-violet-400">await</span> acpClient.getJobStatus(job.id);
console.log(status.card);  <span class="text-slate-500">// { last4: "4242", network: "visa" }</span></pre>
            </div>
            <p class="text-sm text-slate-400 mt-4 text-center">The ACP SDK handles discovery, negotiation, and payment between agents. {{ config('brand.name', 'Zelta') }} fulfils banking jobs on the provider side.</p>
        </div>
    </section>

    <!-- Virtuals x Zelta Comparison -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900">Virtuals x {{ config('brand.name', 'Zelta') }}</h2>
                <p class="text-slate-500 mt-4">Two protocols, complementary strengths. Virtuals provides the agent brain &mdash; {{ config('brand.name', 'Zelta') }} provides the bank account.</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left py-4 px-6 font-semibold text-slate-900"></th>
                            <th class="text-center py-4 px-6 font-semibold text-violet-600">Virtuals Protocol</th>
                            <th class="text-center py-4 px-6 font-semibold text-blue-600">{{ config('brand.name', 'Zelta') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Core role</td>
                            <td class="py-3 px-6 text-center text-slate-600">Agent brain &amp; cognition</td>
                            <td class="py-3 px-6 text-center text-slate-600">Banking &amp; compliance</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Tokenization</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Agent marketplace</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Bank account</td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">KYC / compliance</td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Spending limits</td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">Visa cards</td>
                            <td class="py-3 px-6 text-center text-slate-400">&mdash;</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        </tr>
                        <tr>
                            <td class="py-3 px-6 text-slate-700">aGDP reporting</td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                            <td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Build agent-native banking</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Connect your Virtuals agent to {{ config('brand.name', 'Zelta') }} via ACP and give it a compliant bank account in minutes.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary">Developer Docs</a>
                <a href="{{ route('features.show', 'visa-cli') }}" class="btn-outline-light">Visa CLI Payment Rail</a>
            </div>
        </div>
    </section>

@endsection
