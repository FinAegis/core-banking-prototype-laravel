@extends('layouts.public')

@php
    $brand     = config('brand.name', 'Zelta');
    // Same rule as tools/list (enabled flag + requires_config switch).
    $tools     = \App\Domain\MCP\Server\McpToolCatalog::enabledTools();
    $toolCount = count($tools);
    /** @var array<string, string> $scopes */
    $scopes      = (array) config('mcp.scopes', []);
    $resources   = (array) config('mcp.resources', []);
    $serverName  = (string) config('mcp.server_info.name', 'Zelta');
    $mcpHost     = (string) config('mcp.host', 'mcp.zelta.app');
    $mcpUrl      = 'https://' . $mcpHost . '/mcp';
    $authServer  = (string) config('mcp.authorization_server', 'https://zelta.app');
    $resourceUri = (string) config('mcp.resource_uri', 'https://' . $mcpHost);
    $prmUrl      = rtrim($resourceUri, '/') . '/.well-known/oauth-protected-resource';
    $asMetaUrl   = rtrim($authServer, '/') . '/.well-known/oauth-authorization-server';
@endphp

@section('title', 'Model Context Protocol Server for AI Agents - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title'       => 'Model Context Protocol Server for AI Agents - ' . $brand . ' Developer Documentation',
        'description' => 'The public Model Context Protocol server lets AI agents such as Claude Desktop and Cursor work with an account. It offers ' . $toolCount . ' banking tools and ' . count($resources) . ' read-only resources, and every connection is protected by OAuth.',
        'keywords'    => $brand . ', MCP, Model Context Protocol, AI agent, Claude Desktop, Cursor, OAuth 2.1, RFC 7591, RFC 9728, banking API, JSON-RPC',
        'canonical'   => url('/developers/mcp-tools'),
    ])
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "WebPage",
      "name": "Model Context Protocol Server for AI Agents",
      "url":  "{{ url('/developers/mcp-tools') }}",
      "description": "Reference for the public Model Context Protocol server. It lists the tools, the OAuth scopes and the discovery endpoints.",
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
                {{ $toolCount }} tools · {{ count($resources) }} resources · {{ count($scopes) }} OAuth scopes
            </span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Model Context Protocol Server</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                The Model Context Protocol server connects AI agents to a {{ $serverName }} account. Claude Desktop, Cursor, Continue.dev and other agents that follow the protocol use one endpoint, and OAuth protects every connection.
            </p>
            <div class="mt-8 inline-flex items-center gap-3 bg-black/30 rounded-lg px-5 py-3 code-font text-sm">
                <span class="text-emerald-300">$</span>
                <span>npx -y &#64;finaegis/mcp</span>
            </div>
            <p class="mt-3 text-sm text-white/70">You can also use the remote address directly: <code class="code-font">{{ $mcpUrl }}</code></p>
        </div>
    </div>
</section>

<!-- Connect in 30s -->
<section class="bg-white py-16" id="connect">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Connect your agent</h2>
        <p class="text-slate-600 mb-6">The first launch opens a browser window where you approve the connection. Each client keeps its own token, so later launches connect without asking again.</p>

        <div class="mb-8 p-4 rounded-lg bg-emerald-50 border border-emerald-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-emerald-900">
                <strong>Before you start:</strong> you need a {{ $serverName }} account. You can sign up for free at <a href="https://zelta.app" target="_blank" rel="noopener" class="font-semibold underline hover:no-underline">zelta.app</a> with a one-time code sent to your email. Then come back here.
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Claude Desktop</h3>
                <p class="text-sm text-slate-600 mb-3">Add this to <code class="code-font text-xs">claude_desktop_config.json</code>.</p>
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
                <p class="text-sm text-slate-600 mb-3">Open Settings, then Features, then MCP, and choose Add Server.</p>
                <pre class="code-block bg-slate-900 text-slate-200 rounded-md p-3 text-xs">URL: {{ $mcpUrl }}</pre>
                <p class="text-xs text-slate-500 mt-3">Continue.dev uses the same URL under <code class="code-font">experimental.modelContextProtocolServer</code>.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Clients that only run local servers</h3>
                <p class="text-sm text-slate-600 mb-3">Older clients cannot connect to a remote server over HTTP. They can run this local wrapper instead.</p>
                <pre class="code-block bg-slate-900 text-slate-200 rounded-md p-3 text-xs">npx -y &#64;finaegis/mcp</pre>
                <p class="text-xs text-slate-500 mt-3">The wrapper keeps the token in your operating system's keychain. Run it with <code class="code-font">--logout</code> to remove the token.</p>
            </div>
        </div>
    </div>
</section>

<!-- Discovery -->
<section class="bg-slate-50 py-16" id="discovery">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Discovery</h2>
        <p class="text-slate-600 mb-8">Clients read these live OAuth metadata documents before they connect. They tell the client where to register and where to ask for a token.</p>
        <div class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-emerald-600">GET</span>
                <code class="code-font text-sm break-all"><a href="{{ $prmUrl }}" rel="noopener" class="hover:underline">{{ $prmUrl }}</a></code>
                <span class="text-sm text-slate-500 md:ml-auto">Protected resource metadata (RFC 9728)</span>
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-emerald-600">GET</span>
                <code class="code-font text-sm break-all"><a href="{{ $asMetaUrl }}" rel="noopener" class="hover:underline">{{ $asMetaUrl }}</a></code>
                <span class="text-sm text-slate-500 md:ml-auto">Authorization server metadata (RFC 8414)</span>
            </div>
            <div class="flex flex-col md:flex-row md:items-center gap-2 bg-white border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font font-semibold text-orange-600">POST</span>
                <code class="code-font text-sm break-all">{{ rtrim($authServer, '/') }}/oauth/register</code>
                <span class="text-sm text-slate-500 md:ml-auto">Dynamic client registration (RFC 7591)</span>
            </div>
        </div>
    </div>
</section>

<!-- Scope catalog -->
<section class="bg-white py-16" id="scopes">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Scope catalog</h2>
        <p class="text-slate-600 mb-8">A scope is a permission. The user grants scopes on the consent screen the first time an agent connects. Each tool needs exactly one scope, except <code class="code-font">mpp.discovery</code>, which is public.</p>
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
        <h2 class="text-3xl font-bold mb-2">Tool catalog</h2>
        <p class="text-slate-600 mb-8">This server has {{ $toolCount }} tools switched on. A tool that is switched off does not appear in <code class="code-font">tools/list</code>, and calling it returns <code class="code-font">-32004</code>. Operators switch tools on and off with the <code class="code-font">MCP_TOOL_*</code> environment variables. The write tools that create accounts, send payments, trade and send text messages are off by default outside demo and test environments.</p>
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
            <strong>Live schemas:</strong> call <code class="code-font">tools/list</code> in a signed-in session to get each tool's <code class="code-font">inputSchema</code> and <code class="code-font">outputSchema</code>. The server's answer is always the current definition.
        </p>
    </div>
</section>

<!-- Resources -->
<section class="bg-white py-16" id="resources">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Read-only resources</h2>
        <p class="text-slate-600 mb-8">A resource is an address that an agent can read without calling a tool. Reading a resource costs less than a tool call and suits browsing.</p>
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
        <p class="text-slate-600 mb-8">These codes describe protocol errors. When a tool itself fails, the result carries <code class="code-font">isError: true</code> instead of one of these codes.</p>
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
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32001</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">UNAUTHENTICATED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">The token is missing or has expired. Refresh it and try again. The server answers with status 401 and a <code class="code-font text-xs">WWW-Authenticate</code> header.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32002</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">IDEMPOTENCY_KEY_REUSED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">The same idempotency key was sent with different arguments.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32003</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">SPENDING_LIMIT_EXCEEDED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">The daily spending limit is reached. Wait until the 24-hour window moves on.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32004</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">TOOL_DISABLED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">The operator has switched this tool off with <code class="code-font text-xs">MCP_TOOL_*</code>.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32005</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">IDEMPOTENCY_KEY_IN_FLIGHT</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">A retry arrived while the first call with the same key is still running. Wait and try again.</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font">-32006</code></td><td class="px-4 py-2.5"><code class="code-font text-xs">USER_CONTEXT_REQUIRED</code></td><td class="px-4 py-2.5 text-slate-600 text-sm">A token from the client credentials grant cannot call a tool that acts for a user.</td></tr>
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
                <p class="text-slate-600 text-sm">Each token has its own spending limit. The default is <strong>${{ number_format(((int) config('mcp.spending.default_daily_limit_minor', 50000)) / 100, 2) }} in any 24 hours</strong>. The user can choose a different limit on the consent screen. The server reserves the amount before the tool runs and releases it if anything fails. The limit applies to <code class="code-font text-xs">payment.transfer</code>, <code class="code-font text-xs">ramp.start</code> and <code class="code-font text-xs">sms.send</code>, which costs a fixed amount per message. It does not count <code class="code-font text-xs">exchange.trade</code> yet, because the value of a trade is known only after the order fills.</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-6">
                <h3 class="font-bold text-lg mb-3">Idempotency</h3>
                <p class="text-slate-600 text-sm">Every write tool needs an <code class="code-font text-xs">idempotency_key</code>, such as a UUID, of at most 128 characters. The server keeps the result for 24 hours and returns it for a repeated call. A lock in Redis stops two retries that arrive at the same time from both running.</p>
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
