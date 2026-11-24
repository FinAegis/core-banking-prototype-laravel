@extends('layouts.public')

@section('title', 'Zelta CLI for Payments from the Terminal | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Zelta CLI for Payments from the Terminal',
        'description' => 'The Zelta CLI is a command-line tool for the Zelta API. It pays for API endpoints, sends SMS and shows wallet and payment history. Every command can print JSON for scripts, build pipelines and AI agents.',
        'keywords' => 'zelta cli, payment command line, sms command line, terminal payments, json output, build pipeline payments, ai agent command line, command line banking',
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
                        Version 0.2.9 &middot; 27 commands
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
                    The Zelta CLI is a command-line tool for the Zelta API. You can pay for API endpoints, send SMS and look up wallet and payment history from your terminal.
                    Every command can print JSON, so scripts, build pipelines and AI agents can read the output.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-8">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        JSON Output
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Several payment rails
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        SMS
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Works in build pipelines
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
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Add <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">--json</code> to a command to get structured JSON. You can then pass the output to other tools.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Payments</h3>
                    <p class="text-slate-500 text-sm"><code>zelta pay:send</code> pays for an API endpoint that asks for payment. <code>zelta pay:list</code>, <code>zelta pay:status</code> and <code>zelta pay:stats</code> show past payments, one payment's status and totals.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">SMS</h3>
                    <p class="text-slate-500 text-sm"><code>zelta sms:send</code> sends a message through VertexSMS. <code>zelta sms:status</code> checks whether it was delivered, and <code>zelta sms:rates</code> shows the price for each country.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Configuration</h3>
                    <p class="text-slate-500 text-sm"><code>zelta config</code> shows the current settings and lets you change them. <code>zelta whoami</code> shows which account you are signed in to.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Bank Transfers and Plans</h3>
                    <p class="text-slate-500 text-sm"><code>zelta ramp:kyc-link</code> gets the identity check link for bank transfers. <code>zelta ramp:status</code> shows the setup status, and <code>zelta subscription:status</code> shows your plan.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Authentication</h3>
                    <p class="text-slate-500 text-sm"><code>zelta auth:login</code> signs you in with a personal access token. <code>zelta auth:status</code>, <code>zelta auth:token</code> and <code>zelta auth:logout</code> check, show and remove it.</p>
                </div>
                <div class="card-feature bg-cyan-50 border-cyan-200">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Wallet &amp; Agents</h3>
                    <p class="text-slate-500 text-sm">You can see wallet balances and activity, set spending limits and register agents. The CLI can also generate a client library in 6 languages.</p>
                    <div class="space-y-1.5 font-mono text-xs mt-3">
                        <div class="text-slate-600"><span class="text-cyan-500">wallet:balance</span> &middot; <span class="text-cyan-500">limits:set</span> &middot; <span class="text-cyan-500">agents:register</span> &middot; <span class="text-cyan-500">sdk:generate</span></div>
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
                <p class="text-slate-500 mt-4">Four steps take you from installing the tool to using payment data in your scripts.</p>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Install the CLI</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> npm install -g @finaegis/cli

<span class="text-slate-500"># Verify installation</span>
<span class="text-emerald-400">$</span> zelta --version
zelta/0.2.9 linux-x64 node-v20.11.0</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Authenticate</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-slate-500"># Create a personal access token in the dashboard (Profile &rarr; API Tokens)</span>
<span class="text-emerald-400">$</span> zelta auth:login --key &lt;api-token&gt;

<span class="text-slate-500">Verifying token against the API...</span>
<span class="text-emerald-400">Authenticated as dev@example.com (profile 'default')</span>
Token saved to ~/.zelta/credentials.json</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Query payments</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> zelta pay:list --json

<span class="text-slate-500">[</span>
  { <span class="text-emerald-400">"id"</span>: <span class="text-amber-300">"pay_8f3a..."</span>, <span class="text-emerald-400">"amount"</span>: 1500, <span class="text-emerald-400">"status"</span>: <span class="text-amber-300">"settled"</span>, <span class="text-emerald-400">"rail"</span>: <span class="text-amber-300">"x402"</span> },
  { <span class="text-emerald-400">"id"</span>: <span class="text-amber-300">"pay_2c7b..."</span>, <span class="text-emerald-400">"amount"</span>: 990, <span class="text-emerald-400">"status"</span>: <span class="text-amber-300">"settled"</span>, <span class="text-emerald-400">"rail"</span>: <span class="text-amber-300">"stripe"</span> }
<span class="text-slate-500">]</span></code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">4</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Use the output in scripts and agents</h3>
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
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">The tool is made for scripts, build pipelines and AI agents.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Structured JSON Output</h3>
                    <p class="text-slate-500 text-sm">Every command supports <code>--json</code> for output that programs can read. You can pass it to <code>jq</code> or to a script, or let an AI agent read it.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Payment Rails</h3>
                    <p class="text-slate-500 text-sm">When an endpoint asks for payment, the CLI reads the challenge and picks the rail from it. It pays Machine Payments Protocol challenges by itself. An x402 challenge needs a key on your device, so the CLI shows what the payment requires instead of paying it. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Stored Credentials</h3>
                    <p class="text-slate-500 text-sm">The CLI keeps your token in <code>~/.zelta/credentials.json</code>. Only your user account can read the file. You can keep several profiles and switch between them.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Spending Limits</h3>
                    <p class="text-slate-500 text-sm">Each agent can have a daily budget and a limit for each payment. The server enforces them. <code>limits:set</code>, <code>limits:list</code> and <code>limits:remove</code> manage them.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-sky-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Shell Completions</h3>
                    <p class="text-slate-500 text-sm">The built-in <code>completion</code> command adds tab completion for bash, zsh and fish. It suggests commands and options as you type.</p>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Payment History</h3>
                    <p class="text-slate-500 text-sm"><code>pay:list</code> shows recent x402 payments. You can filter by status or network. <code>wallet:transactions</code> shows the wallet's recent activity.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Start building with Zelta CLI</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Install the CLI and sign in with your token. You can then make your first paid request.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary px-8 py-4 text-lg">Developer Docs</a>
                <a href="{{ route('features.show', 'machine-payments') }}" class="btn-outline px-8 py-4 text-lg">Machine Payments</a>
            </div>
            <p class="mt-8 text-slate-500 text-sm">
                Related: <a href="{{ url('/features/x402-protocol') }}" class="underline hover:text-white transition text-slate-400">x402 Protocol</a> &middot;
                <a href="{{ url('/features/machine-payments') }}" class="underline hover:text-white transition text-slate-400">Machine Payments</a> &middot;
                <a href="{{ url('/features/visa-cli') }}" class="underline hover:text-white transition text-slate-400">Card-Payment Adapter</a>
            </p>
        </div>
    </section>

@endsection
