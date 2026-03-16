@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'MCP & AI Agent Tools - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title' => 'MCP & AI Agent Tools - ' . $brand . ' Developer Documentation',
        'description' => $brand . ' Model Context Protocol — 16 banking tools for LLM agent integration across account, payment, exchange, compliance, and more.',
        'keywords' => $brand . ', MCP, Model Context Protocol, AI agent, tools, LLM, banking API',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .mcp-gradient { background: linear-gradient(135deg, #059669 0%, #0891b2 100%); }
    .code-container { position: relative; background: #0f1419; border-radius: 0.75rem; overflow: hidden; }
    .code-header { background: #0f172a; padding: 0.5rem 1rem; font-size: 0.75rem; font-family: 'Figtree', sans-serif; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; }
    .code-block { font-family: 'Fira Code', monospace; font-size: 0.875rem; line-height: 1.5; overflow-x: auto; white-space: pre; }
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
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">16 Banking Tools</span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">MCP & AI Agent Tools</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Model Context Protocol server with schema-driven banking tools for LLM agent integration.
            </p>
        </div>
    </div>
</section>

<!-- Overview -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Architecture</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-2xl mb-2">LLM</div>
                <p class="text-sm text-slate-600">Claude, GPT, or any MCP-compatible model</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-2xl mb-2">MCPServer</div>
                <p class="text-sm text-slate-600">Routes requests, validates schemas, caches results</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-2xl mb-2">ToolRegistry</div>
                <p class="text-sm text-slate-600">Discovers and manages 16 banking tools</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-5 text-center">
                <div class="text-2xl mb-2">Domain Services</div>
                <p class="text-sm text-slate-600">Real banking operations via DI-injected services</p>
            </div>
        </div>

        <!-- Tool call example -->
        <div class="code-container">
            <div class="code-header"><span>Tool execution via MCPServer</span></div>
            <pre class="code-block p-4 text-slate-300"><span class="text-purple-400">use</span> App\Domain\AI\MCP\MCPServer;
<span class="text-purple-400">use</span> App\Domain\AI\ValueObjects\MCPRequest;

<span class="text-orange-300">$request</span> = MCPRequest::create(<span class="text-green-400">'tools/call'</span>, [
    <span class="text-green-400">'name'</span>      => <span class="text-green-400">'account.balance'</span>,
    <span class="text-green-400">'arguments'</span> => [<span class="text-green-400">'account_uuid'</span> => <span class="text-green-400">'550e8400-...'</span>],
]);

<span class="text-orange-300">$response</span> = <span class="text-orange-300">$mcpServer</span>->handle(<span class="text-orange-300">$request</span>);

<span class="text-purple-400">if</span> (<span class="text-orange-300">$response</span>->isSuccess()) {
    <span class="text-orange-300">$balance</span> = <span class="text-orange-300">$response</span>->getData()[<span class="text-green-400">'toolResult'</span>][<span class="text-green-400">'total_value_usd'</span>];
}</pre>
        </div>
    </div>
</section>

<!-- Available Tools -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Available Tools</h2>
        <p class="text-slate-600 mb-8">16 tools across 7 categories. Each tool has JSON Schema input/output validation, authorization checks, and optional result caching.</p>

        @php
        $toolGroups = [
            'Account' => [
                ['name' => 'account.balance', 'desc' => 'Get current balance for an account', 'cache' => true],
                ['name' => 'account.create', 'desc' => 'Create a new account', 'cache' => false],
                ['name' => 'account.deposit', 'desc' => 'Deposit funds into an account', 'cache' => false],
                ['name' => 'account.withdraw', 'desc' => 'Withdraw funds from an account', 'cache' => false],
            ],
            'Payment' => [
                ['name' => 'payment.transfer', 'desc' => 'Transfer funds between accounts', 'cache' => false],
                ['name' => 'payment.status', 'desc' => 'Check payment status', 'cache' => true],
            ],
            'Exchange' => [
                ['name' => 'exchange.quote', 'desc' => 'Get exchange rate quote', 'cache' => true],
                ['name' => 'exchange.trade', 'desc' => 'Execute a trade', 'cache' => false],
                ['name' => 'exchange.liquidity-pool', 'desc' => 'Query liquidity pool info', 'cache' => true],
            ],
            'Compliance' => [
                ['name' => 'compliance.aml-screening', 'desc' => 'Run AML screening check', 'cache' => false],
                ['name' => 'compliance.kyc', 'desc' => 'Check KYC verification status', 'cache' => true],
            ],
            'Agent Protocol' => [
                ['name' => 'agent.payment', 'desc' => 'Agent-to-agent payment', 'cache' => false],
                ['name' => 'agent.escrow', 'desc' => 'Create/manage escrow', 'cache' => false],
                ['name' => 'agent.reputation', 'desc' => 'Query agent reputation score', 'cache' => true],
            ],
            'Transaction' => [
                ['name' => 'transaction.query', 'desc' => 'Natural language transaction search', 'cache' => true],
                ['name' => 'transaction.spending-analysis', 'desc' => 'AI spending analysis', 'cache' => true],
            ],
            'X402' => [
                ['name' => 'x402.payment', 'desc' => 'HTTP 402 payment protocol', 'cache' => false],
            ],
        ];
        @endphp

        <div class="space-y-4">
            @foreach($toolGroups as $category => $tools)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-semibold text-sm text-emerald-700 uppercase tracking-wider mb-3">{{ $category }}</h3>
                <div class="space-y-2">
                    @foreach($tools as $tool)
                    <div class="flex items-center gap-3">
                        <code class="code-font text-sm bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded w-56 flex-shrink-0">{{ $tool['name'] }}</code>
                        <span class="text-sm text-slate-600 flex-1">{{ $tool['desc'] }}</span>
                        @if($tool['cache'])
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">cacheable</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Building a Tool -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Building a Custom Tool</h2>
        <p class="text-slate-600 mb-8">Implement <code class="code-font bg-slate-100 px-1.5 py-0.5 rounded">MCPToolInterface</code> and register in the service provider.</p>

        <div class="space-y-6">
            <div class="code-container">
                <div class="code-header"><span>1. Implement the interface</span></div>
                <pre class="code-block p-4 text-slate-300"><span class="text-purple-400">use</span> App\Domain\AI\Contracts\MCPToolInterface;
<span class="text-purple-400">use</span> App\Domain\AI\ValueObjects\ToolExecutionResult;

<span class="text-purple-400">class</span> <span class="text-yellow-300">MyCustomTool</span> <span class="text-purple-400">implements</span> MCPToolInterface
{
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getName</span>(): <span class="text-green-400">string</span> { <span class="text-purple-400">return</span> <span class="text-green-400">'custom.my-tool'</span>; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getCategory</span>(): <span class="text-green-400">string</span> { <span class="text-purple-400">return</span> <span class="text-green-400">'custom'</span>; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getDescription</span>(): <span class="text-green-400">string</span> { <span class="text-purple-400">return</span> <span class="text-green-400">'My custom banking tool'</span>; }

    <span class="text-purple-400">public function</span> <span class="text-blue-300">getInputSchema</span>(): <span class="text-green-400">array</span>
    {
        <span class="text-purple-400">return</span> [
            <span class="text-green-400">'type'</span>       => <span class="text-green-400">'object'</span>,
            <span class="text-green-400">'properties'</span> => [
                <span class="text-green-400">'account_id'</span> => [<span class="text-green-400">'type'</span> => <span class="text-green-400">'string'</span>, <span class="text-green-400">'description'</span> => <span class="text-green-400">'Account UUID'</span>],
            ],
            <span class="text-green-400">'required'</span>   => [<span class="text-green-400">'account_id'</span>],
        ];
    }

    <span class="text-purple-400">public function</span> <span class="text-blue-300">execute</span>(<span class="text-green-400">array</span> <span class="text-orange-300">$params</span>, ?<span class="text-green-400">string</span> <span class="text-orange-300">$conversationId</span>): ToolExecutionResult
    {
        <span class="text-slate-500">// Your business logic here</span>
        <span class="text-purple-400">return</span> ToolExecutionResult::success([<span class="text-green-400">'result'</span> => <span class="text-green-400">'done'</span>]);
    }

    <span class="text-purple-400">public function</span> <span class="text-blue-300">getOutputSchema</span>(): <span class="text-green-400">array</span> { <span class="text-purple-400">return</span> []; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getCapabilities</span>(): <span class="text-green-400">array</span> { <span class="text-purple-400">return</span> [<span class="text-green-400">'read'</span>]; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">isCacheable</span>(): <span class="text-green-400">bool</span> { <span class="text-purple-400">return</span> <span class="text-orange-300">false</span>; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">getCacheTtl</span>(): <span class="text-green-400">int</span> { <span class="text-purple-400">return</span> <span class="text-orange-300">0</span>; }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">validateInput</span>(<span class="text-green-400">array</span> <span class="text-orange-300">$params</span>): <span class="text-green-400">bool</span> { <span class="text-purple-400">return</span> isset(<span class="text-orange-300">$params</span>[<span class="text-green-400">'account_id'</span>]); }
    <span class="text-purple-400">public function</span> <span class="text-blue-300">authorize</span>(?<span class="text-green-400">string</span> <span class="text-orange-300">$userId</span>): <span class="text-green-400">bool</span> { <span class="text-purple-400">return</span> Auth::check(); }
}</pre>
            </div>

            <div class="code-container">
                <div class="code-header"><span>2. Register in MCPToolServiceProvider</span></div>
                <pre class="code-block p-4 text-slate-300"><span class="text-slate-500">// app/Providers/MCPToolServiceProvider.php</span>
<span class="text-purple-400">protected array</span> <span class="text-orange-300">$tools</span> = [
    <span class="text-slate-500">// ... existing tools</span>
    \App\Domain\Custom\MCP\MyCustomTool::class,  <span class="text-slate-500">// Add here</span>
];</pre>
            </div>
        </div>
    </div>
</section>

<!-- MCPServer Methods -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold mb-4">Server Methods</h2>
        <p class="text-slate-600 mb-6">The MCPServer handles these JSON-RPC methods:</p>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Method</th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-700">Purpose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">initialize</code></td><td class="px-4 py-2.5 text-slate-600">Initialize server, return capabilities</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">tools/list</code></td><td class="px-4 py-2.5 text-slate-600">List all available tools with schemas</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">tools/call</code></td><td class="px-4 py-2.5 text-slate-600">Execute a tool with parameters</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">resources/list</code></td><td class="px-4 py-2.5 text-slate-600">List available resources</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">resources/read</code></td><td class="px-4 py-2.5 text-slate-600">Read a resource by URI</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">prompts/list</code></td><td class="px-4 py-2.5 text-slate-600">List prompt templates</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">prompts/get</code></td><td class="px-4 py-2.5 text-slate-600">Get a specific prompt</td></tr>
                    <tr><td class="px-4 py-2.5"><code class="code-font text-sm">completion</code></td><td class="px-4 py-2.5 text-slate-600">Completion suggestions</td></tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <h3 class="font-semibold text-lg mb-3">REST API</h3>
            <div class="flex items-center gap-3 border border-slate-200 rounded-lg px-4 py-3 bg-white">
                <span class="code-font font-semibold text-green-600">GET</span>
                <code class="code-font text-sm text-slate-700">/api/ai/mcp/tools</code>
                <span class="text-sm text-slate-500 ml-auto">List all tools with schemas</span>
            </div>
        </div>
    </div>
</section>

<!-- Execution Flow -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold mb-4">Tool Execution Flow</h2>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            @foreach([
                ['Validate', 'Input validated against JSON Schema'],
                ['Authorize', 'User auth + tool-level permission check'],
                ['Cache Check', 'Return cached result if available'],
                ['Execute', 'Run tool logic via domain service'],
                ['Record', 'Log to event store + cache result'],
            ] as $i => $step)
            <div class="flex flex-col items-center text-center">
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center mb-2">{{ $i + 1 }}</div>
                <h4 class="font-semibold text-sm">{{ $step[0] }}</h4>
                <p class="text-xs text-slate-500 mt-1">{{ $step[1] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Back -->
<section class="bg-slate-50 py-12">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('developers') }}" class="inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Developer Hub
        </a>
    </div>
</section>

@endsection
