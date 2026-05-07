@extends('layouts.public')

@php
    $brand     = config('brand.name', 'Zelta');
    /** @var array<string, array<string, mixed>> $allTools */
    $allTools  = (array) config('mcp.tools', []);
    $tools     = array_filter($allTools, fn ($t) => (bool) ($t['enabled'] ?? false));
    $toolCount = count($tools);
    /** @var array<string, string> $scopes */
    $scopes      = (array) config('mcp.scopes', []);
    $resources   = (array) config('mcp.resources', []);
    $mcpHost     = (string) config('mcp.host', 'mcp.zelta.app');
    $mcpUrl      = 'https://' . $mcpHost . '/mcp';
    $authServer  = (string) config('mcp.authorization_server', 'https://zelta.app');
    $resourceUri = (string) config('mcp.resource_uri', 'https://' . $mcpHost);
    $prmUrl      = rtrim($resourceUri, '/') . '/.well-known/oauth-protected-resource';
    $asMetaUrl   = rtrim($authServer, '/') . '/.well-known/oauth-authorization-server';
@endphp

@section('title', 'MCP & AI Agent Tools - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title'       => 'MCP & AI Agent Tools - ' . $brand . ' Developer Documentation',
        'description' => $brand . ' Model Context Protocol — public OAuth-protected MCP server with ' . $toolCount . ' banking tools and 4 read-context resources for Claude Desktop, Cursor, and any spec-compliant AI agent.',
        'keywords'    => $brand . ', MCP, Model Context Protocol, AI agent, Claude Desktop, Cursor, OAuth 2.1, RFC 7591, RFC 9728, banking API, JSON-RPC',
        'canonical'   => url('/developers/mcp-tools'),
    ])
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "WebPage",
      "name": "MCP & AI Agent Tools",
      "url":  "{{ url('/developers/mcp-tools') }}",
      "description": "{{ $brand }} public MCP server reference: tool catalog, OAuth scopes, discovery endpoints.",
      "mainEntity": {
        "@@type": "WebAPI",
        "name": "{{ $brand }} MCP Server",
        "documentation": "{{ url('/developers/mcp-tools') }}",
        "termsOfService": "{{ url('/terms') }}",
        "url": "{{ $mcpUrl }}"
      }
    }
    </script>
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font     { font-family: 'Fira Code', monospace; }
    .mcp-gradient  { background: linear-gradient(135deg, #059669 0%, #0891b2 100%); }
    .code-container { position: relative; background: #0f1419; border-radius: 0.75rem; overflow: hidden; }
    .code-header    { background: #0f172a; padding: 0.5rem 1rem; font-size: 0.75rem; font-family: 'Figtree', sans-serif; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; }
    .code-block     { font-family: 'Fira Code', monospace; font-size: 0.875rem; line-height: 1.5; overflow-x: auto; white-space: pre; }
    details > summary { cursor: pointer; }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="mcp-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute top-20 left-10 w-72 h-72 bg-emerald-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-cyan-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">
                {{ $toolCount }} tools · 4 resources · {{ count($scopes) }} OAuth scopes
            </span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">{{ $brand }} MCP Server</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Connect Claude Desktop, Cursor, Continue.dev, or any spec-compliant agent to your {{ $brand }} account through one OAuth-protected JSON-RPC endpoint.
            </p>
            <div class="mt-8 inline-flex items-center gap-3 bg-black/30 rounded-lg px-5 py-3 code-font text-sm">
                <span class="text-emerald-300">$</span>
                <span>npx -y &#64;finaegis/mcp</span>
            </div>
            <p class="mt-3 text-sm text-white/70">Or use the remote URL directly: <code class="code-font">{{ $mcpUrl }}</code></p>
        </div>
    </div>
</section>

<!-- Connect in 30s -->
<section class="bg-white py-16" id="connect">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Connect in 30 seconds</h2>
        <p class="text-slate-600 mb-6">First launch opens a browser for OAuth consent. Token persists per-client; subsequent launches are silent.</p>

        <div class="mb-8 p-4 rounded-lg bg-emerald-50 border border-emerald-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-emerald-900">
                <strong>Prerequisite:</strong> a {{ $brand }} account. Sign up free at <a href="https://zelta.app" target="_blank" rel="noopener" class="font-semibold underline hover:no-underline">zelta.app</a> — passwordless email-OTP, takes under a minute. Then come back here.
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Claude Desktop</h3>
                <p class="text-sm text-slate-600 mb-3">Add to <code class="code-font text-xs">claude_desktop_config.json</code>:</p>
                <pre class="code-block bg-slate-900 text-slate-200 rounded-md p-3 text-xs">{
  "mcpServers": {
    "{{ strtolower($brand) }}": {
      "url": "{{ $mcpUrl }}"
    }
  }
}</pre>
            </div>
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Cursor</h3>
                <p class="text-sm text-slate-600 mb-3">Settings → Features → MCP → Add Server.</p>
                <pre class="code-block bg-slate-900 text-slate-200 rounded-md p-3 text-xs">URL: {{ $mcpUrl }}</pre>
                <p class="text-xs text-slate-500 mt-3">Continue.dev uses the same URL under <code class="code-font">experimental.modelContextProtocolServer</code>.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Stdio-only clients</h3>
                <p class="text-sm text-slate-600 mb-3">For older clients without remote streamable-HTTP support:</p>
                <pre class="code-block bg-slate-900 text-slate-200 rounded-md p-3 text-xs">npx -y &#64;finaegis/mcp</pre>
                <p class="text-xs text-slate-500 mt-3">Persists token in OS keychain. <code class="code-font">--logout</code> clears it.</p>
            </div>
        </div>
    </div>
</section>

<!-- Discovery -->
<section class="bg-slate-50 py-16" id="discovery">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Discovery</h2>
        <p class="text-slate-600 mb-8">Live OAuth metadata clients use to bootstrap the handshake.</p>
        <div class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-emerald-600">GET</span>
                <code class="code-font text-sm break-all"><a href="{{ $prmUrl }}" rel="noopener" class="hover:underline">{{ $prmUrl }}</a></code>
                <span class="text-sm text-slate-500 md:ml-auto">RFC 9728 — protected resource metadata</span>
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-emerald-600">GET</span>
                <code class="code-font text-sm break-all"><a href="{{ $asMetaUrl }}" rel="noopener" class="hover:underline">{{ $asMetaUrl }}</a></code>
                <span class="text-sm text-slate-500 md:ml-auto">RFC 8414 — authorization server metadata</span>
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-orange-600">POST</span>
                <code class="code-font text-sm break-all">{{ rtrim($authServer, '/') }}/oauth/register</code>
                <span class="text-sm text-slate-500 md:ml-auto">RFC 7591 — dynamic client registration</span>
            </div>
        </div>
    </div>
</section>

<!-- Scope catalog -->
<section class="bg-white py-16" id="scopes">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Scope catalog</h2>
        <p class="text-slate-600 mb-8">Users grant scopes on the consent screen at first connection. Each tool requires exactly one scope; <code class="code-font">mpp.discovery</code> is public.</p>
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Scope</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($scopes as $name => $desc)
                    <tr>
                        <td class="px-4 py-3 align-top"><code class="code-font text-sm font-semibold text-cyan-700">{{ $name }}</code></td>
                        <td class="px-4 py-3 text-slate-600 text-sm">{{ $desc }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Tool catalog -->
<section class="bg-slate-50 py-16" id="tools">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Tool catalog (v1)</h2>
        <p class="text-slate-600 mb-8">{{ $toolCount }} enabled tools. Disabled tools are omitted from <code class="code-font">tools/list</code> AND return <code class="code-font">-32004</code> if invoked. Operators flip them via <code class="code-font">MCP_TOOL_*</code> env vars.</p>
        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Tool</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Scope</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Type</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($tools as $name => $entry)
                    <tr>
                        <td class="px-4 py-3 align-top"><code class="code-font text-sm font-semibold">{{ $name }}</code></td>
                        <td class="px-4 py-3 align-top">
                            @if(! empty($entry['scope']))
                                <code class="code-font text-xs text-cyan-700">{{ $entry['scope'] }}</code>
                            @else
                                <span class="text-xs text-slate-500">public</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-xs">
                            @if(! empty($entry['is_write']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-semibold">write</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold">read</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                            @if(! empty($entry['is_payment']))
                                idempotency_key + spending limit
                            @elseif(! empty($entry['is_write']))
                                idempotency_key required
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm text-slate-500 mt-4">
            <strong>Live schemas:</strong> call <code class="code-font">tools/list</code> on an authenticated session to fetch every tool's <code class="code-font">inputSchema</code> and <code class="code-font">outputSchema</code> — the wire is the source of truth.
        </p>
    </div>
</section>

<!-- Resources -->
<section class="bg-white py-16" id="resources">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Resources (read-context)</h2>
        <p class="text-slate-600 mb-8">URI primitives an agent can pull into its window without a tool call — cheaper, cached, friendlier for browsing.</p>
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">URI pattern</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Scope</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($resources as $uri => $entry)
                    <tr>
                        <td class="px-4 py-3"><code class="code-font text-sm">{{ $uri }}</code></td>
                        <td class="px-4 py-3 text-sm"><code class="code-font text-xs text-cyan-700">{{ $entry['scope'] ?? '—' }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Error code reference -->
<section class="bg-slate-50 py-16" id="errors">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">JSON-RPC error codes</h2>
        <p class="text-slate-600 mb-8">Wire-protocol errors. Tool-level failures use <code class="code-font">isError: true</code> in the result envelope, not these codes.</p>
        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Code</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Name</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Meaning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32001</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">UNAUTHENTICATED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">Missing or expired bearer; refresh and retry. Returned with 401 + <code class="code-font text-xs">WWW-Authenticate</code>.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32002</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">IDEMPOTENCY_KEY_REUSED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">Same key used with different args.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32003</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">SPENDING_LIMIT_EXCEEDED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">Daily limit hit; wait for window reset.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32004</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">TOOL_DISABLED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">Operator-disabled via <code class="code-font text-xs">MCP_TOOL_*</code>.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32005</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">IDEMPOTENCY_KEY_IN_FLIGHT</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">Concurrent retry of an in-progress write; back off.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32006</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">USER_CONTEXT_REQUIRED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">client_credentials grant cannot call user-bound tool.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Spending + idempotency -->
<section class="bg-white py-16" id="policies">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-6">Built-in policies</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Spending limits</h3>
                <p class="text-slate-600 text-sm">Per-token, not per-scope. Default <strong>${{ number_format(((int) config('mcp.spending.default_daily_limit_minor', 50000)) / 100, 2) }} / 24h rolling window</strong>. Slider on the consent screen lets the user pick a different cap. Reservations are atomic: the saga reserves before the tool runs and rolls back on any error.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Idempotency</h3>
                <p class="text-slate-600 text-sm">Every write tool requires <code class="code-font text-xs">idempotency_key</code> (UUID, ≤128 chars). Server caches result for 24h. Atomic Redis SET-NX lock prevents two concurrent retries from both executing.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="bg-slate-50 py-12">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('developers') }}" class="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Developer Hub
        </a>
    </div>
</section>

@endsection
