@extends('layouts.public')

@section('title', 'AI Framework Demo - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Framework Demo - ' . config('brand.name', 'Zelta'),
        'description' => 'This page shows what you can try with the ' . config('brand.name', 'Zelta') . ' AI agent framework in the sandbox. It covers plain-language transaction questions, Model Context Protocol tools, x402 agent payments and spending limits.',
        'keywords' => 'AI demo, MCP tools, AI agent payments, transaction query, x402, financial AI, agent framework demo',
    ])
@endsection

@push('styles')
<style>
    .demo-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .demo-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                Sandbox mode with test data
            </div>
            <h1 class="text-5xl font-bold mb-6">AI Framework Demo</h1>
            <p class="text-xl text-slate-400 max-w-3xl mx-auto mb-8">
                This page shows how AI agents work with the {{ config('brand.name', 'Zelta') }} banking platform. In the sandbox you can ask questions in plain language, call Model Context Protocol tools and run agent workflows on test data.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                    Try the Demo
                </a>
                <a href="{{ route('ai-framework.docs') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    Read the Docs
                </a>
            </div>
        </div>
    </section>

    <!-- Demo Scenarios -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-4xl font-bold text-slate-900 mb-4">What You Can Try</h2>
                <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                    Each scenario uses a part of the AI agent framework that exists in the code.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Transaction Query -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Natural Language Queries</h3>
                    <p class="text-slate-500 mb-4">
                        You can ask a question such as "Show my largest transactions this month". The agent turns it into a structured query on your transactions.
                    </p>
                    <code class="text-sm text-blue-600 bg-blue-50 px-3 py-1 rounded">TransactionQueryTool</code>
                </div>

                <!-- MCP Tools -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Model Context Protocol Tools</h3>
                    <p class="text-slate-500 mb-4">
                        Claude and other large language models can use Model Context Protocol tools. With them, a model can check balances and send transfers through the banking API.
                    </p>
                    <code class="text-sm text-purple-600 bg-purple-50 px-3 py-1 rounded">ToolRegistry</code>
                </div>

                <!-- x402 Agent Payments -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-emerald-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Agent Micropayments</h3>
                    <p class="text-slate-500 mb-4">
                        An agent can pay for an API call with the x402 protocol. Each request is paid in USDC, and the platform checks spending limits and tracks settlement.
                    </p>
                    <code class="text-sm text-emerald-600 bg-emerald-50 px-3 py-1 rounded">AgentPaymentIntegrationService</code>
                </div>

                <!-- A2A Protocol -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Agent2Agent Protocol</h3>
                    <p class="text-slate-500 mb-4">
                        The platform implements Google's Agent2Agent protocol. Agents use it to find each other, hand tasks to each other and complete payments with escrow.
                    </p>
                    <code class="text-sm text-orange-600 bg-orange-50 px-3 py-1 rounded">AgentProtocol Domain</code>
                </div>

                <!-- Spending Controls -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Spending Limits</h3>
                    <p class="text-slate-500 mb-4">
                        Each agent can have a daily limit and a limit per payment. A payment above a limit is refused.
                    </p>
                    <code class="text-sm text-red-600 bg-red-50 px-3 py-1 rounded">X402SpendingLimit</code>
                </div>

                <!-- GraphQL + REST -->
                <div class="demo-card bg-white border border-gray-200 rounded-xl p-8">
                    <div class="w-14 h-14 bg-cyan-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Full API Access</h3>
                    <p class="text-slate-500 mb-4">
                        Agents can use the whole {{ config('brand.name', 'Zelta') }} API. It has more than 1,000 REST API routes and a GraphQL API across 43 domains with real-time subscriptions.
                    </p>
                    <code class="text-sm text-cyan-600 bg-cyan-50 px-3 py-1 rounded">REST + GraphQL</code>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Build with AI Agents</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                You can register for the sandbox and try the agents on test data. The documentation explains how the framework is built.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Start Building
                </a>
                <a href="{{ route('ai-framework.docs') }}" class="btn-outline px-8 py-4 text-lg">
                    Documentation
                </a>
            </div>
        </div>
    </section>

@endsection
