@extends('layouts.public')

@section('title', 'AI Framework Demo - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'AI Framework Demo - Interactive AI Agent Playground',
        'description' => 'Try the ' . config('brand.name', 'Zelta') . ' AI Agent Framework in action. Explore MCP tools, transaction queries, x402 agent payments, and autonomous financial workflows.',
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
                Sandbox Mode &mdash; Safe to Explore
            </div>
            <h1 class="text-5xl font-bold mb-6">AI Framework Demo</h1>
            <p class="text-xl text-slate-400 max-w-3xl mx-auto mb-8">
                Explore how AI agents interact with the {{ config('brand.name', 'Zelta') }} banking platform. Run natural language queries, trigger MCP tools, and see autonomous agent workflows in action.
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
                    Each scenario demonstrates a real capability of the AI Agent Framework
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
                        Ask questions like "Show my largest transactions this month" or "What's my portfolio allocation?" The AI translates to structured queries.
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
                    <h3 class="text-xl font-semibold mb-3">MCP Tool Integration</h3>
                    <p class="text-slate-500 mb-4">
                        See how Claude and other LLMs use Model Context Protocol tools to interact with banking APIs, check balances, and execute transfers.
                    </p>
                    <code class="text-sm text-purple-600 bg-purple-50 px-3 py-1 rounded">X402PaymentTool</code>
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
                        Watch autonomous agents pay for API calls using the x402 protocol. USDC-based pay-per-request with spending limits and settlement tracking.
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
                    <h3 class="text-xl font-semibold mb-3">Agent-to-Agent (A2A)</h3>
                    <p class="text-slate-500 mb-4">
                        Google A2A protocol for multi-agent collaboration. See agents discover capabilities, negotiate tasks, and execute financial workflows together.
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
                        Configure per-agent spending caps, daily/monthly limits, and approval workflows. Safety rails for autonomous financial agents.
                    </p>
                    <code class="text-sm text-red-600 bg-red-50 px-3 py-1 rounded">X402PricingService</code>
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
                        Agents have access to the complete {{ config('brand.name', 'Zelta') }} API surface: 1,250+ REST endpoints and 35 GraphQL domain schemas with real-time subscriptions.
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Ready to Build with AI Agents?</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Register for the sandbox to start experimenting, or read the documentation to understand the architecture.
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
