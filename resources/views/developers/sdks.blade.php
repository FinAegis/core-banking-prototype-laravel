@extends('layouts.public')

@section('title', 'Build with FinAegis - Integration Guide')

@section('seo')
    @include('partials.seo', [
        'title' => 'Build with FinAegis - Integration Guide',
        'description' => 'Multiple ways to integrate FinAegis core banking infrastructure into your applications. REST API, native SDKs, webhooks, and more.',
        'keywords' => 'FinAegis, integration, API, SDK, webhooks, banking infrastructure, fintech, development',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .gradient-bg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
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
                            <span>REST API v5.2</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>Cross-Chain &amp; DeFi</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>BaaS Partner SDKs</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>RegTech Compliance</span>
                        </span>
                        <span class="inline-flex items-center px-4 py-2 bg-green-500/20 backdrop-blur-sm rounded-full text-sm">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                            <span>AI Transaction Query</span>
                        </span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6">
                        Build with FinAegis
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-3xl mx-auto">
                        Multiple ways to integrate our core banking infrastructure into your applications. Choose the approach that fits your needs.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#integration-methods" class="bg-white text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            View Integration Options
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                            Start with REST API
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Integration Methods -->
        <section id="integration-methods" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Integration Approaches</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Choose the integration method that best suits your application architecture and development needs
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
                                <h3 class="text-2xl font-bold text-gray-900">REST API</h3>
                                <p class="text-gray-600">Direct HTTP/JSON integration</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-700 mb-6">Integrate directly with our RESTful API using any programming language. Full OpenAPI 3.0 specification available.</p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700">Standard HTTP methods and status codes</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700">Comprehensive API documentation</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700">Sandbox environment for testing</span>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700">Postman collection available</span>
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
                        <div class="status-indicator status-coming-soon">In Development</div>
                        <div class="flex items-center mb-6">
                            <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Native SDKs</h3>
                                <p class="text-gray-600">Language-specific libraries</p>
                            </div>
                        </div>
                        
                        <p class="text-gray-700 mb-6">Official SDKs for popular programming languages with idiomatic APIs and comprehensive type safety.</p>
                        
                        <div class="grid grid-cols-2 gap-3 mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">JavaScript/TypeScript</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">Python</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">PHP</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">Go</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">Ruby</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 012 0v4a1 1 0 11-2 0V9zm0-4a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-600">Java</span>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-600">
                                Native SDKs are planned for future development. Currently, use our REST API directly.
                            </p>
                        </div>
                    </div>


                </div>
            </div>
        </section>

        <!-- AI Agent SDK Section -->
        <section class="py-20 bg-gradient-to-br from-purple-50 to-indigo-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">AI Agent Integration</h2>
                    <p class="text-xl text-gray-600">Connect to our AI-powered banking agents with simple API calls</p>
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
                            <pre class="code-block p-4"><code class="language-javascript"><span style="color: #c792ea;">const</span> <span style="color: #82aaff;">agent</span> = <span style="color: #c792ea;">new</span> <span style="color: #ffcb6b;">FinAegisAI</span>({
  <span style="color: #f07178;">apiKey</span>: <span style="color: #c3e88d;">'your-api-key'</span>
});

<span style="color: #546e7a;">// Send a message to the AI agent</span>
<span style="color: #c792ea;">const</span> <span style="color: #82aaff;">response</span> = <span style="color: #c792ea;">await</span> <span style="color: #82aaff;">agent</span>.<span style="color: #89ddff;">chat</span>({
  <span style="color: #f07178;">message</span>: <span style="color: #c3e88d;">'What is my account balance?'</span>,
  <span style="color: #f07178;">context</span>: { <span style="color: #f07178;">accountId</span>: <span style="color: #c3e88d;">'acc_123'</span> }
});

console.<span style="color: #89ddff;">log</span>(<span style="color: #82aaff;">response</span>.<span style="color: #f07178;">message</span>);
<span style="color: #546e7a;">// "Your current balance is $12,456.78"</span></code></pre>
                        </div>
                    </div>

                    <!-- MCP Tools Example -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-4">MCP Tool Integration</h3>
                        <div class="code-container">
                            <div class="code-header">
                                <span>Python Example</span>
                                <button class="copy-button" onclick="copyCode(this)">Copy</button>
                            </div>
                            <pre class="code-block p-4"><code class="language-python"><span style="color: #c792ea;">from</span> <span style="color: #ffcb6b;">finaegis</span> <span style="color: #c792ea;">import</span> <span style="color: #82aaff;">MCPClient</span>

<span style="color: #546e7a;"># Initialize MCP client</span>
<span style="color: #82aaff;">mcp</span> = <span style="color: #ffcb6b;">MCPClient</span>(<span style="color: #f07178;">api_key</span>=<span style="color: #c3e88d;">'your-api-key'</span>)

<span style="color: #546e7a;"># Use banking tools directly</span>
<span style="color: #82aaff;">balance</span> = <span style="color: #82aaff;">mcp</span>.<span style="color: #89ddff;">tools</span>.<span style="color: #89ddff;">get_account_balance</span>(
    <span style="color: #f07178;">account_id</span>=<span style="color: #c3e88d;">'acc_123'</span>,
    <span style="color: #f07178;">currency</span>=<span style="color: #c3e88d;">'USD'</span>
)

<span style="color: #c792ea;">print</span>(<span style="color: #c792ea;">f</span><span style="color: #c3e88d;">"Balance: {balance.amount} {balance.currency}"</span>)</code></pre>
                        </div>
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
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Model Context Protocol (MCP)</h2>
                    <p class="text-xl text-gray-600">Standard protocol for AI model integration with banking tools</p>
                </div>

                <div class="bg-gray-50 rounded-xl p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">15+ Banking Tools</h3>
                            <p class="text-sm text-gray-600">Account, transaction, compliance, and analytics tools</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">Built-in Security</h3>
                            <p class="text-sm text-gray-600">Authentication, authorization, and audit logging</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold mb-2">Real-time Processing</h3>
                            <p class="text-sm text-gray-600">Stream responses with WebSocket support</p>
                        </div>
                    </div>

                    <div class="code-container">
                        <div class="code-header">
                            <span>MCP Server Configuration</span>
                            <button class="copy-button" onclick="copyCode(this)">Copy</button>
                        </div>
                        <pre class="code-block p-4"><code class="language-json">{
  <span style="color: #f07178;">"name"</span>: <span style="color: #c3e88d;">"finaegis-mcp-server"</span>,
  <span style="color: #f07178;">"version"</span>: <span style="color: #c3e88d;">"1.0.0"</span>,
  <span style="color: #f07178;">"tools"</span>: [
    <span style="color: #c3e88d;">"GetAccountBalance"</span>,
    <span style="color: #c3e88d;">"TransferMoney"</span>,
    <span style="color: #c3e88d;">"KycVerification"</span>,
    <span style="color: #c3e88d;">"AmlScreening"</span>,
    <span style="color: #c3e88d;">"SpendingAnalysis"</span>
  ],
  <span style="color: #f07178;">"capabilities"</span>: {
    <span style="color: #f07178;">"streaming"</span>: <span style="color: #f78c6c;">true</span>,
    <span style="color: #f07178;">"authentication"</span>: <span style="color: #c3e88d;">"bearer"</span>,
    <span style="color: #f07178;">"rateLimit"</span>: <span style="color: #f78c6c;">1000</span>
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- SDK Coming Soon Section -->
        <section id="sdk-timeline" class="py-20 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gray-50 rounded-2xl p-8 text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Native SDKs Are Planned</h2>
                    <p class="text-gray-600 mb-6">
                        We're planning to build native SDKs for all major programming languages to make integration even easier.
                    </p>
                    <div class="flex flex-wrap gap-3 justify-center text-sm">
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">JavaScript</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">Python</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">PHP</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">Java</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">Go</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">Ruby</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">C#/.NET</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">Rust</span>
                        <span class="bg-white px-4 py-2 rounded-lg border border-gray-200">& more</span>
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
                        Available Now via Partner API (v2.9+)
                    </span>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">BaaS SDK Generation</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Generate type-safe, versioned SDKs for your partner integration in TypeScript, Python, Java, Go, and PHP -- directly through the Partner API.
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
                        <h4 class="font-semibold text-gray-900 mb-1">TypeScript</h4>
                        <p class="text-xs text-gray-500">@finaegis/sdk</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Available</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Python</h4>
                        <p class="text-xs text-gray-500">finaegis-sdk</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Available</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Java</h4>
                        <p class="text-xs text-gray-500">com.finaegis:sdk</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Available</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-cyan-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">Go</h4>
                        <p class="text-xs text-gray-500">finaegis-go</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Available</span>
                    </div>
                    <div class="sdk-card bg-white rounded-xl shadow-lg p-6 text-center">
                        <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">PHP</h4>
                        <p class="text-xs text-gray-500">finaegis/sdk</p>
                        <span class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-medium">Available</span>
                    </div>
                </div>

                <!-- SDK Generation Code Example -->
                <div class="max-w-4xl mx-auto">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Generate Your SDK via Partner API</h3>
                    <p class="text-gray-600 mb-6">
                        When you onboard as a BaaS partner, SDKs are automatically generated for your requested languages.
                        You can also regenerate or request additional language SDKs at any time.
                    </p>

                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto mb-8">
<pre><span class="text-gray-500"># Request SDK generation for your partner account</span>
curl -X POST https://api.finaegis.org/api/v1/partner/sdk/generate \
  -H "Authorization: Bearer YOUR_PARTNER_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "languages": ["typescript", "python", "java", "go", "php"],
    "api_version": "v5",
    "include_modules": ["accounts", "transfers", "crosschain", "defi", "compliance"],
    "options": {
      "include_types": true,
      "include_examples": true,
      "include_tests": true
    }
  }'

<span class="text-gray-500"># Response includes download URLs for each SDK</span>
{
  "data": {
    "generation_id": "sdkgen_abc123",
    "status": "completed",
    "packages": [
      {
        "language": "typescript",
        "version": "5.0.0",
        "package_name": "@finaegis/sdk",
        "download_url": "https://sdk.finaegis.org/packages/typescript/finaegis-sdk-5.0.0.tgz",
        "install_command": "npm install @finaegis/sdk@5.0.0"
      },
      {
        "language": "python",
        "version": "5.0.0",
        "package_name": "finaegis-sdk",
        "download_url": "https://sdk.finaegis.org/packages/python/finaegis-sdk-5.0.0.tar.gz",
        "install_command": "pip install finaegis-sdk==5.0.0"
      },
      {
        "language": "java",
        "version": "5.0.0",
        "package_name": "com.finaegis:sdk",
        "download_url": "https://sdk.finaegis.org/packages/java/finaegis-sdk-5.0.0.jar",
        "install_command": "mvn install com.finaegis:sdk:5.0.0"
      },
      {
        "language": "go",
        "version": "5.0.0",
        "package_name": "github.com/finaegis/sdk-go",
        "download_url": "https://sdk.finaegis.org/packages/go/finaegis-sdk-go-5.2.0.tar.gz",
        "install_command": "go get github.com/finaegis/sdk-go@v5.2.0"
      },
      {
        "language": "php",
        "version": "5.0.0",
        "package_name": "finaegis/sdk",
        "download_url": "https://sdk.finaegis.org/packages/php/finaegis-sdk-5.0.0.zip",
        "install_command": "composer require finaegis/sdk:^5.0"
      }
    ]
  }
}</pre>
                    </div>

                    <!-- Install Commands Grid -->
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">Quick Install</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">TypeScript / JavaScript</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>npm install @finaegis/sdk@5.0.0</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Python</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>pip install finaegis-sdk==5.0.0</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Java (Maven)</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>mvn install com.finaegis:sdk:5.0.0</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">Go</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>go get github.com/finaegis/sdk-go@v5.2.0</code>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-2">PHP (Composer)</p>
                            <div class="bg-gray-900 rounded-lg p-4 font-mono text-green-400 text-sm">
                                <code>composer require finaegis/sdk:^5.0</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Partner SDK Integration Guide -->
        <section id="partner-sdk-guide" class="py-20 bg-gradient-to-br from-amber-50 to-orange-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Partner SDK Integration Guide</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Step-by-step guide to integrating the FinAegis BaaS SDK into your partner application,
                        covering authentication, module access, and advanced features like Cross-Chain and DeFi.
                    </p>
                </div>

                <div class="max-w-5xl mx-auto space-y-8">
                    <!-- Step 1: Initialize the SDK -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-blue-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">1</span>
                                <h3 class="text-lg font-semibold text-white">Initialize the SDK with Your Partner Credentials</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 mb-4">
                                Use the API key and partner ID from your onboarding response. The SDK auto-configures based on your enabled modules.
                            </p>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-2">TypeScript</p>
                                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre>import { FinAegis } from '@finaegis/sdk';

const client = new FinAegis({
  apiKey: process.env.FINAEGIS_PARTNER_KEY,
  partnerId: 'partner_acme_abc123',
  environment: 'production', // or 'sandbox'
  modules: ['accounts', 'transfers',
    'crosschain', 'defi', 'compliance']
});</pre>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-2">Python</p>
                                    <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre>from finaegis import FinAegis

client = FinAegis(
    api_key=os.environ['FINAEGIS_PARTNER_KEY'],
    partner_id='partner_acme_abc123',
    environment='production',
    modules=['accounts', 'transfers',
        'crosschain', 'defi', 'compliance']
)</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Use Cross-Chain & DeFi Modules -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-cyan-600 to-teal-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-cyan-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">2</span>
                                <h3 class="text-lg font-semibold text-white">Access Cross-Chain and DeFi Modules (v5.2)</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 mb-4">
                                The SDK provides typed interfaces for bridge operations (Wormhole, LayerZero, Axelar), DEX aggregation (Uniswap, Aave, Curve, Lido),
                                cross-chain swaps, and multi-chain portfolio management.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre><span class="text-gray-500">// TypeScript -- Cross-Chain Bridge + DeFi Swap in one workflow</span>
async function crossChainSwapWorkflow() {
  <span class="text-gray-500">// Bridge USDC from Ethereum to Polygon</span>
  const bridgeQuote = await client.crosschain.bridge.quote({
    source_chain: 'ethereum',
    destination_chain: 'polygon',
    token: 'USDC',
    amount: '5000.00'
  });

  const bridgeTx = await client.crosschain.bridge.initiate({
    quote_id: bridgeQuote.data.quote_id,
    sender_address: '0x1234...abcd',
    recipient_address: '0x1234...abcd'
  });

  <span class="text-gray-500">// Wait for bridge completion, then swap on Polygon</span>
  await client.crosschain.bridge.waitForCompletion(bridgeTx.data.bridge_tx_id);

  const swapQuote = await client.defi.swap.quote({
    chain: 'polygon',
    token_in: 'USDC',
    token_out: 'WMATIC',
    amount_in: '5000.00'
  });

  const swap = await client.defi.swap.execute({
    quote_id: swapQuote.data.quote_id,
    wallet_address: '0x1234...abcd'
  });

  <span class="text-gray-500">// Get multi-chain portfolio overview</span>
  const portfolio = await client.crosschain.portfolio.get({
    wallet_address: '0x1234...abcd',
    chains: ['ethereum', 'polygon', 'arbitrum']
  });

  console.log('Portfolio total value:', portfolio.data.total_value_usd);
}</pre>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: RegTech Compliance Integration -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-emerald-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">3</span>
                                <h3 class="text-lg font-semibold text-white">Integrate RegTech Compliance (v2.8)</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 mb-4">
                                Built-in compliance modules for MiFID II reporting, MiCA compliance, and FATF Travel Rule -- automatically enforced based on your jurisdiction configuration.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre><span class="text-gray-500">// TypeScript -- Compliance-aware transfer</span>
async function compliantTransfer(transferParams) {
  <span class="text-gray-500">// Travel Rule check is automatic for transfers above threshold</span>
  const complianceResult = await client.regtech.travelRule.check({
    transfer_id: transferParams.id,
    originator: transferParams.originator,
    beneficiary: transferParams.beneficiary,
    transfer_details: {
      amount: transferParams.amount,
      currency: transferParams.currency
    }
  });

  if (!complianceResult.data.is_compliant) {
    throw new Error(
      `Compliance failed: ${complianceResult.data.compliance_issues
        .map(i => i.description).join(', ')}`
    );
  }

  <span class="text-gray-500">// MiCA compliance for crypto assets</span>
  const micaCheck = await client.regtech.mica.validate({
    asset_type: 'crypto',
    transaction_type: 'transfer',
    amount: transferParams.amount,
    jurisdiction: 'EU'
  });

  <span class="text-gray-500">// Proceed with transfer only if all checks pass</span>
  return await client.transfers.create(transferParams);
}</pre>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: AI-Powered Queries -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-violet-600 to-purple-600 px-6 py-4">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-white text-violet-600 rounded-full flex items-center justify-center font-bold text-sm mr-3">4</span>
                                <h3 class="text-lg font-semibold text-white">Use AI Transaction Queries (v2.8)</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 mb-4">
                                The SDK includes AI-powered transaction search that accepts natural language queries and returns structured, filterable results with risk scoring.
                            </p>
                            <div class="bg-gray-900 rounded-lg p-6 font-mono text-green-400 text-sm overflow-x-auto">
<pre><span class="text-gray-500">// TypeScript -- AI-powered transaction intelligence</span>
const insights = await client.ai.transactions({
  query: 'Large DeFi swaps on Ethereum this month with high slippage',
  account_id: 'acct_primary',
  options: {
    include_analytics: true,
    include_risk_scores: true
  }
});

console.log('Interpreted as:', insights.data.interpreted_query);
console.log('Found:', insights.data.total_results, 'transactions');
console.log('Total volume:', insights.data.analytics.total_volume);</pre>
                            </div>
                        </div>
                    </div>

                    <!-- SDK Module Reference -->
                    <div class="bg-white rounded-2xl shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-900 mb-6">SDK Module Reference</h3>
                        <p class="text-gray-600 mb-6">
                            Each BaaS partner SDK includes the following modules, based on the modules enabled during onboarding.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.accounts</h4>
                                <p class="text-sm text-gray-600">Account creation, balances, transactions</p>
                                <span class="text-xs text-gray-400">v1.0+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.transfers</h4>
                                <p class="text-sm text-gray-600">Payments, P2P transfers, bulk operations</p>
                                <span class="text-xs text-gray-400">v1.0+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.wallets</h4>
                                <p class="text-sm text-gray-600">Blockchain wallets, hardware wallet support</p>
                                <span class="text-xs text-gray-400">v2.1+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.compliance</h4>
                                <p class="text-sm text-gray-600">KYC/AML, sanctions screening</p>
                                <span class="text-xs text-gray-400">v1.0+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.regtech</h4>
                                <p class="text-sm text-gray-600">MiFID II, MiCA, Travel Rule compliance</p>
                                <span class="text-xs text-gray-400">v2.8+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.crosschain</h4>
                                <p class="text-sm text-gray-600">Bridge protocols, multi-chain portfolio</p>
                                <span class="text-xs text-gray-400">v5.2+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.defi</h4>
                                <p class="text-sm text-gray-600">DEX aggregation, lending, staking, yield</p>
                                <span class="text-xs text-gray-400">v5.2+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.ai</h4>
                                <p class="text-sm text-gray-600">Transaction queries, spending insights</p>
                                <span class="text-xs text-gray-400">v2.8+</span>
                            </div>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 mb-1">client.partner</h4>
                                <p class="text-sm text-gray-600">SDK generation, tenant management, config</p>
                                <span class="text-xs text-gray-400">v2.9+</span>
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
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Built for Your Use Case</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Whether you're building a fintech app, marketplace, or enterprise platform, FinAegis provides the banking infrastructure you need
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Digital Wallets</h3>
                        <p class="text-gray-700 mb-6">
                            Create secure digital wallets with multi-currency support, instant transfers, and comprehensive transaction history.
                        </p>
                        <ul class="space-y-2 text-gray-600">
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
                                Instant peer-to-peer transfers
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Marketplace Banking</h3>
                        <p class="text-gray-700 mb-6">
                            Enable split payments, escrow services, and automated payouts for your marketplace platform.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Automated split payments
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Escrow services
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Seller payouts
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Neobanking</h3>
                        <p class="text-gray-700 mb-6">
                            Launch a complete digital banking solution with accounts, cards, and lending capabilities.
                        </p>
                        <ul class="space-y-2 text-gray-600">
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
                                Virtual card issuance
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Personal finance tools
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Corporate Banking</h3>
                        <p class="text-gray-700 mb-6">
                            Streamline business banking with bulk payments, expense management, and team permissions.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Bulk payment processing
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
                                Expense management
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Lending Platform</h3>
                        <p class="text-gray-700 mb-6">
                            Build lending products with automated underwriting, loan management, and repayment processing.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Credit scoring integration
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Automated disbursements
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
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Investment Platform</h3>
                        <p class="text-gray-700 mb-6">
                            Enable investment accounts, portfolio management, and automated investing features.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Fractional investing
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
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Get Started in Minutes</h2>
                    <p class="text-xl text-gray-600">Three simple steps to integrate FinAegis into your application</p>
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
                            <h3 class="text-xl font-bold text-gray-900 mb-3">Sign Up & Get API Keys</h3>
                            <p class="text-gray-700">
                                Create your free account and generate API keys from your dashboard. You'll get instant access to our sandbox environment.
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
                            <h3 class="text-xl font-bold text-gray-900 mb-3">Create Your First Account</h3>
                            <p class="text-gray-700">
                                Use our API to create customer accounts. Each account can hold multiple currencies and support various transaction types.
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
                            <h3 class="text-xl font-bold text-gray-900 mb-3">Process Transactions</h3>
                            <p class="text-gray-700">
                                Start processing payments, transfers, and other transactions. Monitor everything in real-time through webhooks or API polling.
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
                        <p class="text-gray-600 mt-4">
                            See full code examples with syntax highlighting for all operations
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to integrate?</h2>
                <p class="text-xl text-purple-100 mb-8">
                    Choose your preferred language and start building with FinAegis today
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-purple-700 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Get API Keys
                    </a>
                    <a href="{{ route('developers.show', 'examples') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                        View Examples
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')
        
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