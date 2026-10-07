@extends('layouts.public')

@section('title', 'Virtuals Protocol - AI Agent Commerce | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Virtuals Protocol - AI Agent Commerce',
        'description' => 'Virtuals Protocol ACP integration adapter: agent token tracking, spending limits and Pimlico-based enforcement. Integration adapters for third-party APIs; no partnership or endorsement implied.',
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
                    Give autonomous agents programmable wallets with spending limits. {{ config('brand.name', 'Zelta') }} includes an integration adapter for the Virtuals Protocol
                    Agent Commerce Protocol (ACP); no partnership or endorsement implied.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-8">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        ACP Adapter Operations
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
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">From agent intent to settled payment in three steps.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8 bg-slate-50 rounded-2xl">
                    <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-violet-600">1</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Agent Hires {{ config('brand.name', 'Zelta') }} via ACP</h3>
                    <p class="text-slate-500 text-sm">An autonomous agent browses the ACP marketplace, finds the adapter's services, and initiates a job &mdash; a spending-limit check or payment execution.</p>
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
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Payment Settles via x402</h3>
                    <p class="text-slate-500 text-sm">The approved transaction settles via x402 for crypto-native APIs. Every step is event-sourced for full auditability.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ACP Service Catalog -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Example adapter operations (sandbox)</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Operations exposed to Virtuals agents through the Agent Commerce Protocol adapter.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Sanctions Screen</h3>
                    <p class="text-slate-500 text-sm">Screen agent wallet addresses and counterparties against OFAC, EU, and UN sanctions lists in real time before any payment executes.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Shield Assets</h3>
                    <p class="text-slate-500 text-sm">Move agent funds into a Pimlico-enforced smart account with configurable spend policies, time locks, and multi-sig recovery.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Execute Payment</h3>
                    <p class="text-slate-500 text-sm">Authorize and settle a payment via x402 USDC. Budget atomically reserved under database row lock before execution.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- For Agent Developers -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">For Agent Developers</h2>
                <p class="text-slate-500 mt-4">Call the {{ config('brand.name', 'Zelta') }} adapter operations from any Virtuals agent in a few lines of TypeScript.</p>
            </div>
            <div class="bg-slate-900 rounded-xl p-6 font-mono text-sm text-slate-300 overflow-x-auto">
<pre><span class="text-slate-500">// Install: npm i @virtuals-protocol/acp-node</span>
<span class="text-violet-400">import</span> AcpClient <span class="text-violet-400">from</span> <span class="text-emerald-400">"@virtuals-protocol/acp-node"</span>;

<span class="text-slate-500">// Browse for {{ config('brand.name', 'Zelta') }}'s adapter operations</span>
<span class="text-blue-400">const</span> services = <span class="text-violet-400">await</span> acpClient.browseAgents({
  query: <span class="text-emerald-400">"spending limits payments"</span>
});

<span class="text-slate-500">// Hire {{ config('brand.name', 'Zelta') }} to execute a payment</span>
<span class="text-blue-400">const</span> job = <span class="text-violet-400">await</span> acpClient.initiateJob({
  providerId: <span class="text-emerald-400">"zelta-banking"</span>,
  serviceType: <span class="text-emerald-400">"payments"</span>,
  params: {
    chain: <span class="text-emerald-400">"base"</span>,
    dailyLimit: <span class="text-amber-400">50000</span>
  }
});

<span class="text-slate-500">// Monitor job status</span>
<span class="text-blue-400">const</span> status = <span class="text-violet-400">await</span> acpClient.getJobStatus(job.id);
console.log(status);</pre>
            </div>
            <p class="text-sm text-slate-400 mt-4 text-center">The ACP SDK handles discovery, negotiation, and payment between agents. {{ config('brand.name', 'Zelta') }} fulfils adapter jobs on the provider side.</p>
        </div>
    </section>

    <!-- Virtuals x Zelta Comparison -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900">Virtuals x {{ config('brand.name', 'Zelta') }}</h2>
                <p class="text-slate-500 mt-4">Two protocols, complementary strengths. Virtuals provides the agent runtime &mdash; {{ config('brand.name', 'Zelta') }} provides wallet and spending-limit tooling.</p>
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
                            <td class="py-3 px-6 text-center text-slate-600">Wallet &amp; spending-limit tooling</td>
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
                            <td class="py-3 px-6 text-slate-700">Spending limits</td>
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
            <h2 class="font-display text-3xl font-bold text-white mb-4">Build agent-native payments</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Connect your Virtuals agent to {{ config('brand.name', 'Zelta') }} via ACP and give it a programmable wallet with spending limits.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary px-8 py-4 text-lg">Developer Docs</a>
                <a href="{{ route('features.show', 'visa-cli') }}" class="btn-outline px-8 py-4 text-lg">Card-Payment CLI Adapter</a>
            </div>
        </div>
    </section>

@endsection
