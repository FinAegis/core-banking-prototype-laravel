@extends('layouts.public')

@section('title', 'Zelta CLI - Multi-Rail Payments from Your Terminal | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Zelta CLI - Multi-Rail Payments from Your Terminal',
        'description' => 'Send payments, manage SMS campaigns, and query transaction history from the command line. JSON output for piping to scripts, CI/CD, and AI agents.',
        'keywords' => 'zelta cli, payment cli, sms cli, terminal payments, json output, ci/cd payments, ai agent cli, command line banking',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Zelta CLI', 'url' => url('/features/zelta-cli')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 -right-32 w-80 h-80 bg-emerald-500/10 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-sm text-emerald-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        v0.2.0 &middot; 25 Commands
                    </span>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'Zelta CLI', 'url' => url('/features/zelta-cli')]
                ]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Zelta <span class="text-gradient">CLI</span>
                </h1>
                <p class="text-lg text-slate-400 max-w-2xl mx-auto mb-10">
                    Multi-rail payments, SMS campaigns, and transaction queries &mdash; all from your terminal.
                    Pipe JSON output to scripts, CI/CD pipelines, and AI agents.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-8">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        JSON Output
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Multi-Rail Payments
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        SMS Campaigns
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        CI/CD Ready
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent"></div>
    </section>

    <!-- Command Reference -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Command Reference</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Every command outputs structured JSON with <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">--json</code> for easy piping.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Payments</h3>
                    <p class="text-slate-500 text-sm"><code>zelta pay:send</code>, <code>zelta pay:list</code>, <code>zelta pay:stats</code> &mdash; send payments, list history, and view aggregate statistics across all rails.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">SMS</h3>
                    <p class="text-slate-500 text-sm"><code>zelta sms:send</code>, <code>zelta sms:status</code>, <code>zelta sms:balance</code> &mdash; send messages, check delivery status, and monitor provider balance.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Configuration</h3>
                    <p class="text-slate-500 text-sm"><code>zelta config:init</code>, <code>zelta config:show</code> &mdash; initialize API credentials and display the current environment configuration.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Analytics</h3>
                    <p class="text-slate-500 text-sm"><code>zelta analytics:dashboard</code> &mdash; real-time payment volume, success rates, and rail distribution rendered in the terminal.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Authentication</h3>
                    <p class="text-slate-500 text-sm"><code>zelta auth:login</code>, <code>zelta auth:token</code> &mdash; OAuth device flow login and token management for CI environments.</p>
                </div>
                <div class="card-feature bg-cyan-50 border-cyan-200">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Wallet &amp; Agents</h3>
                    <p class="text-slate-500 text-sm">Manage wallet balances, spending limits, and agent registration. Generate SDKs in 6 languages directly from the CLI.</p>
                    <div class="space-y-1.5 font-mono text-xs mt-3">
                        <div class="text-slate-600"><span class="text-cyan-500">wallet:balance</span> &middot; <span class="text-cyan-500">limits:set</span> &middot; <span class="text-cyan-500">agents:register</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Quick Start</h2>
                <p class="text-slate-500 mt-4">Four steps from install to piping payment data into your scripts.</p>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Install the CLI</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> npm install -g @zelta/cli

<span class="text-slate-500"># Verify installation</span>
<span class="text-emerald-400">$</span> zelta --version
zelta/0.2.0 linux-x64 node-v20.11.0</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Authenticate</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> zelta auth:login

<span class="text-slate-500">Opening browser for authentication...</span>
<span class="text-emerald-400">Authenticated as dev@example.com</span>
Token saved to ~/.config/zelta/credentials.json</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Query payments</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> zelta pay:list --limit 5 --json

<span class="text-slate-500">[</span>
  { <span class="text-emerald-400">"id"</span>: <span class="text-amber-300">"pay_8f3a..."</span>, <span class="text-emerald-400">"amount"</span>: 1500, <span class="text-emerald-400">"status"</span>: <span class="text-amber-300">"settled"</span>, <span class="text-emerald-400">"rail"</span>: <span class="text-amber-300">"x402"</span> },
  { <span class="text-emerald-400">"id"</span>: <span class="text-amber-300">"pay_2c7b..."</span>, <span class="text-emerald-400">"amount"</span>: 990, <span class="text-emerald-400">"status"</span>: <span class="text-amber-300">"settled"</span>, <span class="text-emerald-400">"rail"</span>: <span class="text-amber-300">"stripe"</span> }
<span class="text-slate-500">]</span></code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">4</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Pipe to scripts and agents</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-slate-500"># AI agent consumes JSON output directly</span>
<span class="text-emerald-400">$</span> zelta pay:list --json | jq '.[] | select(.status == "settled")'

<span class="text-slate-500"># CI/CD: fail pipeline if payments are failing</span>
<span class="text-emerald-400">$</span> zelta pay:stats --json --period day | jq -e '.failed == 0'

<span class="text-slate-500"># Bulk SMS from a file</span>
<span class="text-emerald-400">$</span> cat contacts.json | jq -c '.[]' | while read c; do
    zelta sms:send --to $(echo $c | jq -r .phone) --message "Your code: $(openssl rand -hex 3)"
  done</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Built for Automation</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Every feature is designed for scripting, CI/CD integration, and AI agent consumption.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Structured JSON Output</h3>
                    <p class="text-slate-500 text-sm">Every command supports <code>--json</code> for machine-readable output. Pipe to <code>jq</code>, feed into scripts, or let AI agents parse results directly.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Multi-Rail Support</h3>
                    <p class="text-slate-500 text-sm">Send payments over x402 (USDC), Stripe, Tempo, Lightning, or Visa CLI rails. The CLI auto-selects the best rail or lets you override with <code>--rail</code>.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Secure by Default</h3>
                    <p class="text-slate-500 text-sm">Credentials stored in OS keychain. Tokens auto-refresh. Environment variables for CI/CD. Never stores secrets in plaintext.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Spending Limits</h3>
                    <p class="text-slate-500 text-sm">Per-agent daily budgets and per-transaction caps enforced server-side. The CLI surfaces remaining budget in <code>--json</code> responses.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-sky-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Shell Completions</h3>
                    <p class="text-slate-500 text-sm">Tab completion for bash, zsh, and fish. Command, flag, and argument suggestions generated from the CLI schema.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Audit Export</h3>
                    <p class="text-slate-500 text-sm">Export full transaction audit trails as JSON or CSV. Filter by date range, rail, status, or agent for compliance reporting.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Start building with Zelta CLI</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Install the CLI, authenticate, and send your first payment in under two minutes.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary px-8 py-4 text-lg">Developer Docs</a>
                <a href="{{ route('features.show', 'machine-payments') }}" class="btn-outline px-8 py-4 text-lg">Machine Payments</a>
            </div>
            <p class="mt-8 text-slate-500 text-sm">
                Related: <a href="{{ url('/features/x402-protocol') }}" class="underline hover:text-white transition text-slate-400">x402 Protocol</a> &middot;
                <a href="{{ url('/features/machine-payments') }}" class="underline hover:text-white transition text-slate-400">Machine Payments</a> &middot;
                <a href="{{ url('/features/visa-cli') }}" class="underline hover:text-white transition text-slate-400">Visa CLI</a>
            </p>
        </div>
    </section>

@endsection
