@extends('layouts.public')

@section('title', 'AI Framework Documentation - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Framework Documentation - ' . config('brand.name', 'Zelta'),
        'description' => 'Technical documentation for the ' . config('brand.name', 'Zelta') . ' AI agent framework. It explains the Model Context Protocol tools, the Agent2Agent protocol, x402 agent payments, spending limits and transaction questions in plain language.',
        'keywords' => 'AI framework documentation, Model Context Protocol, Agent2Agent protocol, agent payments, x402, spending limits, large language models'
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
                This page explains how the {{ config('brand.name', 'Zelta') }} AI agent framework is built. It also shows how agents connect to it and pay for API calls.
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
                        <a href="#a2a-protocol" class="block text-slate-500 hover:text-indigo-600 py-1 text-sm">Agent2Agent Protocol</a>
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
                            The {{ config('brand.name', 'Zelta') }} AI framework lets autonomous agents work with the banking platform. Large language models reach the platform through Model Context Protocol (MCP) tools. Agents talk to each other through Google's Agent2Agent protocol. They pay for single API requests with x402 micropayments.
                        </p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 not-prose mt-6">
                            <h4 class="font-semibold text-blue-900 mb-2">Key Capabilities</h4>
                            <ul class="space-y-2 text-blue-800">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>The tool registry holds 24 tools, including TransactionQueryTool and X402PaymentTool.</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>The Agent2Agent protocol lets several agents share tasks.</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Agents pay in USDC with x402, within their spending limits.</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Agents can use more than 1,000 REST API routes and a GraphQL API across 43 domains.</span>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- Architecture -->
                    <section id="architecture" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Architecture</h2>
                        <p>
                            The AI framework spans three domain modules. <code>AgentProtocol</code> handles communication between agents. <code>X402</code> handles paid API access. <code>AI</code> holds the agents, the workflows and the tool definitions.
                        </p>
                        <div class="bg-gray-900 rounded-lg p-6 font-mono text-sm text-gray-300 not-prose mt-4">
<pre>app/
├── Domain/
│   ├── AgentProtocol/     # Google A2A protocol implementation
│   │   ├── Services/      # AgentPaymentIntegrationService
│   │   └── Models/        # Agent, A2ATask, Escrow
│   ├── X402/              # HTTP 402 payment gate
│   │   └── Services/      # Settlement, Verification, Pricing
│   └── AI/                # Agents, workflows, MCP tools
│       └── MCP/Tools/     # TransactionQueryTool, X402PaymentTool
├── Http/Middleware/       # X402PaymentGateMiddleware
└── Infrastructure/
    └── AI/                # OpenAI, Claude and Pinecone adapters</pre>
                        </div>
                    </section>

                    <!-- MCP Tools -->
                    <section id="mcp-tools" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">MCP Tools</h2>
                        <p>
                            {{ config('brand.name', 'Zelta') }} implements Model Context Protocol tools. Large language models such as Claude use them to work with the banking platform. Each tool is registered in the tool registry with a name and an input schema.
                        </p>
                        <h3>TransactionQueryTool</h3>
                        <p>
                            This tool turns a plain-language question about transactions into a structured database query. It can filter by account, date range, amount, category and asset.
                        </p>
                        <h3>X402PaymentTool</h3>
                        <p>
                            This tool lets an agent pay for API access in USDC with the x402 protocol. One tool call reads the price, signs the payment and checks the result.
                        </p>
                    </section>

                    <!-- A2A Protocol -->
                    <section id="a2a-protocol" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Agent2Agent Protocol</h2>
                        <p>
                            The Agent2Agent protocol follows Google's specification. Agents use it to find each other, hand over tasks and work together on financial workflows with several steps.
                        </p>
                        <h3>Agent Cards</h3>
                        <p>
                            Each agent publishes a card that describes its skills and capabilities. Other agents read these cards to decide which agent should get a task.
                        </p>
                        <h3>Task Lifecycle</h3>
                        <p>
                            A task starts as <code>submitted</code> and moves to <code>working</code>. It can wait in <code>input-required</code>, and it ends as <code>completed</code>, <code>failed</code> or <code>canceled</code>. Agents report progress through protocol messages.
                        </p>
                    </section>

                    <!-- x402 Payments -->
                    <section id="x402-payments" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">x402 Agent Payments</h2>
                        <p>
                            The x402 protocol adds payments to normal HTTP requests. When an agent calls a paid endpoint, it gets a <code>402 Payment Required</code> response with the price. The agent signs a USDC payment and sends the request again with the signature.
                        </p>
                        <div class="bg-gray-900 rounded-lg p-6 font-mono text-sm text-gray-300 not-prose mt-4">
<pre><span class="text-gray-500"># 1. Agent calls a gated endpoint</span>
<span class="text-green-400">GET</span> /api/v2/premium/data
<span class="text-yellow-400">→ 402 Payment Required</span>
<span class="text-gray-500"># PAYMENT-REQUIRED: base64 JSON with amount, asset and network</span>
<span class="text-gray-500"># 2. Agent signs the payment and retries</span>
<span class="text-green-400">GET</span> /api/v2/premium/data
<span class="text-gray-500"># PAYMENT-SIGNATURE: base64 signed payment payload</span>
<span class="text-gray-500"># 3. Server verifies and settles the payment</span>
<span class="text-green-400">→ 200 OK</span>
<span class="text-gray-500"># PAYMENT-RESPONSE: base64 settlement result</span></pre>
                        </div>
                    </section>

                    <!-- Spending Controls -->
                    <section id="spending-controls" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Spending Controls</h2>
                        <p>
                            Spending limits keep an autonomous agent from paying more than its owner allows. Each agent can have a daily limit and a limit per payment. The platform checks both limits before it signs a payment.
                        </p>
                        <ul>
                            <li>Each agent has its own daily limit.</li>
                            <li>A limit per payment caps a single request.</li>
                            <li>The <code>/spending-limits</code> API routes read and change the limits.</li>
                        </ul>
                    </section>

                    <!-- Transaction Query -->
                    <section id="transaction-query" class="doc-section mb-16">
                        <h2 class="font-display text-3xl font-bold text-slate-900 mb-6">Transaction Query</h2>
                        <p>
                            The <code>TransactionQueryTool</code> answers questions about transaction data in plain language. An agent asks a question and gets a structured result.
                        </p>
                        <div class="bg-gray-50 border rounded-lg p-6 not-prose mt-4">
                            <h4 class="font-semibold text-slate-900 mb-3">Example Queries</h4>
                            <ul class="space-y-2 text-slate-600 text-sm">
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"Show my top 5 transactions this week"</code></li>
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"Show transactions over $100 last week"</code></li>
                                <li><code class="bg-gray-200 px-2 py-1 rounded">"How much did I spend on groceries last month?"</code></li>
                            </ul>
                        </div>
                    </section>

                    <!-- CTA -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-8 not-prose text-center">
                        <h3 class="text-2xl font-bold text-indigo-900 mb-3">Start Building</h3>
                        <p class="text-indigo-700 mb-6">You can connect your first AI agent to {{ config('brand.name', 'Zelta') }} in the sandbox. It uses test data only.</p>
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
