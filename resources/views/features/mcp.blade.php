@extends('layouts.public')

@php
    $brand     = config('brand.name', 'Zelta');
    $allTools  = (array) config('mcp.tools', []);
    $tools     = array_filter($allTools, fn ($t) => (bool) ($t['enabled'] ?? false));
    $toolCount = count($tools);
    $scopeCount = count((array) config('mcp.scopes', []));
    $mcpUrl    = 'https://' . (string) config('mcp.host', 'mcp.zelta.app') . '/mcp';
@endphp

@section('title', 'MCP-native banking — connect Claude, Cursor, or any agent | ' . $brand)

@section('seo')
    @include('partials.seo', [
        'title'       => 'MCP-native banking — connect Claude, Cursor, or any agent',
        'description' => 'Public OAuth-protected MCP server with ' . $toolCount . ' banking tools. Move money, exchange, ramp, send SMS from Claude Desktop, Cursor, or any agent.',
        'keywords'    => 'MCP, Model Context Protocol, AI banking, Claude Desktop, Cursor, agent banking, OAuth banking, AI payments, ' . $brand,
    ])

    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'MCP', 'url' => url('/features/mcp')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'MCP', 'url' => url('/features/mcp')]
                ]])
                <div class="inline-flex items-center px-3 py-1 bg-emerald-500/15 backdrop-blur-sm rounded-full text-sm text-emerald-300 border border-emerald-400/30 mb-6">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full mr-2 animate-pulse"></span>
                    v7.11.0 &middot; New
                </div>
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    MCP-native banking
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    Connect Claude Desktop, Cursor, Continue.dev, or any spec-compliant agent to your {{ $brand }} account through one OAuth-protected JSON-RPC endpoint. Move money, exchange, on/off-ramp, send SMS — without writing API glue.
                </p>
                <div class="inline-flex items-center gap-3 bg-black/30 rounded-lg px-5 py-3 font-mono text-sm text-slate-200 mb-8">
                    <span class="text-emerald-300">$</span>
                    <span>npx -y &#64;finaegis/mcp</span>
                </div>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ url('/developers/mcp-tools') }}" class="btn-primary px-8 py-4 text-lg">Developer reference</a>
                    <a href="{{ $mcpUrl }}" class="btn-outline px-8 py-4 text-lg" rel="noopener">Live endpoint</a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent"></div>
    </section>

    <!-- Get started in 3 steps -->
    <section class="py-20 bg-slate-50" id="get-started">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-3">Get started in 3 steps</h2>
                <p class="text-slate-600 max-w-2xl mx-auto">From zero to "Claude paid my supplier" in about four minutes. Free to try — no credit card.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">1</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Create your {{ $brand }} account</h3>
                    <p class="text-sm text-slate-600 mb-4">Sign in with email — passwordless, OTP-only. You're live in under a minute.</p>
                    <a href="https://zelta.app" target="_blank" rel="noopener" class="btn-primary !px-4 !py-2 text-sm inline-flex">Open zelta.app →</a>
                </div>
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">2</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Add the connector to your AI</h3>
                    <p class="text-sm text-slate-600 mb-4">Claude Desktop, Claude.ai, Cursor, Continue.dev — copy-paste setup, three lines.</p>
                    <a href="{{ url('/developers/mcp-tools#connect') }}" class="btn-outline !px-4 !py-2 text-sm inline-flex">See connector setup →</a>
                </div>
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">3</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Authorize and go</h3>
                    <p class="text-sm text-slate-600 mb-4">First call opens a consent screen. You pick scopes, set a daily spending cap, and you're done. Token persists per client.</p>
                    <span class="text-sm text-slate-500 italic">No further steps — try a prompt.</span>
                </div>
            </div>
            <p class="text-center text-sm text-slate-500 mt-10">
                Looking for the technical details? See the <a href="{{ url('/developers/mcp-tools') }}" class="text-emerald-600 hover:underline font-medium">developer reference</a> for tool schemas, OAuth scopes, and error codes.
            </p>
        </div>
    </section>

    <!-- What MCP is -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold mb-6">What is MCP?</h2>
            <div class="space-y-4 text-slate-700 text-lg leading-relaxed">
                <p>The Model Context Protocol is Anthropic's open standard for letting AI agents talk to external systems. Instead of every agent vendor rolling its own integration shape, MCP gives them a single wire format — JSON-RPC over HTTP — for discovering tools, calling them, and reading context.</p>
                <p>For users, that means an agent you already use (Claude Desktop, Cursor, Continue.dev) can act on your accounts without an extra app or copy-pasted API keys. The agent asks for the scopes it needs, you approve them with a daily spending cap, and it gets a token bound to those limits.</p>
                <p>For developers, that means {{ $brand }} works with any spec-compliant client today, and any new client tomorrow — you don't ship new code per agent.</p>
            </div>
        </div>
    </section>

    <!-- Why Zelta is different -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Why {{ $brand }}'s MCP is different</h2>
                <p class="text-slate-600 max-w-2xl mx-auto">Most "MCP-compatible" launches stop at read-only data. Ours moves real money — under proper consent and audit.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Real money rails</h3>
                    <p class="text-slate-600 text-sm">Send payments, execute exchanges, on/off-ramp via Stripe Bridge. Not just balance reads — actual settlement, with audit-grade attribution to the token + scope + client.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-cyan-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Spending limits + idempotency</h3>
                    <p class="text-slate-600 text-sm">Per-token daily cap (default $500/24h, slider on consent). Atomic Redis lock prevents double-charge on agent retries. The reservation only sticks on success.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Multi-rail payments</h3>
                    <p class="text-slate-600 text-sm">Stripe, Tempo, Lightning, Card, x402 — all via the same MCP catalog. The agent picks a rail; the server settles it. SMS sends settle per-message via x402 micropayments.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Regulated foundation</h3>
                    <p class="text-slate-600 text-sm">{{ $brand }} is a real banking platform with KYC, AML, and audit trail compliance. Every $-impact agent action is attributed in the regulatory feed — agents inherit the platform's posture, not bolt-on permissioning.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- vs Legacy bank APIs -->
    <section class="py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-2">vs. legacy bank APIs</h2>
            <p class="text-slate-600 text-center mb-12">Connecting an agent to a traditional banking REST API is a per-agent, per-bank integration project. MCP collapses that.</p>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold text-slate-700"></th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-700">Legacy bank REST</th>
                            <th class="text-left px-6 py-4 font-semibold text-emerald-700">{{ $brand }} MCP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="px-6 py-3 font-medium">Discovery</td><td class="px-6 py-3 text-slate-600 text-sm">Per-bank Postman / Swagger; manual.</td><td class="px-6 py-3 text-sm">RFC 9728 + MCP <code class="font-mono text-xs">tools/list</code> — automatic.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Auth</td><td class="px-6 py-3 text-slate-600 text-sm">OAuth flavour per bank, per client type.</td><td class="px-6 py-3 text-sm">OAuth 2.1 + RFC 7591 DCR — agent self-registers.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Spending limits</td><td class="px-6 py-3 text-slate-600 text-sm">Bolted on per integration, if at all.</td><td class="px-6 py-3 text-sm">Per-token, set on consent screen, server-enforced.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Idempotency</td><td class="px-6 py-3 text-slate-600 text-sm">Header conventions vary; race-prone.</td><td class="px-6 py-3 text-sm">Required key + atomic SET-NX lock; no double-charge.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Audit + AML</td><td class="px-6 py-3 text-slate-600 text-sm">Manual mapping of agent → user → tx.</td><td class="px-6 py-3 text-sm">Every call attributed to token + scope + client.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">New client onboarding</td><td class="px-6 py-3 text-slate-600 text-sm">Bank ships new SDK or doc.</td><td class="px-6 py-3 text-sm">Spec-compliant client just connects.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Use cases -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12">What agents do with this</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="card-feature !p-6">
                    <h3 class="font-bold text-lg mb-3">Agent treasurer</h3>
                    <p class="text-slate-600 text-sm">A finance agent watches accounts and sweeps idle balances into yield, executes FX hedges against the daily cap, and reports back. Spending limit caps the worst case; idempotency keeps retries safe.</p>
                </div>
                <div class="card-feature !p-6">
                    <h3 class="font-bold text-lg mb-3">Customer-service co-pilot</h3>
                    <p class="text-slate-600 text-sm">A support agent reads transaction history, posts reversals up to a per-token cap, and triggers SMS on the user's behalf. Each settlement attributes the action to the support persona for audit.</p>
                </div>
                <div class="card-feature !p-6">
                    <h3 class="font-bold text-lg mb-3">Recurring agent workflows</h3>
                    <p class="text-slate-600 text-sm">Scheduled rebalance, monthly payouts, micro-grants. Refresh-token rollover means a long-lived workflow never has to re-prompt the user — until the user revokes the token.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats strip -->
    <section class="py-12 bg-fa-navy">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-white">
                <div><div class="text-4xl font-bold text-emerald-400">{{ $toolCount }}</div><div class="text-sm text-slate-300 mt-1">tools</div></div>
                <div><div class="text-4xl font-bold text-emerald-400">4</div><div class="text-sm text-slate-300 mt-1">resources</div></div>
                <div><div class="text-4xl font-bold text-emerald-400">{{ $scopeCount }}</div><div class="text-sm text-slate-300 mt-1">OAuth scopes</div></div>
                <div><div class="text-4xl font-bold text-emerald-400">5</div><div class="text-sm text-slate-300 mt-1">payment rails</div></div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold mb-4">Ship an agent against {{ $brand }} today</h2>
            <p class="text-slate-600 text-lg mb-8">No SDK install, no API key copy-paste. The npm relay handles OAuth and persists your token in the OS keychain.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers/mcp-tools') }}" class="btn-primary px-8 py-4 text-lg">Developer reference</a>
                <a href="https://github.com/FinAegis/core-banking-prototype-laravel/blob/main/docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md" class="btn-outline px-8 py-4 text-lg" rel="noopener">Quickstart on GitHub</a>
            </div>
        </div>
    </section>

@endsection
