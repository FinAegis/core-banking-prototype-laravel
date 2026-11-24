@extends('layouts.public')

@section('title', 'AI Framework and Multi-Agent Intelligence | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Framework and Multi-Agent Intelligence for Banking',
        'description' => 'The AI framework has six specialised agents and ' . count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) . ' Model Context Protocol tools that use OAuth scopes. It also detects unusual transactions, answers questions in plain language and runs durable workflows.',
        'keywords' => 'AI framework, Model Context Protocol tools, multi-agent, anomaly detection, natural language banking, agent orchestration, durable workflows, AI banking, ' . config('brand.name', 'Zelta'),
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'AI Framework', 'url' => url('/features/ai-framework')]
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                @include('partials.breadcrumb', ['items' => [
                    ['name' => 'Features', 'url' => url('/features')],
                    ['name' => 'AI Framework', 'url' => url('/features/ai-framework')]
                ]])
                <div class="inline-flex items-center px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-sm text-slate-300 mb-6">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full mr-2"></span>
                    v7.18.1 &middot; Multi-Agent Intelligence
                </div>
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    AI Framework
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    The artificial intelligence (AI) framework adds software agents to the platform. Six specialised agents share the work, and an orchestrator combines their answers. External agents connect through {{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} Model Context Protocol (MCP) tools. The framework also detects unusual transactions and runs long financial tasks as durable workflows.
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ route('features.show', 'agent-protocol') }}" class="btn-primary px-8 py-4 text-lg">Agent Protocol</a>
                    <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">API Reference</a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Three Pillars -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Natural Language Queries</h3>
                    <p class="text-slate-500">You can ask about your transactions in plain English. The framework turns the question into a database query. It returns the answer as a table or a chart.</p>
                </div>

                <div class="card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Multi-Agent Orchestration</h3>
                    <p class="text-slate-500">The framework has five specialised agents for general, compliance, financial, trading and transfer questions. A sixth agent, the orchestrator, sends each request to the right agent and combines the results.</p>
                </div>

                <div class="card-feature !p-8 text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Anomaly Detection</h3>
                    <p class="text-slate-500">Several machine learning models look for unusual transactions. They check statistics, behaviour, transaction speed and location. A combined score decides when an alert goes to a person.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Agent Architecture -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Specialized Agent Architecture</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                Each agent covers one area of banking. The orchestrator sends a request to the right agent. When several agents answer, it reconciles their results into one answer.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card-feature !p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900">General Agent</h3>
                    </div>
                    <p class="text-sm text-slate-500">The general agent answers plain-language questions and common questions. It also gives account summaries.</p>
                </div>

                <div class="card-feature !p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900">Compliance Agent</h3>
                    </div>
                    <p class="text-sm text-slate-500">The compliance agent runs anti-money laundering screening, identity checks and sanctions checks. It sends high-risk findings to a person.</p>
                </div>

                <div class="card-feature !p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900">Financial Agent</h3>
                    </div>
                    <p class="text-sm text-slate-500">The financial agent analyses spending and tracks budgets. It also scores credit and checks whether a loan is affordable.</p>
                </div>

                <div class="card-feature !p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900">Trading Agent</h3>
                    </div>
                    <p class="text-sm text-slate-500">The trading agent analyses the market with common technical indicators, such as the relative strength index. It suggests strategies and finds price patterns within risk limits that you set.</p>
                </div>

                <div class="card-feature !p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-900">Transfer Agent</h3>
                    </div>
                    <p class="text-sm text-slate-500">The transfer agent makes payments and cross-border transfers. It looks for the lowest fee and keeps every payment within the spending limit.</p>
                </div>

                <div class="card-feature !p-6 border-2 border-indigo-200">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <h3 class="font-bold text-indigo-900">Orchestrator</h3>
                    </div>
                    <p class="text-sm text-slate-500">The orchestrator sends each request to the right agent. When several agents take part in a decision, it combines their views into one result.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- MCP Tool Registry -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">{{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} MCP Tools</h2>
            <p class="text-lg text-slate-500 text-center max-w-3xl mx-auto mb-12">
                The public MCP server offers {{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} tools to external AI agents. Each tool needs its own OAuth scope. The wallet tools <code>wallet.addresses</code> and <code>wallet.activity</code> only read data. Every tool call is rate-limited and written to the audit log. The agents inside the platform use the larger internal set of tools listed below.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Account -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-blue-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-blue-700">4</span>
                        Account
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Create Account</li>
                        <li>Balance Inquiry</li>
                        <li>Deposit</li>
                        <li>Withdraw</li>
                    </ul>
                </div>

                <!-- Payment -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-green-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-green-700">2</span>
                        Payment
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Transfer</li>
                        <li>Payment Status</li>
                    </ul>
                </div>

                <!-- Exchange -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-purple-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-purple-700">3</span>
                        Exchange
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Quote</li>
                        <li>Trade</li>
                        <li>Pool Lookup</li>
                    </ul>
                </div>

                <!-- Compliance -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-amber-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-amber-700">2</span>
                        Compliance
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Anti-Money Laundering Screening</li>
                        <li>Identity Check</li>
                    </ul>
                </div>

                <!-- Transaction -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-indigo-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-indigo-700">2</span>
                        Transaction
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Plain-Language Query</li>
                        <li>Spending Analysis</li>
                    </ul>
                </div>

                <!-- Agent Protocol -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-cyan-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-cyan-700">5</span>
                        Agent Protocol
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Agent Payment</li>
                        <li>Escrow</li>
                        <li>Reputation</li>
                        <li>Mandate</li>
                        <li>Digital Credential Issuance</li>
                    </ul>
                </div>

                <!-- Payment Protocols -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-emerald-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-emerald-700">3</span>
                        Payment Rails
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>x402 (USDC)</li>
                        <li>Machine Payments Discovery</li>
                        <li>Machine Payment</li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-pink-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-pink-700">3</span>
                        Services
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>SMS Send</li>
                        <li>Command-Line Card List</li>
                        <li>Command-Line Card Payment</li>
                    </ul>
                </div>

                <!-- Ramp -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-orange-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-orange-700">2</span>
                        Ramp
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Start Session</li>
                        <li>Session Status</li>
                    </ul>
                </div>

                <!-- Wallet -->
                <div class="card-feature !p-5">
                    <h4 class="font-bold text-slate-900 mb-3 flex items-center">
                        <span class="w-8 h-8 bg-teal-100 rounded flex items-center justify-center mr-2 text-xs font-bold text-teal-700">2</span>
                        Wallet
                    </h4>
                    <ul class="text-sm text-slate-500 space-y-1.5">
                        <li>Addresses</li>
                        <li>Activity Feed</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow Engine -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-6">Durable Workflow Engine</h2>
                    <p class="text-lg text-slate-500 mb-6">
                        Long financial operations run as durable workflows on the Laravel Workflow package. A failed step is retried, and a failed operation is undone step by step. Some steps wait for a person to approve them.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-indigo-600 font-bold text-sm">1</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Risk Assessment Saga</h4>
                                <p class="text-slate-500 text-sm">This workflow runs several checks in parallel. It scores credit, calculates value at risk, works out the debt ratio and checks the device and location.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-purple-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-purple-600 font-bold text-sm">2</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Trading Execution Saga</h4>
                                <p class="text-slate-500 text-sm">This workflow analyses the market and calculates technical indicators. It then suggests a strategy and finds price patterns within the risk limits you set.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-green-600 font-bold text-sm">3</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Human Approval Gate</h4>
                                <p class="text-slate-500 text-sm">A transaction above a set amount waits for a person to review it. The default amount is $10,000. A high-value transaction passes without a person only when the confidence score is at least 0.95.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-10 h-10 bg-amber-50 rounded-full flex items-center justify-center mr-4">
                                <span class="text-amber-600 font-bold text-sm">4</span>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-1">Compliance Workflow</h4>
                                <p class="text-slate-500 text-sm">This workflow runs anti-money laundering screening and checks identity documents and proof of address. It then prepares the regulatory report, all in one run.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-feature !p-8 rounded-2xl">
                    <h3 class="text-2xl font-bold mb-6 text-slate-900">Detection Models</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold text-gray-800">Statistical Analysis</span>
                                <p class="text-sm text-gray-500">Deviation from baseline patterns</p>
                            </div>
                            <span class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">Active</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold text-gray-800">Behavioral Profiling</span>
                                <p class="text-sm text-gray-500">User habit and routine modeling</p>
                            </div>
                            <span class="text-sm font-bold text-purple-600 bg-purple-50 px-3 py-1 rounded-full">Active</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold text-gray-800">Velocity Checks</span>
                                <p class="text-sm text-gray-500">Transaction frequency monitoring</p>
                            </div>
                            <span class="text-sm font-bold text-green-600 bg-green-50 px-3 py-1 rounded-full">Active</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold text-gray-800">Geo-Based Detection</span>
                                <p class="text-sm text-gray-500">Impossible travel identification</p>
                            </div>
                            <span class="text-sm font-bold text-pink-600 bg-pink-50 px-3 py-1 rounded-full">Active</span>
                        </div>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-semibold text-gray-800">Ensemble Scoring</span>
                                <p class="text-sm text-gray-500">Weighted confidence aggregation</p>
                            </div>
                            <span class="text-sm font-bold text-cyan-600 bg-cyan-50 px-3 py-1 rounded-full">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Capabilities -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Technical Capabilities</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Language Model Integration</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Two language model providers, OpenAI and Anthropic</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Automatic failover between providers</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Plain-language questions turned into database queries</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Intent classification with confidence scoring</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Usage and cost tracking, with a default limit of $10 per user per day</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Query safety validation and injection prevention</span>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Conversation Intelligence</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Conversations keep their context for 24 hours</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Multi-turn reasoning with progressive query refinement</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Automatic routing to specialized agents via intent classification</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Versioned prompt templates with category management</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Response caching for frequently asked questions</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Event-sourced interaction auditing</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Related Capabilities</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <a href="{{ route('features.show', 'agent-protocol') }}" class="card-feature !p-6 hover:border-cyan-300 transition-colors group">
                    <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-cyan-700">Agent Protocol</h3>
                    <p class="text-sm text-slate-500">This module lets AI agents trade with each other. It covers agent identity, messages between agents, escrow, reputation and payment mandates.</p>
                </a>
                <a href="{{ route('features.show', 'machine-payments') }}" class="card-feature !p-6 hover:border-orange-300 transition-colors group">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-orange-700">Machine Payments</h3>
                    <p class="text-sm text-slate-500">AI agents can pay for a service when it answers with HTTP status 402. The module supports Stripe, USDC, Lightning and x402 as payment rails. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                </a>
                <a href="{{ route('features.show', 'x402-protocol') }}" class="card-feature !p-6 hover:border-emerald-300 transition-colors group">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2 group-hover:text-emerald-700">x402 Protocol</h3>
                    <p class="text-sm text-slate-500">x402 lets a client pay small amounts of USDC inside an HTTP request. A facilitator checks each payment before the service answers.</p>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Try the AI Framework</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                The sandbox lets you ask questions about test transactions and watch the agents work. All data in the sandbox is test data.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Try AI Banking
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    AI API Reference
                </a>
            </div>
        </div>
    </section>

@endsection
