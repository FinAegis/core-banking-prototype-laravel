@extends('layouts.public')

@section('title', 'SDKs and Integration Guide - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'SDKs and Integration Guide - ' . config('brand.name', 'Zelta'),
        'description' => 'You can connect your application to ' . config('brand.name', 'Zelta') . ' through the REST API or the official SDKs for JavaScript, Python and PHP. This guide shows both, plus the AI and partner tools.',
        'keywords' => config('brand.name', 'Zelta') . ', integration, API, SDK, webhooks, banking infrastructure, fintech, development',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .dev-gradient {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    }
    .code-block {
        font-family: 'Fira Code', monospace;
        font-size: 0.875rem;
        line-height: 1.5;
                overflow-x: auto;
                white-space: pre;
            }
            .code-container {
                position: relative;
                background: #1e293b;
                border-radius: 0.75rem;
                overflow: hidden;
            }
            .code-header {
                background: #0f172a;
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
                font-family: 'Figtree', sans-serif;
                color: #94a3b8;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .copy-button {
                background: #334155;
                padding: 0.375rem 0.75rem;
                border-radius: 0.375rem;
                color: #e2e8f0;
                font-size: 0.75rem;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
                cursor: pointer;
                border: none;
            }
            .copy-button:hover {
                background: #475569;
                color: white;
            }
            .copy-button.copied {
                background: #10b981;
                color: white;
            }
            .terminal-dot {
                width: 0.75rem;
                height: 0.75rem;
                border-radius: 50%;
                display: inline-block;
            }
            .sdk-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            .sdk-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-color: #e5e7eb;
            }
            .floating-blob {
                position: absolute;
                filter: blur(80px);
                opacity: 0.3;
                animation: float 20s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% {
                    transform: translateY(0) rotate(0deg);
                }
                33% {
                    transform: translateY(-30px) rotate(120deg);
                }
                66% {
                    transform: translateY(20px) rotate(240deg);
                }
            }
            @keyframes blob {
                0%, 100% {
                    transform: translate(0, 0) scale(1);
                }
                25% {
                    transform: translate(20px, -50px) scale(1.1);
                }
                50% {
                    transform: translate(-20px, 20px) scale(0.9);
                }
                75% {
                    transform: translate(50px, 10px) scale(1.05);
                }
            }
            .animate-blob {
                animation: blob 10s infinite;
            }
            .animation-delay-2000 {
                animation-delay: 2s;
            }
            .animation-delay-4000 {
                animation-delay: 4s;
            }
            .integration-card {
                transition: all 0.3s ease;
                border: 2px solid #e5e7eb;
                background: white;
                height: 100%;
            }
            .integration-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border-color: #ddd6fe;
            }
            .status-indicator {
                position: absolute;
                top: 1rem;
                right: 1rem;
                padding: 0.25rem 0.75rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
            }
            .status-available {
                background: #dcfce7;
                color: #166534;
            }
            .status-coming-soon {
                background: #fef3c7;
                color: #92400e;
            }
            .status-beta {
                background: #dbeafe;
                color: #1e40af;
            }
            .timeline-phase {
                position: relative;
                padding: 1rem;
                text-align: center;
            }
            .timeline-phase.active {
                background: #f3f4f6;
                border-radius: 0.5rem;
            }
            .timeline-phase.current {
                background: #fef3c7;
                border-radius: 0.5rem;
                box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.2);
            }
</style>
@endpush

@section('content')

        <!-- Hero Section -->
        <section class="dev-gradient text-white relative overflow-hidden">
            <!-- Animated Background -->
            <div class="absolute inset-0">
                <div class="floating-blob w-96 h-96 bg-purple-600 rounded-full top-0 left-1/4"></div>
                <div class="floating-blob w-72 h-72 bg-blue-600 rounded-full bottom-0 right-1/4 animation-delay-2000"></div>
                <div class="floating-blob w-80 h-80 bg-indigo-600 rounded-full top-1/2 right-1/3 animation-delay-4000"></div>
            </div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="flex flex-wrap items-center justify-center gap-3 mb-6">
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>REST API v2</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>Cross-Chain and Decentralised Finance</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>Partner SDK Generator</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>Reporting Modules</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>AI Transaction Query</span>
                        </span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6">
                        SDKs and Integration
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-3xl mx-auto">
                        You can connect your application through the REST API or through an official SDK. Pick the approach that suits your project.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#integration-methods" class="bg-white text-slate-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            View Integration Options
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                            Start with REST API
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- SDK Source Notice -->
        <div class="bg-amber-50 border-b border-amber-200 py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm text-amber-800"><strong>The FinAegis SDKs are published to public registries.</strong> You can install them from npm, Packagist or PyPI with the commands below. You can also build them from the source in the main repository.</p>
                </div>
            </div>
        </div>

        <!-- Integration Methods -->
        <section id="integration-methods" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Integration Approaches</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        Both approaches use the same API. The SDKs save you from writing the HTTP calls yourself.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8">
                    <!-- REST API Integration -->
                    <div class="integration-card rounded-2xl p-8">
                        <div class="status-indicator status-available">Available Now</div>
                        <div class="flex items-center mb-6">
                            <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-slate-900">REST API</h3>
                                <p class="text-slate-500">Direct HTTP and JSON calls</p>
                            </div>
                        </div>
                        
                        <p class="text-slate-600 mb-6">You can call the REST API from any programming language. An OpenAPI 3.0 specification describes every documented endpoint.</p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-600">Standard HTTP methods and status codes</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-600">API documentation for every endpoint</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-600">A public demo environment with test data</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-600">Postman collection available</span>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('developers.show', 'api-docs') }}" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition inline-flex items-center">
                                View API Docs
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>
                            <a href="{{ route('developers.show', 'postman') }}" class="text-green-600 hover:text-green-800 font-semibold inline-flex items-center px-4 py-3">
                                Postman Collection
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Native SDKs -->
                    <div class="integration-card rounded-2xl p-8">
                        <div class="status-indicator status-available">Available</div>
                        <div class="flex items-center mb-6">
                            <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-slate-900">Native SDKs</h3>
                                <p class="text-slate-500">Language-specific libraries</p>
                            </div>
                        </div>
                        
                        <p class="text-slate-600 mb-6">Official SDKs exist for JavaScript and TypeScript, Python and PHP. They cover accounts, transactions, transfers, assets, exchange rates, baskets, the GCU demo and webhooks.</p>

                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-700">JavaScript/TypeScript</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-700">Python</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-700">PHP</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-500">Go (soon)</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-500">Ruby (soon)</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-slate-500">Java (soon)</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="bg-gray-900 rounded-lg px-4 py-2 font-mono text-green-400 text-sm">
                                <code>npm install @finaegis/sdk</code>
                            </div>
                            <div class="bg-gray-900 rounded-lg px-4 py-2 font-mono text-green-400 text-sm">
                                <code>pip install finaegis</code>
                            </div>
                            <div class="bg-gray-900 rounded-lg px-4 py-2 font-mono text-green-400 text-sm">
                                <code>composer require finaegis/php-sdk</code>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mt-3">Each SDK installs with one command from its public registry.</p>
                    </div>


                </div>
            </div>
        </section>

        <!-- AI Agent SDK Section -->
        <section class="py-20 bg-gradient-to-br from-purple-50 to-indigo-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl font-bold text-slate-900 mb-4">AI Agent Integration</h2>
                    <p class="text-xl text-slate-500">The AI endpoints are part of the REST API. The official SDKs do not wrap them yet, so you call them over HTTP.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- AI SDK Example -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-4">AI Agent API</h3>
                        <div class="code-container">
                            <div class="code-header">
                                <span>JavaScript Example</span>
                                <button class="copy-button" onclick="copyCode(this)">Copy</button>
                            </div>
                            <pre class="code-block p-4"><code class="language-javascript"><span style="color: #546e7a;">// Send a message to the AI agent</span>
<span style="color: #c792ea;">const</span> <span style="color: #82aaff;">response</span> = <span style="color: #c792ea;">await</span> <span style="color: #82aaff;">fetch</span>(<span style="color: #c3e88d;">'{{ config('app.url') }}/api/ai/chat'</span>, {
  <span style="color: #f07178;">method</span>: <span style="color: #c3e88d;">'POST'</span>,
  <span style="color: #f07178;">headers</span>: {
    <span style="color: #c3e88d;">'Authorization'</span>: <span style="color: #c3e88d;">'Bearer your-api-token'</span>,
    <span style="color: #c3e88d;">'Content-Type'</span>: <span style="color: #c3e88d;">'application/json'</span>
  },
  <span style="color: #f07178;">body</span>: <span style="color: #82aaff;">JSON</span>.<span style="color: #89ddff;">stringify</span>({ <span style="color: #f07178;">message</span>: <span style="color: #c3e88d;">'What is my account balance?'</span> })
});

console.<span style="color: #89ddff;">log</span>(<span style="color: #c792ea;">await</span> <span style="color: #82aaff;">response</span>.<span style="color: #89ddff;">json</span>());</code></pre>
                        </div>
                    </div>

                    <!-- MCP Tools Example -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-4">MCP Tool Integration</h3>
                        <div class="code-container">
                            <div class="code-header">
                                <span>Claude Desktop configuration</span>
                                <button class="copy-button" onclick="copyCode(this)">Copy</button>
                            </div>
                            <pre class="code-block p-4"><code class="language-json">{
  <span style="color: #f07178;">"mcpServers"</span>: {
    <span style="color: #f07178;">"zelta"</span>: {
      <span style="color: #f07178;">"url"</span>: <span style="color: #c3e88d;">"https://{{ config('mcp.host', 'mcp.zelta.app') }}/mcp"</span>
    }
  }
}</code></pre>
                        </div>
                        <p class="text-sm text-slate-500 mt-3">An AI client connects to the Model Context Protocol server and calls its tools. You do not need an SDK for this.</p>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <a href="{{ route('demo.ai-agent') }}" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
                        Try AI Agent Demo
                    </a>
                    <a href="/api/documentation#/AI%20Agent" class="inline-block ml-4 text-purple-600 hover:text-purple-700 font-semibold">
                        View AI API Docs →
                    </a>
                </div>
            </div>
        </section>

        <!-- MCP Server Section -->
        <section class="py-20 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl font-bold text-slate-900 mb-4">Model Context Protocol</h2>
                    <p class="text-xl text-slate-500">The Model Context Protocol is an open standard that lets AI models use outside tools. The public server offers banking tools to any client that follows it.</p>
                </div>

                <div class="bg-gray-50 rounded-xl p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">{{ count((array) config('mcp.tools')) }} Banking Tools</h3>
                            <p class="text-sm text-slate-500">The tools read balances and transactions, send payments, quote exchange rates and start on-ramp sessions.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">OAuth and Audit Log</h3>
                            <p class="text-sm text-slate-500">The user approves each connection with OAuth. The server records every tool call.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">Spending Limits</h3>
                            <p class="text-sm text-slate-500">Each token has a daily spending limit for the payment tools. The user sets it on the consent screen.</p>
                        </div>
                    </div>

                    <div class="code-container">
                        <div class="code-header">
                            <span>Tool names</span>
                            <button class="copy-button" onclick="copyCode(this)">Copy</button>
                        </div>
                        <pre class="code-block p-4"><code class="language-json">{
  <span style="color: #f07178;">"server"</span>: <span style="color: #c3e88d;">"https://{{ config('mcp.host', 'mcp.zelta.app') }}/mcp"</span>,
  <span style="color: #f07178;">"tools"</span>: [
@foreach(array_keys((array) config('mcp.tools')) as $toolName)
    <span style="color: #c3e88d;">"{{ $toolName }}"</span>{{ $loop->last ? '' : ',' }}
@endforeach
  ]
}</code></pre>
                    </div>
                    <p class="text-sm text-slate-500 mt-4">The <a href="{{ route('developers.show', 'mcp-tools') }}" class="text-purple-600 hover:text-purple-700 font-semibold">MCP tools reference</a> lists the scope and the type of each tool.</p>
                </div>
            </div>
        </section>

        <!-- SDK Coming Soon Section -->
        <section id="sdk-timeline" class="py-20 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gray-50 rounded-2xl p-8 text-center">
                    <h2 class="font-display text-2xl font-bold text-slate-900 mb-4">Official SDKs</h2>
                    <p class="text-slate-500 mb-6">
                        Official SDKs are available for JavaScript and TypeScript, Python and PHP. SDKs for more languages are planned. A separate PHP package, finaegis/payment-sdk, pays for x402 and Machine Payments Protocol requests automatically.
                    </p>
                    <div class="flex flex-wrap gap-3 justify-center text-sm">
                        <span class="bg-green-50 px-4 py-2 rounded-lg border border-green-200 text-green-700">JavaScript/TypeScript</span>
                        <span class="bg-green-50 px-4 py-2 rounded-lg border border-green-200 text-green-700">Python</span>
                        <span class="bg-green-50 px-4 py-2 rounded-lg border border-green-200 text-green-700">PHP</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200 text-slate-400">Java (planned)</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200 text-slate-400">Go (planned)</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200 text-slate-400">Ruby (planned)</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- BaaS SDK Generation via Partner API -->
        <section id="baas-sdk-generation" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <span class="inline-flex items-center px-4 py-2 bg-green-100 rounded-full text-sm font-medium text-green-700 mb-4">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                        Partner API
                    </span>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Partner SDK Generator</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        Partners on the Growth or Enterprise tier can ask the Partner API for a starter client. Today the generator builds it from templates, one language per request. The result has a README, a client class, an authentication helper and a package file.
                    </p>
                </div>

                <!-- Language Cards -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-12">
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-slate-900 mb-1">TypeScript</h4>
                        <p class="text-xs text-gray-500">typescript</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Starter client</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-slate-900 mb-1">Python</h4>
                        <p class="text-xs text-gray-500">python</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Starter client</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-slate-900 mb-1">Java</h4>
                        <p class="text-xs text-gray-500">java</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Starter client</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-cyan-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-slate-900 mb-1">Go</h4>
                        <p class="text-xs text-gray-500">go</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Starter client</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-slate-900 mb-1">PHP</h4>
                        <p class="text-xs text-gray-500">php</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Starter client</span>
                    </div>
                </div>

                <!-- SDK Generation Code Example -->
                <div class="max-w-4xl mx-auto">
                    <h3 class="text-xl font-semibold text-slate-900 mb-4">Ask the Partner API for a Starter Client</h3>
                    <p class="text-slate-500 mb-6">
                        Send one request for each language you need. The generator also supports C# and .NET. The Partner API signs you in with your partner client ID and client secret.
                    </p>

                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto mb-8">
<pre><span class="text-gray-500"># Ask for a starter client in one language</span>
curl -X POST {{ config('app.url') }}/api/partner/v1/sdk/generate \
  -H "X-Partner-Client-Id: YOUR_CLIENT_ID" \
  -H "X-Partner-Client-Secret: YOUR_CLIENT_SECRET" \
  -H "Content-Type: application/json" \
  -d '{"language": "typescript"}'

<span class="text-gray-500"># List the supported languages</span>
curl {{ config('app.url') }}/api/partner/v1/sdk/languages \
  -H "X-Partner-Client-Id: YOUR_CLIENT_ID" \
  -H "X-Partner-Client-Secret: YOUR_CLIENT_SECRET"</pre>
                    </div>

                    <!-- Install Commands Grid -->
                    <h4 class="text-lg font-semibold text-slate-900 mb-4">Quick Install</h4>
                    <p class="text-sm text-slate-500 mb-4">The official SDKs install from Packagist, npm and PyPI.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                        <div>
                            <p class="text-sm font-medium text-slate-600 mb-2">PHP (Composer)</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm overflow-x-auto">
                                <code>composer require finaegis/php-sdk</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600 mb-2">JavaScript (npm)</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>npm install @finaegis/sdk</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600 mb-2">Python (pip)</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>pip install finaegis</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SDK Integration Guide -->
        <section id="partner-sdk-guide" class="py-20 bg-gradient-to-br from-amber-50 to-orange-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">SDK Integration Guide</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        This guide starts with the official SDK. It then shows the REST calls for the modules the SDK does not cover yet, such as cross-chain transfers, decentralised finance, the Travel Rule check and AI queries.
                    </p>
                </div>

                <div class="max-w-5xl mx-auto space-y-8">
                    <!-- Step 1: Initialize the SDK -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-blue-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">1</span>
                                <h3 class="text-lg font-semibold text-white">Set Up the SDK with Your API Key</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-slate-500 mb-4">
                                Create an API token in your dashboard. Point the SDK at the server you want to use.
                            </p>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-slate-600 mb-2">TypeScript</p>
                                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre>// Install: npm install @finaegis/sdk
import { FinAegis } from '@finaegis/sdk';

const client = new FinAegis({
  apiKey: process.env.FINAEGIS_API_KEY,
  baseURL: '{{ config('app.url') }}/api/v2'
});

const accounts = await client.accounts.list();</pre>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-600 mb-2">Python</p>
                                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre># Install: pip install finaegis
import os
from finaegis import FinAegis

client = FinAegis(
    api_key=os.environ['FINAEGIS_API_KEY'],
    base_url='{{ config('app.url') }}/api/v2'
)

accounts = client.accounts.list()</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Cross-Chain and Decentralised Finance through REST -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-cyan-600 to-teal-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-cyan-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">2</span>
                                <h3 class="text-lg font-semibold text-white">Call the Cross-Chain and Decentralised Finance Endpoints</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-slate-500 mb-4">
                                The cross-chain endpoints quote and start bridge transfers through adapters for Wormhole, LayerZero and Axelar. The decentralised finance endpoints quote swaps through adapters for Uniswap, Aave, Curve and Lido. Integration adapters for third-party APIs; no partnership or endorsement implied.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre><span class="text-gray-500"># Quote a bridge transfer of USDC from Ethereum to Polygon</span>
curl -X POST {{ config('app.url') }}/api/v1/crosschain/bridge/quote \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"from_chain": "ethereum", "to_chain": "polygon", "token": "USDC", "amount": "5000.00"}'

<span class="text-gray-500"># Quote a swap on Polygon</span>
curl -X POST {{ config('app.url') }}/api/v1/defi/swap/quote \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"chain": "polygon", "from_token": "USDC", "to_token": "WETH", "amount": "5000.00"}'</pre>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: RegTech -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-emerald-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">3</span>
                                <h3 class="text-lg font-semibold text-white">Run a Travel Rule Check</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-slate-500 mb-4">
                                The reporting modules are modelled on European markets and crypto-asset rules and on the Travel Rule. The Travel Rule check tells you which sender and recipient details a transfer needs. These modules do not by themselves make a deployment compliant.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre>curl -X POST {{ config('app.url') }}/api/regtech/travel-rule/check \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2500,
    "currency": "EUR",
    "originator": {"name": "Alice Example", "account_number": "LT00 0000 0000 0000 0000"},
    "beneficiary": {"name": "Bob Example", "account_number": "DE00 0000 0000 0000 0000 00"}
  }'</pre>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: AI Queries -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-600 to-purple-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-violet-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">4</span>
                                <h3 class="text-lg font-semibold text-white">Ask About Transactions in Plain Language</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-slate-500 mb-4">
                                The AI query endpoint turns a question in plain language into transaction filters. It returns the matching transactions.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre>curl -X POST {{ config('app.url') }}/api/ai/query/transactions \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"query": "Payments over 500 euros last month"}'</pre>
                            </div>
                        </div>
                    </div>

                    <!-- SDK Module Reference -->
                    <div class="bg-white rounded-2xl shadow-lg p-8">
                        <h3 class="text-xl font-bold text-slate-900 mb-6">SDK Module Reference</h3>
                        <p class="text-slate-500 mb-6">
                            The official SDKs have these modules. The names are the same in JavaScript, Python and PHP, written in each language's style.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.accounts</h4>
                                <p class="text-sm text-slate-500">Accounts, balances, deposits, withdrawals, freeze and unfreeze</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.transactions</h4>
                                <p class="text-sm text-slate-500">Transaction lists and details</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.transfers</h4>
                                <p class="text-sm text-slate-500">Transfers between accounts</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.assets</h4>
                                <p class="text-sm text-slate-500">Supported assets</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.exchangeRates</h4>
                                <p class="text-sm text-slate-500">Exchange rates and conversions</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.baskets</h4>
                                <p class="text-sm text-slate-500">Currency baskets</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.gcu</h4>
                                <p class="text-sm text-slate-500">The GCU software demo</p>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-slate-900 mb-1">client.webhooks</h4>
                                <p class="text-sm text-slate-500">Webhook setup and deliveries</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Use Cases Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Built for Your Use Case</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        These are examples of products you can model with the {{ config('brand.name', 'Zelta') }} modules. Each one still needs your own licences, partners and compliance work before it serves real customers.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Digital Wallets -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Digital Wallets</h3>
                        <p class="text-slate-600 mb-6">
                            You can build a wallet with accounts in several currencies. It can move money between users and keep a full transaction history.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Multi-currency accounts
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Peer-to-peer transfers
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                QR code payments
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Marketplace Banking -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Marketplace Banking</h3>
                        <p class="text-slate-600 mb-6">
                            You can model payments between buyers, sellers and AI agents. The agent protocol module covers split payments, escrow and disputes.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Split payments
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Escrow
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Dispute handling
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Neobanking -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Neobanking</h3>
                        <p class="text-slate-600 mb-6">
                            You can prototype a digital bank with accounts, cards and loans. Card issuing runs through adapters for third-party card issuers.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Digital account opening
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Card-issuing integration adapter
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Spending analysis
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Corporate Banking -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Corporate Banking</h3>
                        <p class="text-slate-600 mb-6">
                            You can model business banking with bulk payments and team permissions.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Bulk payments
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Multi-user permissions
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Batch jobs
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Lending Platform -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-orange-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Lending Platform</h3>
                        <p class="text-slate-600 mb-6">
                            You can model loan products. The lending module covers applications, credit scoring, disbursement and repayments.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Credit scoring
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Disbursements
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Repayment tracking
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Investment Platform -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 hover:shadow-xl transition-shadow">
                        <div class="w-16 h-16 bg-red-100 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-3">Investment Platform</h3>
                        <p class="text-slate-600 mb-6">
                            You can model portfolio tracking. The treasury module manages portfolios and rebalances them to target weights.
                        </p>
                        <ul class="space-y-2 text-slate-500">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Net asset value calculation
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Portfolio tracking
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Automated rebalancing
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Quick Start Guide -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Getting Started</h2>
                    <p class="text-xl text-slate-500">These three steps connect your application to {{ config('brand.name', 'Zelta') }}.</p>
                </div>
                
                <div class="max-w-4xl mx-auto">
                    <!-- Step 1 -->
                    <div class="flex items-start mb-12">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold">
                                1
                            </div>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-slate-900 mb-3">Sign Up and Create an API Key</h3>
                            <p class="text-slate-600">
                                Create a free account and create an API key in your dashboard. The demo environment uses test data only.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="flex items-start mb-12">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold">
                                2
                            </div>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-slate-900 mb-3">Create Your First Account</h3>
                            <p class="text-slate-600">
                                Use the API to create an account. Each account can hold balances in several currencies.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="flex items-start mb-12">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-600 text-white rounded-full flex items-center justify-center text-xl font-bold">
                                3
                            </div>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="text-xl font-bold text-slate-900 mb-3">Process Transactions</h3>
                            <p class="text-slate-600">
                                Send deposits, withdrawals and transfers. Follow their status through webhooks or by asking the API.
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="{{ route('developers.show', 'examples') }}" class="bg-blue-600 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-blue-700 transition inline-flex items-center">
                            View Code Examples
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                        <p class="text-slate-500 mt-4">
                            The examples page has code for the common operations.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-dot-pattern"></div>
            <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Start building</h2>
                <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                    Pick your language and connect to {{ config('brand.name', 'Zelta') }}.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                        Get API Keys
                    </a>
                    <a href="{{ route('developers.show', 'examples') }}" class="btn-outline px-8 py-4 text-lg">
                        View Examples
                    </a>
                </div>
            </div>
        </section>

        
        <script>
            function copyCode(button, code) {
                navigator.clipboard.writeText(code).then(function() {
                    button.classList.add('copied');
                    button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Copied';
                    
                    setTimeout(function() {
                        button.classList.remove('copied');
                        button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg> Copy';
                    }, 2000);
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                });
            }
        </script>
@endsection