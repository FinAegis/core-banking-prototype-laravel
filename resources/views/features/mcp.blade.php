@extends('layouts.public')

@php
    $brand     = config('brand.name', 'Zelta');
    // Same rule as tools/list (enabled flag + requires_config switch).
    $tools     = \App\Domain\MCP\Server\McpToolCatalog::enabledTools();
    $toolCount = count($tools);
    $scopeCount = count((array) config('mcp.scopes', []));
    $mcpUrl    = 'https://' . (string) config('mcp.host', 'mcp.zelta.app') . '/mcp';
@endphp

@section('title', 'Model Context Protocol Server for AI Agents | ' . $brand)

@section('seo')
    @include('partials.seo', [
        'title'       => 'Model Context Protocol Server for AI Agents | ' . $brand,
        'description' => 'A public Model Context Protocol server with ' . $toolCount . ' banking tools. AI agents such as Claude Desktop and Cursor can check balances, send payments, exchange currency and send SMS within the limits you approve.',
        'keywords'    => 'MCP, Model Context Protocol, AI banking, Claude Desktop, Cursor, agent banking, OAuth banking, AI payments, ' . $brand,
    ])

    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Model Context Protocol', 'url' => url('/features/mcp')]
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
                    ['name' => 'Model Context Protocol', 'url' => url('/features/mcp')]
                ]])
                <div class="inline-flex items-center px-3 py-1 bg-emerald-500/15 backdrop-blur-sm rounded-full text-sm text-emerald-300 border border-emerald-400/30 mb-6">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full mr-2 animate-pulse"></span>
                    v7.18.1
                </div>
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Model Context Protocol server for banking
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    The server lets an AI agent work with your Zelta account. Zelta is the hosted wallet built on FinAegis. Claude Desktop, Cursor, Continue.dev and other agents that follow the specification connect to one endpoint protected by OAuth. The agent can then use payment, exchange and SMS tools without any custom integration code.
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
                <p class="text-slate-600 max-w-2xl mx-auto">Three steps take you from a new account to the first tool call from your agent.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">1</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Create a Zelta account</h3>
                    <p class="text-sm text-slate-600 mb-4">You sign in with a one-time code sent to your email. There is no password.</p>
                    <a href="https://zelta.app" target="_blank" rel="noopener" class="btn-primary !px-4 !py-2 text-sm inline-flex">Open zelta.app →</a>
                </div>
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">2</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Add the connector to your AI</h3>
                    <p class="text-sm text-slate-600 mb-4">The setup page has a short snippet for Claude Desktop, Claude.ai, Cursor and Continue.dev. You paste it into your agent's settings.</p>
                    <a href="{{ url('/developers/mcp-tools#connect') }}" class="btn-outline !px-4 !py-2 text-sm inline-flex">See connector setup →</a>
                </div>
                <div class="border border-slate-200 rounded-xl p-6 relative">
                    <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white font-bold flex items-center justify-center text-lg shadow-md">3</div>
                    <h3 class="font-bold text-lg mb-2 mt-2">Authorize and go</h3>
                    <p class="text-sm text-slate-600 mb-4">The first call opens a consent screen. You choose what the agent may do and set a daily spending limit. The agent then keeps its access token until you revoke it.</p>
                    <span class="text-sm text-slate-500 italic">You can now ask your agent to use the tools.</span>
                </div>
            </div>
            <p class="text-center text-sm text-slate-500 mt-10">
                The <a href="{{ url('/developers/mcp-tools') }}" class="text-emerald-600 hover:underline font-medium">developer reference</a> lists the tool schemas, the OAuth scopes and the error codes.
            </p>
        </div>
    </section>

    <!-- What MCP is -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold mb-6">What Is the Model Context Protocol?</h2>
            <div class="space-y-4 text-slate-700 text-lg leading-relaxed">
                <p>The Model Context Protocol is an open standard from Anthropic. It lets AI agents work with outside systems. Every agent uses the same message format, which is JSON-RPC over HTTP. With it an agent finds the available tools, calls them and reads context.</p>
                <p>For users, this means an agent you already use can act on your account. You do not need an extra app, and you do not copy API keys. The agent asks for the permissions it needs. You approve them and set a daily spending limit. The agent then gets a token that is bound to those limits.</p>
                <p>For developers, this means {{ $brand }} works with any client that follows the specification. A new agent can connect without new code on our side.</p>
            </div>
        </div>
    </section>

    <!-- Why Zelta is different -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">How the {{ $brand }} Server Is Different</h2>
                <p class="text-slate-600 max-w-2xl mx-auto">Many servers of this kind only read data. This server can also make payments and other changes. Each change needs your consent and is recorded in the audit log.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Payment rails</h3>
                    <p class="text-slate-600 text-sm">The agent can use payment and exchange tools. It stays within the permissions and spending limits you set. Each call is linked to the token, the permission and the client that made it. Converting stablecoins to fiat, where available, will be provided by licensed third parties under their own terms.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-cyan-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Spending limits and safe retries</h3>
                    <p class="text-slate-600 text-sm">Each token has a daily spending limit. The default is $500 in 24 hours, and you can change it on the consent screen. A lock in Redis stops a retry from charging you twice. The amount is only counted against the limit when the payment succeeds.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Multi-rail payments</h3>
                    <p class="text-slate-600 text-sm">One tool catalog covers five payment rails. They are Stripe, Tempo, Lightning, card and x402. The agent chooses the rail. Each SMS counts against the daily send limit and the token's spending limit. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                </div>
                <div class="card-feature !p-6">
                    <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg mb-2">Audit attribution</h3>
                    <p class="text-slate-600 text-sm">Every agent action that moves money is written to the audit log. The entry names the token, the permission and the client.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- vs Legacy bank APIs -->
    <section class="py-20 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-2">Compared with traditional bank APIs</h2>
            <p class="text-slate-600 text-center mb-12">With a traditional bank REST API, each agent needs its own integration for each bank. With the Model Context Protocol, one standard connection works for every agent.</p>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold text-slate-700"></th>
                            <th class="text-left px-6 py-4 font-semibold text-slate-700">Legacy bank REST</th>
                            <th class="text-left px-6 py-4 font-semibold text-emerald-700">{{ $brand }} server</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="px-6 py-3 font-medium">Discovery</td><td class="px-6 py-3 text-slate-600 text-sm">Each bank publishes its own documentation. A developer reads it by hand.</td><td class="px-6 py-3 text-sm">The agent finds the tools by itself through the protected resource metadata standard and the <code class="font-mono text-xs">tools/list</code> call.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Auth</td><td class="px-6 py-3 text-slate-600 text-sm">Each bank has its own OAuth variant for each type of client.</td><td class="px-6 py-3 text-sm">OAuth 2.1 with dynamic client registration. The agent registers itself.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Spending limits</td><td class="px-6 py-3 text-slate-600 text-sm">Each integration adds its own limits, if it has any.</td><td class="px-6 py-3 text-sm">Each token has a limit. You set it on the consent screen, and the server enforces it.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Idempotency</td><td class="px-6 py-3 text-slate-600 text-sm">Each bank handles repeated requests differently. Two requests at the same time can both go through.</td><td class="px-6 py-3 text-sm">Every payment call needs a unique key. A lock stops a second charge.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">Audit and anti-money laundering checks</td><td class="px-6 py-3 text-slate-600 text-sm">Someone links each agent to a user and a transaction by hand.</td><td class="px-6 py-3 text-sm">Every call is linked to its token, permission and client.</td></tr>
                        <tr><td class="px-6 py-3 font-medium">New client onboarding</td><td class="px-6 py-3 text-slate-600 text-sm">The bank has to publish a new client library or new documentation.</td><td class="px-6 py-3 text-sm">Any client that follows the specification can connect.</td></tr>
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
                    <p class="text-slate-600 text-sm">A finance agent watches the balances and converts currency within the daily limit. It then reports back to you. The spending limit caps the largest possible loss. Unique request keys make retries safe.</p>
                </div>
                <div class="card-feature !p-6">
                    <h3 class="font-bold text-lg mb-3">Customer-service co-pilot</h3>
                    <p class="text-slate-600 text-sm">A support agent reads the transaction history and checks the status of a payment. It can also send an SMS for the user. Each action is recorded in the audit log under that agent's token.</p>
                </div>
                <div class="card-feature !p-6">
                    <h3 class="font-bold text-lg mb-3">Recurring agent workflows</h3>
                    <p class="text-slate-600 text-sm">An agent can run regular tasks such as monthly payouts or small grants. Its token renews itself, so the agent does not ask you to approve it again. It stops working when you revoke the token.</p>
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
            <h2 class="text-3xl font-bold mb-4">Connect your agent to Zelta</h2>
            <p class="text-slate-600 text-lg mb-8">You do not need to install a client library or copy an API key. The npm package handles the OAuth sign-in for you. It stores your token in a private file on your computer, or in the system keychain if you choose.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/developers/mcp-tools') }}" class="btn-primary px-8 py-4 text-lg">Developer reference</a>
                <a href="https://github.com/FinAegis/core-banking-prototype-laravel/blob/main/docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md" class="btn-outline px-8 py-4 text-lg" rel="noopener">Quickstart on GitHub</a>
            </div>
        </div>
    </section>

@endsection
