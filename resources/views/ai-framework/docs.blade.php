@extends('layouts.public')

@section('title', 'AI Framework Documentation - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Framework Documentation - Architecture & Integration Guide',
        'description' => 'Technical documentation for the ' . config('brand.name', 'Zelta') . ' AI Agent Framework. Learn about MCP tools, A2A protocol, x402 agent payments, spending controls, and integration patterns.',
        'keywords' => 'AI framework docs, MCP integration, A2A protocol, agent payments, x402, financial AI documentation, LLM integration',
    ])
@endsection

@push('styles')
<style>
    .doc-section {
        scroll-margin-top: 100px;
    }
</style>
@endpush

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 text-sm text-gray-300 mb-4">
                <a href="{{ route('ai-framework') }}" class="hover:text-white">AI Framework</a>
                <span>/</span>
                <span class="text-white">Documentation</span>
            </div>
            <h1 class="text-4xl font-bold mb-4">AI Framework Documentation</h1>
            <p class="text-xl text-gray-300 max-w-3xl">
                Architecture guide, integration patterns, and API reference for the {{ config('brand.name', 'Zelta') }} AI Agent Framework.
            </p>
        </div>
    </section>

    <!-- Content -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-4 lg:gap-12">

                <!-- Sidebar Navigation -->
                <nav class="hidden lg:block lg:col-span-1">
                    <div class="sticky top-24 space-y-1">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">On this page</h3>
                        <a href="#overview" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">Overview</a>
                        <a href="#architecture" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">Architecture</a>
                        <a href="#mcp-tools" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">MCP Tools</a>
                        <a href="#a2a-protocol" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">A2A Protocol</a>
                        <a href="#x402-payments" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">x402 Agent Payments</a>
                        <a href="#spending-controls" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">Spending Controls</a>
                        <a href="#transaction-query" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">Transaction Query</a>
                    </div>
                </nav>

                <!-- Main Content -->
                <div class="lg:col-span-3 prose prose-lg max-w-none">

                    <!-- Overview -->
                    <section id="overview" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Overview</h2>
                        <p>
                            The {{ config('brand.name', 'Zelta') }} AI Framework enables autonomous agents to interact with the full banking platform. It provides Model Context Protocol (MCP) tools for LLM integration, Google A2A protocol for agent-to-agent communication, and x402-based micropayments for pay-per-request API access.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 not-prose mt-6">
                            <h4 class="font-semibold text-blue-900 mb-2">Key Capabilities</h4>
                            <ul class="space-y-2 text-blue-800">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span><strong>MCP Tools:</strong> TransactionQueryTool, X402PaymentTool for direct LLM integration</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span><strong>A2A Protocol:</strong> Google Agent-to-Agent for multi-agent collaboration</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span><strong>x402 Payments:</strong> Autonomous USDC micropayments with spending limits</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span><strong>Full API Surface:</strong> 1,250+ REST endpoints and 35 GraphQL domains</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- Architecture -->
                    <section id="architecture" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Architecture</h2>
                        <p>
                            The AI Framework spans three domain modules: <code>AgentProtocol</code> for A2A communication, <code>X402</code> for payment gating, and <code>AI</code> for MCP tool definitions.
                        </p>
                        <div class="bg-gray-900 rounded-lg p-6 font-mono text-sm text-gray-300 not-prose mt-4">
<pre>app/
├── Domain/
│   ├── AgentProtocol/     # Google A2A protocol implementation
│   │   ├── Services/      # AgentPaymentIntegrationService
│   │   └── Models/        # AgentCard, AgentTask
│   ├── X402/              # HTTP 402 payment gate
│   │   ├── Services/      # Settlement, Verification, Pricing
│   │   └── Middleware/     # X402PaymentGateMiddleware
│   └── AI/                # MCP tool definitions
│       └── Tools/         # TransactionQueryTool, X402PaymentTool
└── Infrastructure/
    └── AI/                # Provider adapters</pre>
                        </div>
                    </section>

                    <!-- MCP Tools -->
                    <section id="mcp-tools" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">MCP Tools</h2>
                        <p>
                            {{ config('brand.name', 'Zelta') }} implements Model Context Protocol tools that LLMs like Claude can use to interact with the banking platform. Tools are registered as MCP-compatible endpoints.
                        </p>
                        <h3>TransactionQueryTool</h3>
                        <p>
                            Translates natural language questions about transactions, balances, and portfolios into structured database queries. Supports filtering by date range, amount, asset type, and counterparty.
                        </p>
                        <h3>X402PaymentTool</h3>
                        <p>
                            Enables agents to make USDC payments for API access using the x402 protocol. Handles payment negotiation, execution, and verification in a single tool call.
                        </p>
                    </section>

                    <!-- A2A Protocol -->
                    <section id="a2a-protocol" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">A2A Protocol</h2>
                        <p>
                            The Agent-to-Agent (A2A) protocol, based on Google's specification, enables agents to discover each other's capabilities, negotiate tasks, and collaborate on multi-step financial workflows.
                        </p>
                        <h3>Agent Cards</h3>
                        <p>
                            Each agent publishes a capability card describing its skills, input/output schemas, and pricing. Other agents can discover these cards and delegate appropriate tasks.
                        </p>
                        <h3>Task Lifecycle</h3>
                        <p>
                            Tasks follow a lifecycle: <code>created → assigned → in_progress → completed</code>. Agents report progress and intermediate results through the A2A message channel.
                        </p>
                    </section>

                    <!-- x402 Payments -->
                    <section id="x402-payments" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">x402 Agent Payments</h2>
                        <p>
                            The x402 protocol enables HTTP-native micropayments. When an agent hits a payment-gated endpoint, it receives a <code>402 Payment Required</code> response with pricing details. The agent then submits a USDC payment and retries the request.
                        </p>
                        <div class="bg-gray-900 rounded-lg p-6 font-mono text-sm text-gray-300 not-prose mt-4">
<pre><span class="text-gray-500"># 1. Agent calls a gated endpoint</span>
<span class="text-green-400">GET</span> /api/v2/premium/data
<span class="text-yellow-400">→ 402 Payment Required</span>
<span class="text-gray-500"># X-Payment-Required: {"amount": "0.01", "currency": "USDC", "network": "base"}</span>

<span class="text-gray-500"># 2. Agent submits payment</span>
<span class="text-green-400">POST</span> /api/v2/x402/pay
<span class="text-gray-500"># {"endpoint": "/api/v2/premium/data", "tx_hash": "0x..."}</span>

<span class="text-gray-500"># 3. Agent retries with payment proof</span>
<span class="text-green-400">GET</span> /api/v2/premium/data
<span class="text-gray-500"># X-Payment-Proof: 0x...</span>
<span class="text-green-400">→ 200 OK</span></pre>
                        </div>
                    </section>

                    <!-- Spending Controls -->
                    <section id="spending-controls" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Spending Controls</h2>
                        <p>
                            Safety rails for autonomous agents. Configure per-agent daily and monthly spending limits, per-request caps, and approval thresholds. The <code>X402PricingService</code> enforces limits before any payment is authorized.
                        </p>
                        <ul>
                            <li>Per-agent daily and monthly spending caps</li>
                            <li>Per-request maximum amount</li>
                            <li>Approval workflows for high-value transactions</li>
                            <li>Real-time spending dashboards</li>
                        </ul>
                    </section>

                    <!-- Transaction Query -->
                    <section id="transaction-query" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Transaction Query</h2>
                        <p>
                            The <code>TransactionQueryTool</code> enables natural language interaction with transaction data. Agents can ask questions and receive structured results.
                        </p>
                        <div class="bg-gray-50 border rounded-lg p-6 not-prose mt-4">
                            <h4 class="font-semibold text-slate-900 mb-3">Example Queries</h4>
                            <ul class="space-y-2 text-slate-600 text-sm">
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"Show my top 5 transactions this week"</code></li>
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"What's my total spending on DeFi protocols?"</code></li>
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"List all cross-chain bridge transactions over $100"</code></li>
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"Calculate my portfolio allocation by asset class"</code></li>
                            </ul>
                        </div>
                    </section>

                    <!-- CTA -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-8 not-prose text-center">
                        <h3 class="text-2xl font-bold text-indigo-900 mb-3">Start Building</h3>
                        <p class="text-indigo-700 mb-6">Ready to integrate AI agents with {{ config('brand.name', 'Zelta') }}? Start with the sandbox.</p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="{{ route('ai-framework.demo') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                                Try the Demo
                            </a>
                            <a href="{{ route('developers.show', 'api-docs') }}" class="border-2 border-indigo-600 text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-indigo-100 transition">
                                API Reference
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
