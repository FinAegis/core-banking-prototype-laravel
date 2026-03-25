@extends('layouts.public')

@section('title', 'Zelta CLI - Manage Payments & APIs from the Terminal | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Zelta CLI - Manage Payments & APIs from the Terminal',
        'description' => 'Manage x402 payments, SMS, wallets, and API monetization from one CLI. Built for humans and AI agents — tables for terminals, JSON for pipes.',
        'keywords' => 'zelta cli, payment cli, x402 cli, mpp cli, sms cli, api monetization, developer tools, ai agent cli, terminal payments, fintech cli',
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
        <div class="absolute top-1/4 -left-32 w-80 h-80 bg-emerald-500/10 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-1/4 -right-32 w-80 h-80 bg-blue-500/10 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <span class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-sm text-emerald-300 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        v6.5.0 &middot; Standalone CLI Binary
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
                    Manage payments, SMS, wallets, and API monetization from the terminal.
                    Built for humans and AI agents &mdash; formatted tables in TTY, raw JSON when piped.
                </p>
                <div class="flex flex-wrap justify-center gap-4 mb-10">
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        10 Commands
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        5 Resource Groups
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        x402 + MPP Protocols
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-400">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Multi-Profile Auth
                    </div>
                </div>
                <!-- Terminal preview -->
                <div class="max-w-2xl mx-auto">
                    <div class="bg-slate-900 rounded-xl overflow-hidden border border-slate-700/50 shadow-2xl shadow-black/40">
                        <div class="flex items-center gap-2 px-4 py-3 bg-slate-800/80 border-b border-slate-700/50">
                            <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                            <span class="text-xs text-slate-500 ml-2 font-mono">Terminal</span>
                        </div>
                        <div class="p-5 font-mono text-sm text-left overflow-x-auto">
<pre class="text-slate-300"><span class="text-emerald-400">$</span> curl -fsSL https://cli.zelta.app/install.sh | bash
<span class="text-slate-500">Installing Zelta CLI v0.1.0... done.</span>

<span class="text-emerald-400">$</span> zelta auth:login --key zk_live_A1b2C3d4
<span class="text-blue-400">Authenticated as profile 'default' at https://api.zelta.app</span>

<span class="text-emerald-400">$</span> zelta pay:list --status settled
<span class="text-slate-400">ID          Status    Amount   Network       Endpoint</span>
<span class="text-slate-600">----------- --------- -------- ------------- ----------------</span>
<span class="text-slate-300">c9a1..f2e3  settled   100000   eip155:8453   /v1/premium/data</span>
<span class="text-slate-300">b7d4..a1c8  settled   50000    solana:main   /v1/market/feed</span></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent"></div>
    </section>

    <!-- Install Methods -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Install in Seconds</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Four ways to install. Pick the one that fits your workflow.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center p-6 bg-slate-50 rounded-2xl">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="font-display text-sm font-semibold text-slate-900 mb-2">curl</h3>
                    <code class="text-xs bg-white px-2 py-1 rounded border border-slate-200 text-slate-600 break-all">curl -fsSL cli.zelta.app/install.sh | bash</code>
                </div>
                <div class="text-center p-6 bg-slate-50 rounded-2xl">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="font-display text-sm font-semibold text-slate-900 mb-2">npm</h3>
                    <code class="text-xs bg-white px-2 py-1 rounded border border-slate-200 text-slate-600">npm install -g @zelta/cli</code>
                </div>
                <div class="text-center p-6 bg-slate-50 rounded-2xl">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <h3 class="font-display text-sm font-semibold text-slate-900 mb-2">Homebrew</h3>
                    <code class="text-xs bg-white px-2 py-1 rounded border border-slate-200 text-slate-600">brew install zelta-app/tap/zelta</code>
                </div>
                <div class="text-center p-6 bg-slate-50 rounded-2xl">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </div>
                    <h3 class="font-display text-sm font-semibold text-slate-900 mb-2">Composer</h3>
                    <code class="text-xs bg-white px-2 py-1 rounded border border-slate-200 text-slate-600">composer global require zelta/cli</code>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">How It Works</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Authenticate once, manage everything from a single binary &mdash; payments, SMS, endpoints, and spending limits.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8 bg-white rounded-2xl border border-slate-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">1</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Authenticate</h3>
                    <p class="text-slate-500 text-sm">Run <code class="text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">zelta auth:login --key zk_live_xxx</code>. Credentials are stored in <code class="text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">~/.zelta/</code> with multi-profile support.</p>
                </div>
                <div class="text-center p-8 bg-white rounded-2xl border border-slate-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">2</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Execute Commands</h3>
                    <p class="text-slate-500 text-sm">Run <code class="text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">zelta pay:list</code>, <code class="text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">zelta sms:send</code>, or any of the 10 commands. The CLI talks to the Zelta API over HTTPS.</p>
                </div>
                <div class="text-center p-8 bg-white rounded-2xl border border-slate-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold text-blue-600">3</span>
                    </div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 mb-3">Pipe or Read</h3>
                    <p class="text-slate-500 text-sm">In a terminal, output renders as formatted tables. Piped or with <code class="text-xs bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">--json</code>, it returns structured JSON for scripts and AI agents.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Use Cases -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Built For</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">Developers, operators, and autonomous AI agents that need fast access to Zelta&rsquo;s payment infrastructure.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-violet-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Developers</h3>
                    <p class="text-slate-500 text-sm">Manage monetized endpoints, view payment history, and debug SMS delivery from the terminal without leaving your IDE.</p>
                </div>
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-sky-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">AI Agents</h3>
                    <p class="text-slate-500 text-sm">Pipe JSON output directly into agent workflows. Structured exit codes (0/1/2/3/4) and <code class="text-xs bg-white px-1 py-0.5 rounded border border-slate-200">--json</code> flag for machine consumption.</p>
                </div>
                <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">CI/CD Pipelines</h3>
                    <p class="text-slate-500 text-sm">Automate API key rotation, endpoint provisioning, and payment reconciliation in GitHub Actions, GitLab CI, or any shell-based pipeline.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Command Reference -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Command Reference</h2>
                <p class="text-slate-500 mt-4 max-w-xl mx-auto">10 commands across 5 resource groups. Every command supports <code class="text-xs bg-white px-1.5 py-0.5 rounded border border-slate-200">--json</code> for machine-readable output.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="card-feature">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Authentication</h3>
                    <p class="text-slate-500 text-sm mb-3">Multi-profile API key management with secure credential storage.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-600"><span class="text-blue-500">auth:login</span> --key zk_live_xxx</div>
                        <div class="text-slate-600"><span class="text-blue-500">auth:status</span></div>
                    </div>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Payments</h3>
                    <p class="text-slate-500 text-sm mb-3">Query x402 payment history, statistics, and settlement status.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-600"><span class="text-emerald-500">pay:list</span> --status settled</div>
                        <div class="text-slate-600"><span class="text-emerald-500">pay:stats</span> --period week</div>
                    </div>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">SMS</h3>
                    <p class="text-slate-500 text-sm mb-3">Send SMS via VertexSMS and check delivery rates by country.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-600"><span class="text-purple-500">sms:send</span> --to +370xxx --message &quot;Hi&quot;</div>
                        <div class="text-slate-600"><span class="text-purple-500">sms:rates</span> --country LT</div>
                    </div>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Endpoints</h3>
                    <p class="text-slate-500 text-sm mb-3">Manage x402-monetized API endpoints and pricing configuration.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-600"><span class="text-amber-500">endpoints:list</span></div>
                    </div>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Identity</h3>
                    <p class="text-slate-500 text-sm mb-3">Verify authentication status and inspect the active profile.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-600"><span class="text-rose-500">whoami</span></div>
                    </div>
                </div>
                <div class="card-feature">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Coming Soon</h3>
                    <p class="text-slate-500 text-sm mb-3">Wallet management, agent registration, spending limits, SDK generation, and plugin management.</p>
                    <div class="space-y-1.5 font-mono text-xs">
                        <div class="text-slate-400">wallet:balance, limits:set, agents:register, sdk:generate ...</div>
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
                <p class="text-slate-500 mt-4">From install to first payment query in 30 seconds.</p>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Install the CLI</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> curl -fsSL https://cli.zelta.app/install.sh | bash
<span class="text-slate-500">Downloading Zelta CLI v0.1.0...</span>
<span class="text-emerald-400">Zelta CLI installed to /usr/local/bin/zelta</span></code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Authenticate</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> zelta auth:login --key zk_live_A1b2C3d4
<span class="text-blue-400">Authenticated as profile 'default' at https://api.zelta.app</span>

<span class="text-emerald-400">$</span> zelta whoami
<span class="text-slate-400">{</span>
  <span class="text-blue-300">"profile"</span>: <span class="text-amber-300">"default"</span>,
  <span class="text-blue-300">"base_url"</span>: <span class="text-amber-300">"https://api.zelta.app"</span>,
  <span class="text-blue-300">"api_key"</span>: <span class="text-amber-300">"zk_live_A1..."</span>
<span class="text-slate-400">}</span></code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Query payments</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> zelta pay:stats --period week
<span class="text-slate-400">{</span>
  <span class="text-blue-300">"total_payments"</span>: <span class="text-emerald-300">142</span>,
  <span class="text-blue-300">"settled"</span>: <span class="text-emerald-300">138</span>,
  <span class="text-blue-300">"failed"</span>: <span class="text-emerald-300">4</span>,
  <span class="text-blue-300">"total_volume_usdc"</span>: <span class="text-amber-300">"24.850000"</span>
<span class="text-slate-400">}</span>

<span class="text-slate-500"># Or send SMS directly from the terminal:</span>
<span class="text-emerald-400">$</span> zelta sms:send --to +37060012345 --message "Your code: 847291"
<span class="text-slate-400">{</span>
  <span class="text-blue-300">"message_id"</span>: <span class="text-amber-300">"sms_a1b2c3d4"</span>,
  <span class="text-blue-300">"status"</span>: <span class="text-amber-300">"sent"</span>
<span class="text-slate-400">}</span></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Zelta CLI vs Zelta SDK vs Direct API -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900">CLI vs SDK vs Direct API</h2>
                <p class="text-slate-500 mt-4">Three ways to interact with Zelta. Choose the one that fits your use case.</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="text-left py-4 px-6 font-semibold text-slate-900"></th>
                            <th class="text-center py-4 px-6 font-semibold text-emerald-600">Zelta CLI</th>
                            <th class="text-center py-4 px-6 font-semibold text-blue-600">Zelta SDK</th>
                            <th class="text-center py-4 px-6 font-semibold text-slate-600">REST API</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="py-3 px-6 text-slate-700">Best for</td><td class="py-3 px-6 text-center text-slate-600">Terminal &amp; CI/CD</td><td class="py-3 px-6 text-center text-slate-600">PHP applications</td><td class="py-3 px-6 text-center text-slate-600">Any language</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Auto 402 handling</td><td class="py-3 px-6 text-center text-slate-600">Manual</td><td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td><td class="py-3 px-6 text-center text-slate-600">Manual</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Machine-readable output</td><td class="py-3 px-6 text-center"><code class="text-xs">--json</code></td><td class="py-3 px-6 text-center text-slate-600">Native</td><td class="py-3 px-6 text-center text-slate-600">Native</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Human-friendly output</td><td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td><td class="py-3 px-6 text-center text-slate-400">&mdash;</td><td class="py-3 px-6 text-center text-slate-400">&mdash;</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Multi-profile auth</td><td class="py-3 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td><td class="py-3 px-6 text-center text-slate-400">&mdash;</td><td class="py-3 px-6 text-center text-slate-400">&mdash;</td></tr>
                        <tr><td class="py-3 px-6 text-slate-700">Install</td><td class="py-3 px-6 text-center"><code class="text-xs">curl | bash</code></td><td class="py-3 px-6 text-center"><code class="text-xs">composer require</code></td><td class="py-3 px-6 text-center text-slate-600">None</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl font-bold text-white mb-4">Start building with Zelta CLI</h2>
            <p class="text-slate-400 mb-8 max-w-xl mx-auto">Install in one line and manage your entire payment infrastructure from the terminal. No browser needed.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers') }}" class="btn-primary px-8 py-4 text-lg">Developer Docs</a>
                <a href="{{ route('features.show', 'x402-protocol') }}" class="btn-outline px-8 py-4 text-lg">x402 Protocol</a>
            </div>
        </div>
    </section>

@endsection
