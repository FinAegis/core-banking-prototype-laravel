@extends('layouts.public')

@section('title', 'Developer Docs — REST, GraphQL & CLI Reference | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Developer Docs — REST, GraphQL & CLI Reference | ' . config('brand.name', 'Zelta'),
        'description' => 'The developer docs for ' . config('brand.name', 'Zelta') . ' cover more than 1,000 API routes, GraphQL, the Model Context Protocol server, SDKs, a command-line tool and WebSockets across 61 modules.',
        'keywords' => config('brand.name', 'Zelta') . ', developer, API, documentation, SDK, CLI, REST API, GraphQL, WebSocket, Laravel, banking API, open source, integration',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .dev-gradient {
        background: linear-gradient(135deg, #0c1222 0%, #131b2e 50%, #1e293b 100%);
    }
    .hljs {
        background: transparent !important;
        padding: 0 !important;
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
        background: #0f1419;
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
            .floating-blob {
                position: absolute;
                filter: blur(80px);
                opacity: 0.3;
                animation: float 20s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% { transform: translate(0, 0) scale(1); }
                33% { transform: translate(30px, -30px) scale(1.1); }
                66% { transform: translate(-20px, 20px) scale(0.9); }
            }
            .animate-blob {
                animation: blob 7s infinite;
            }
            @keyframes blob {
                0% { transform: translate(0px, 0px) scale(1); }
                33% { transform: translate(30px, -50px) scale(1.1); }
                66% { transform: translate(-20px, 20px) scale(0.9); }
                100% { transform: translate(0px, 0px) scale(1); }
            }
            .animation-delay-2000 {
                animation-delay: 2s;
            }
            .animation-delay-4000 {
                animation-delay: 4s;
            }
        </style>
        
        <script>
            function copyCode(button, codeId) {
                const codeElement = document.getElementById(codeId);
                const code = codeElement.textContent;
                navigator.clipboard.writeText(code);
                
                // Update button
                button.classList.add('copied');
                const originalContent = button.innerHTML;
                button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Copied!</span>';
                
                // Reset after 2 seconds
                setTimeout(() => {
                    button.classList.remove('copied');
                    button.innerHTML = originalContent;
                }, 2000);
            }
        </script>
</style>
@endpush

@section('content')

        <!-- Hero Section with Animated Background -->
        <section class="dev-gradient text-white relative overflow-hidden">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0">
                <div class="floating-blob w-96 h-96 bg-indigo-500 rounded-full absolute top-0 left-0 animate-blob"></div>
                <div class="floating-blob w-96 h-96 bg-purple-500 rounded-full absolute bottom-0 right-0 animate-blob animation-delay-2000"></div>
                <div class="floating-blob w-96 h-96 bg-pink-500 rounded-full absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 animate-blob animation-delay-4000"></div>
            </div>
            
            <!-- Grid pattern overlay -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" xmlns="http://www.w3.org/2000/svg"%3E%3Cdefs%3E%3Cpattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"%3E%3Cpath d="M 60 0 L 0 0 0 60" fill="none" stroke="white" stroke-width="1"%3E%3C/path%3E%3C/pattern%3E%3C/defs%3E%3Crect width="100%25" height="100%25" fill="url(%23grid)"%3E%3C/rect%3E%3C/svg%3E');"></div>
            </div>
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                        <span>Documentation for v7.18.1, with 61 domain modules and more than 1,000 API routes</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-white to-blue-200">
                        Build with More Than 1,000 API Routes
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-4xl mx-auto mb-12">
                        You can use REST, GraphQL, WebSockets and a command-line tool. They give you access to payments, lending, compliance tooling and the other modules.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#quickstart" class="group bg-white text-slate-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl inline-flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Quick Start
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="group border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-slate-900 transition inline-flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            API Reference
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-gray-50" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Status Alert -->
        <section class="py-6 bg-green-50 border-b border-green-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-center text-green-800">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">Version 7.18.1 is out.</span>
                    <span class="ml-2">Asset transfers, wallet currency conversions and Global Currency Unit demo trades now book the right amounts. The daily spending limit a person sets when connecting an AI assistant is now saved. The identity check in the closed card pilot now starts, and adding money to a card stays switched off. Version 7.18.0 added the provider-neutral card layer. It also removed the liquidity add, remove and rebalance flow and the saved bank accounts on the withdrawal page, because they never worked.</span>
                </div>
            </div>
        </section>

        <!-- Quick Start Section -->
        <section id="quickstart" class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Quick Start Guide</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        You can run {{ config('brand.name', 'Zelta') }} on your own computer in three steps.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="flex flex-col">
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg">1</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Clone Repository</h3>
                            <p class="text-slate-500">Get the source code from GitHub.</p>
                        </div>
                        <div class="code-container flex-1">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>Terminal</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-step1')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <div class="p-4 font-mono text-sm">
                                <div id="code-step1">
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">git</span> <span class="text-blue-400">clone</span> <span class="text-yellow-400">{{ config('brand.github_url') }}.git</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">cd</span> <span class="text-yellow-400">core-banking-prototype-laravel</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="flex flex-col">
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-pink-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg">2</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Install & Configure</h3>
                            <p class="text-slate-500">Set up your development environment.</p>
                        </div>
                        <div class="code-container flex-1">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>Terminal</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-step2')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <div class="p-4 font-mono text-sm">
                                <div id="code-step2">
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">composer</span> <span class="text-blue-400">install</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">cp</span> <span class="text-yellow-400">.env.example</span> <span class="text-yellow-400">.env</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">php</span> <span class="text-blue-400">artisan</span> <span class="text-purple-400">key:generate</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">php</span> <span class="text-blue-400">artisan</span> <span class="text-purple-400">migrate</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="flex flex-col">
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-gradient-to-br from-pink-500 to-orange-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg">3</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Start Building</h3>
                            <p class="text-slate-500">Check that the API is running, then make your first request.</p>
                        </div>
                        <div class="code-container flex-1">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>cURL</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-step3')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <div class="p-4 font-mono text-sm">
                                <div id="code-step3">
                                    <div><span class="text-gray-500"># Verify the API is running (no auth needed)</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">curl</span> <span class="text-yellow-400">http://localhost:8000/api/health</span></div>
                                    <div><span class="text-gray-500"># {"status":"healthy","checks":{"database":true,"cache":true,"app":true},...}</span></div>
                                    <div class="mt-2"><span class="text-gray-500"># Make an authenticated request</span></div>
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">curl</span> <span class="text-blue-400">-H</span> <span class="text-yellow-400">"Authorization: Bearer YOUR_API_KEY"</span> <span class="text-gray-400">\</span></div>
                                    <div>  <span class="text-yellow-400">http://localhost:8000/api/v2/accounts</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- API Overview -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">API Overview</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        The REST API follows common conventions and is described in an OpenAPI reference.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Authentication -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-8">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white mr-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold">Authentication</h3>
                            </div>
                            <p class="text-slate-500 mb-6">
                                The API uses bearer tokens for authentication. You can create an API key in the dashboard after you register.
                            </p>
                            <div class="code-container">
                                <div class="code-header">
                                    <div class="flex items-center gap-2">
                                        <span class="terminal-dot bg-red-500"></span>
                                        <span class="terminal-dot bg-yellow-500"></span>
                                        <span class="terminal-dot bg-green-500"></span>
                                        <span>JavaScript</span>
                                    </div>
                                    <button class="copy-button" onclick="copyCode(this, 'code-auth')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <div class="p-4 font-mono text-sm">
                                    <div id="code-auth">
                                        <div><span class="text-blue-400">const</span> <span class="text-white">headers</span> <span class="text-gray-400">=</span> <span class="text-gray-400">{</span></div>
                                        <div class="pl-4"><span class="text-green-400">'Authorization'</span><span class="text-gray-400">:</span> <span class="text-yellow-400">'Bearer YOUR_API_KEY'</span><span class="text-gray-400">,</span></div>
                                        <div class="pl-4"><span class="text-green-400">'Content-Type'</span><span class="text-gray-400">:</span> <span class="text-yellow-400">'application/json'</span><span class="text-gray-400">,</span></div>
                                        <div class="pl-4"><span class="text-green-400">'Accept'</span><span class="text-gray-400">:</span> <span class="text-yellow-400">'application/json'</span></div>
                                        <div><span class="text-gray-400">};</span></div>
                                        <div class="mt-4"></div>
                                        <div><span class="text-gray-400">// Example API call</span></div>
                                        <div><span class="text-purple-400">fetch</span><span class="text-gray-400">(</span><span class="text-yellow-400">'{{ config('app.url') }}/api/v2/accounts'</span><span class="text-gray-400">,</span> <span class="text-gray-400">{</span></div>
                                        <div class="pl-4"><span class="text-green-400">method</span><span class="text-gray-400">:</span> <span class="text-yellow-400">'GET'</span><span class="text-gray-400">,</span></div>
                                        <div class="pl-4"><span class="text-green-400">headers</span><span class="text-gray-400">:</span> <span class="text-white">headers</span></div>
                                        <div><span class="text-gray-400">})</span></div>
                                        <div><span class="text-gray-400">.</span><span class="text-purple-400">then</span><span class="text-gray-400">(</span><span class="text-white">response</span> <span class="text-blue-400">=></span> <span class="text-white">response</span><span class="text-gray-400">.</span><span class="text-purple-400">json</span><span class="text-gray-400">())</span></div>
                                        <div><span class="text-gray-400">.</span><span class="text-purple-400">then</span><span class="text-gray-400">(</span><span class="text-white">data</span> <span class="text-blue-400">=></span> <span class="text-white">console</span><span class="text-gray-400">.</span><span class="text-purple-400">log</span><span class="text-gray-400">(</span><span class="text-white">data</span><span class="text-gray-400">));</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rate Limiting -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-8">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center text-white mr-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold">Rate Limiting</h3>
                            </div>
                            <p class="text-slate-500 mb-6">
                                Each type of request has its own limit per minute. The limits keep the platform fair and stable.
                            </p>
                            <div class="bg-slate-50 rounded-lg p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Queries</span>
                                    <span class="text-2xl font-bold text-indigo-600">100/min</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Transactions</span>
                                    <span class="text-2xl font-bold text-indigo-600">30/min</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Sign-in attempts</span>
                                    <span class="text-2xl font-bold text-indigo-600">3/min</span>
                                </div>
                            </div>
                            <p class="text-sm text-slate-400 mt-4">
                                Responses carry rate limit headers, so you can see how many requests remain. Operators can change each limit in the configuration.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Additional API Features -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                    <!-- Versioning -->
                    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl p-6 border border-indigo-200">
                        <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-slate-900 mb-2">API Versioning</h4>
                        <p class="text-slate-500 text-sm">
                            Most endpoints carry a version in the path, such as v1 or v2. A new version can change an endpoint without breaking older clients.
                        </p>
                    </div>

                    <!-- Webhooks -->
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-200">
                        <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-slate-900 mb-2">Webhooks</h4>
                        <p class="text-slate-500 text-sm">
                            Webhooks notify your systems about transactions, account changes and system events as they happen.
                        </p>
                    </div>

                    <!-- SDKs -->
                    <div class="bg-gradient-to-br from-pink-50 to-orange-50 rounded-xl p-6 border border-pink-200">
                        <div class="w-10 h-10 bg-pink-600 rounded-lg flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-slate-900 mb-2">Official Client Libraries</h4>
                        <p class="text-slate-500 text-sm mb-3">
                            Official client libraries exist for JavaScript, Python and PHP.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">JavaScript</span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Python</span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">PHP</span>
                        </div>
                    </div>
                </div>

                <!-- Platform API Area Cards -->
                <div class="mt-16">
                    <h3 class="text-2xl font-bold text-slate-900 mb-2 text-center">Platform API Areas</h3>
                    <p class="text-slate-500 text-center mb-8">These cards show the main API areas of the 61 domain modules. Integration adapters for third-party APIs; no partnership or endorsement implied.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                        <!-- CrossChain -->
                        <a href="{{ route('developers.show', 'api-docs') }}#crosschain" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Cross-Chain</h4>
                                        <span class="text-xs text-slate-400">6 routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">Bridge adapters for Wormhole, LayerZero and Axelar compare fees and run cross-chain swaps. The API also tracks portfolios across chains.</p>
                                <span class="text-cyan-600 text-sm font-medium group-hover:text-cyan-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- DeFi -->
                        <a href="{{ route('developers.show', 'api-docs') }}#defi" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Decentralised Finance</h4>
                                        <span class="text-xs text-slate-400">8 routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">Swaps run through Uniswap and Curve, lending through Aave and staking through Lido. The API also covers flash loans and portfolio management.</p>
                                <span class="text-emerald-600 text-sm font-medium group-hover:text-emerald-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- RegTech -->
                        <a href="{{ route('developers.show', 'api-docs') }}#regtech" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Regulatory Reporting</h4>
                                        <span class="text-xs text-slate-400">11 routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">These endpoints build reports in the formats of the Markets in Financial Instruments Directive, the Markets in Crypto-Assets Regulation and the Travel Rule. Each jurisdiction has its own adapter.</p>
                                <span class="text-amber-600 text-sm font-medium group-hover:text-amber-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- MobilePayment -->
                        <a href="{{ route('developers.show', 'api-docs') }}#mobile-payment" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-violet-500 to-purple-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Mobile Payment</h4>
                                        <span class="text-xs text-slate-400">15+ routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The mobile endpoints handle payment intents, receipts, the activity feed, receive addresses and peer-to-peer transfers. People sign in with passkeys or biometrics.</p>
                                <span class="text-violet-600 text-sm font-medium group-hover:text-violet-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Partner API -->
                        <a href="{{ route('developers.show', 'api-docs') }}#partner-baas" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-rose-500 to-pink-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Partner API</h4>
                                        <span class="text-xs text-slate-400">28 routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">Banking-as-a-service partners can generate client libraries, set their own branding and embed widgets. They can also see their usage and bills.</p>
                                <span class="text-rose-600 text-sm font-medium group-hover:text-rose-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- AI -->
                        <a href="{{ route('developers.show', 'api-docs') }}#ai" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-gray-700 to-gray-900 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">AI Query</h4>
                                        <span class="text-xs text-slate-400">2 routes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">You can ask about transactions in plain language. The query endpoint answers with financial insights.</p>
                                <span class="text-slate-500 text-sm font-medium group-hover:text-slate-800">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- GraphQL API -->
                        <a href="{{ route('developers.show', 'api-docs') }}#graphql" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-sky-500 to-blue-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v3m0 12v3m9-9h-3M6 12H3m15.364-6.364l-2.121 2.121M8.757 15.243l-2.121 2.121m12.728 0l-2.121-2.121M8.757 8.757L6.636 6.636"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">GraphQL API</h4>
                                        <span class="text-xs text-slate-400">43 domains</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The schema-first GraphQL API is built with Lighthouse PHP. It has queries, mutations and subscriptions across 43 domains, and it batches database queries.</p>
                                <span class="text-sky-600 text-sm font-medium group-hover:text-sky-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Event Streaming -->
                        <a href="{{ route('developers.show', 'api-docs') }}#event-streaming" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-lime-500 to-green-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.348 14.651a3.75 3.75 0 010-5.303m5.304 0a3.75 3.75 0 010 5.303m-7.425 2.122a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.981 0 13.79M12 12h.008v.007H12V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Event Streaming</h4>
                                        <span class="text-xs text-slate-400">5 endpoints</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">Domain events are published to Redis Streams and read by consumer groups. A live dashboard shows projector lag and throughput.</p>
                                <span class="text-lime-600 text-sm font-medium group-hover:text-lime-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- SMS -->
                        <a href="{{ route('developers.show', 'api-docs') }}#sms" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-red-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">SMS</h4>
                                        <span class="text-xs text-slate-400">4 endpoints &middot; paid per message</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">You can send SMS messages through VertexSMS and pay for each one with USDC, Stripe or Lightning. The API tracks delivery and shows the rate for each country. A Model Context Protocol tool can send messages too.</p>
                                <span class="text-orange-600 text-sm font-medium group-hover:text-orange-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- x402 Protocol -->
                        <a href="{{ route('developers.show', 'api-docs') }}#x402" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">x402 Protocol</h4>
                                        <span class="text-xs text-slate-400">15+ endpoints</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">An API can ask for a small USDC payment before it answers, so you can charge for your own APIs. REST and GraphQL endpoints manage it, and AI agents can pay on their own.</p>
                                <span class="text-emerald-600 text-sm font-medium group-hover:text-emerald-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- ISO 20022 -->
                        <a href="{{ route('developers.show', 'api-docs') }}#iso20022" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">ISO 20022</h4>
                                        <span class="text-xs text-slate-400">8 message types</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The API reads, builds and validates eight ISO 20022 message types from the pacs, pain and camt families. It works at the message layer and does not connect to a live scheme.</p>
                                <span class="text-blue-600 text-sm font-medium group-hover:text-blue-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Open Banking -->
                        <a href="{{ route('developers.show', 'api-docs') }}#open-banking" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-teal-500 to-emerald-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Open Banking</h4>
                                        <span class="text-xs text-slate-400">Consents &middot; Accounts &middot; Payments</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The API manages consents, account access and payment initiation under the European Union's second Payment Services Directive. It includes adapters for the Berlin Group standard and the United Kingdom Open Banking standard, and it runs in demo mode.</p>
                                <span class="text-teal-600 text-sm font-medium group-hover:text-teal-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Payment Rails -->
                        <a href="{{ route('developers.show', 'api-docs') }}#payment-rails" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-amber-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Payment Rails</h4>
                                        <span class="text-xs text-slate-400">ACH &middot; Fedwire &middot; SEPA &middot; RTP &middot; FedNow</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">These modules build messages for the Automated Clearing House network, Fedwire, Real-Time Payments, FedNow and SEPA. Routing logic picks a rail for each payment. They do not settle real payments.</p>
                                <span class="text-orange-600 text-sm font-medium group-hover:text-orange-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Interledger -->
                        <a href="{{ route('developers.show', 'api-docs') }}#interledger" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-violet-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Interledger</h4>
                                        <span class="text-xs text-slate-400">Connector &middot; Open Payments &middot; Quotes</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The Interledger connector moves value between networks and quotes across currencies. It supports Open Payments with its own authorisation protocol.</p>
                                <span class="text-purple-600 text-sm font-medium group-hover:text-purple-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Ledger -->
                        <a href="{{ route('developers.show', 'api-docs') }}#ledger" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-slate-600 to-gray-700 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Ledger</h4>
                                        <span class="text-xs text-slate-400">Double-entry accounting</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The ledger keeps double-entry books with a chart of accounts and journal entries. It posts to the general ledger and produces a trial balance. An optional TigerBeetle driver handles high volumes.</p>
                                <span class="text-slate-600 text-sm font-medium group-hover:text-slate-800">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <!-- Microfinance -->
                        <a href="{{ route('developers.show', 'api-docs') }}#microfinance" class="group block">
                            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all hover:-translate-y-1 h-full">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-teal-600 rounded-lg flex items-center justify-center text-white mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-semibold text-slate-900">Microfinance</h4>
                                        <span class="text-xs text-slate-400">Group lending &middot; Provisions &middot; Tellers</span>
                                    </div>
                                </div>
                                <p class="text-slate-500 text-sm mb-3">The API supports group loans, loan-loss provisions by days overdue, share accounts, teller work and savings accounts.</p>
                                <span class="text-green-600 text-sm font-medium group-hover:text-green-700">View endpoints &rarr;</span>
                            </div>
                        </a>

                        <div class="col-span-full mt-2">
                            <p class="text-slate-500 text-sm text-center">
                                Explore payment features: <a href="/features/x402-protocol" class="text-blue-600 hover:text-blue-800 font-medium">x402 Protocol</a> &middot;
                                <a href="/features/machine-payments" class="text-blue-600 hover:text-blue-800 font-medium">Machine Payments</a> &middot;
                                <a href="/features/zelta-cli" class="text-blue-600 hover:text-blue-800 font-medium">Zelta CLI</a> &middot;
                                <a href="{{ route('developers.show', 'api-docs') }}#sms" class="text-blue-600 hover:text-blue-800 font-medium">SMS API</a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- Resources -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Developer Resources</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        These resources help you build on the platform.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <a href="{{ route('developers.show', 'mcp-tools') }}" class="group">
                        <div class="bg-white border border-emerald-200 rounded-xl p-8 hover:shadow-xl transition-all hover:-translate-y-2 h-full relative">
                            <span class="absolute top-3 right-3 text-[10px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">New</span>
                            <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2">Model Context Protocol Tools</h3>
                            <p class="text-slate-500 mb-4">{{ count(\App\Domain\MCP\Server\McpToolCatalog::enabledTools()) }} tools and 4 resources behind an OAuth 2.1 sign-in. Any client that follows the specification can connect.</p>
                            <span class="text-emerald-600 font-medium group-hover:text-emerald-700">Explore the tools →</span>
                        </div>
                    </a>

                    <a href="{{ route('developers.show', 'api-docs') }}" class="group">
                        <div class="bg-white border border-gray-200 rounded-xl p-8 hover:shadow-xl transition-all hover:-translate-y-2 h-full">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                API Documentation
                            </h3>
                            <p class="text-slate-500 mb-4">The reference describes the API endpoints.</p>
                            <span class="text-indigo-600 font-medium group-hover:text-indigo-700">Explore API →</span>
                        </div>
                    </a>

                    <a href="{{ route('developers.show', 'sdks') }}" class="group">
                        <div class="bg-white border border-gray-200 rounded-xl p-8 hover:shadow-xl transition-all hover:-translate-y-2 h-full">
                            <div class="w-14 h-14 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center mb-6 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Client Libraries
                            </h3>
                            <p class="text-slate-500 mb-4">Official client libraries exist for JavaScript, Python and PHP.</p>
                            <span class="text-purple-600 font-medium group-hover:text-purple-700">View SDKs →</span>
                        </div>
                    </a>

                    <a href="{{ route('developers.show', 'examples') }}" class="group">
                        <div class="bg-white border border-gray-200 rounded-xl p-8 hover:shadow-xl transition-all hover:-translate-y-2 h-full">
                            <div class="w-14 h-14 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mb-6 group-hover:bg-green-600 group-hover:text-white transition-colors">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold mb-2 flex items-center">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                                Code Examples
                            </h3>
                            <p class="text-slate-500 mb-4">Worked examples show common integrations.</p>
                            <span class="text-green-600 font-medium group-hover:text-green-700">See Examples →</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="py-20 bg-indigo-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-8 text-center">
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">1,000+</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            API Routes
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">61</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            Domain Modules
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">3</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                            Official Libraries
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">Apache-2.0</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Open Source
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">43</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            GraphQL Domains
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Partner API Key Authentication -->
        <section id="partner-auth" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Partner API Authentication</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        Banking-as-a-service partners use their own client credentials. These credentials work only on the partner API.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Partner Key Overview -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-8">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 rounded-lg flex items-center justify-center text-white mr-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold">Partner Credentials</h3>
                            </div>
                            <p class="text-slate-500 mb-6">
                                Each partner gets a client ID and a client secret during onboarding. They give access to the partner endpoints for client library generation, branding, widgets, usage and billing.
                            </p>
                            <div class="bg-slate-50 rounded-lg p-6 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Base Path</span>
                                    <code class="text-sm bg-slate-200 px-2 py-1 rounded">/api/partner/v1</code>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Areas</span>
                                    <span class="text-sm text-slate-500">Client libraries, branding, widgets, usage and billing</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Rate Limit</span>
                                    <span class="text-sm text-slate-500">60 to 1,000 requests per minute by tier</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-medium">Address Allowlist</span>
                                    <span class="text-sm text-slate-500">Applied when set</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Partner Auth Code Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-8">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-lg flex items-center justify-center text-white mr-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold">Partner Request Example</h3>
                            </div>
                            <p class="text-slate-500 mb-6">
                                Send the client ID in the <code class="bg-slate-100 px-1 rounded">X-Partner-Client-Id</code> header and the secret in the <code class="bg-slate-100 px-1 rounded">X-Partner-Client-Secret</code> header.
                            </p>
                            <div class="code-container">
                                <div class="code-header">
                                    <div class="flex items-center gap-2">
                                        <span class="terminal-dot bg-red-500"></span>
                                        <span class="terminal-dot bg-yellow-500"></span>
                                        <span class="terminal-dot bg-green-500"></span>
                                        <span>cURL</span>
                                    </div>
                                    <button class="copy-button" onclick="copyCode(this, 'code-partner-auth')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>Copy</span>
                                    </button>
                                </div>
                                <div class="p-4 font-mono text-sm">
                                    <div id="code-partner-auth">
                                        <div><span class="text-gray-500">$</span> <span class="text-green-400">curl</span> <span class="text-yellow-400">"{{ config('app.url') }}/api/partner/v1/profile"</span> <span class="text-gray-400">\</span></div>
                                        <div>  <span class="text-blue-400">-H</span> <span class="text-yellow-400">"X-Partner-Client-Id: YOUR_CLIENT_ID"</span> <span class="text-gray-400">\</span></div>
                                        <div>  <span class="text-blue-400">-H</span> <span class="text-yellow-400">"X-Partner-Client-Secret: YOUR_CLIENT_SECRET"</span> <span class="text-gray-400">\</span></div>
                                        <div>  <span class="text-blue-400">-H</span> <span class="text-yellow-400">"Accept: application/json"</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 bg-slate-50">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-6">Get an API Key</h2>
                <p class="text-xl text-slate-500 mb-8">
                    Create a sandbox account to get an API key. The source code and the issue tracker are on GitHub.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="btn-primary btn-lg">
                        Get API Key
                    </a>
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-outline-dark btn-lg">
                        View on GitHub
                    </a>
                </div>
            </div>
        </section>

        <!-- Code Examples Section -->
        <section class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Code Examples</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        These examples show common requests.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Create Account Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-slate-900">Create a New Account</h3>
                            <p class="text-slate-500 text-sm mt-1">Open a new account with an initial balance.</p>
                        </div>
                        <div class="code-container">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>JavaScript</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-create-account')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <pre class="p-4 code-block"><code id="code-create-account"><span class="text-purple-400">const</span> <span class="text-blue-400">createAccount</span> = <span class="text-purple-400">async</span> () <span class="text-blue-400">=></span> {
    <span class="text-purple-400">const</span> <span class="text-white">response</span> = <span class="text-purple-400">await</span> <span class="text-green-400">fetch</span>(<span class="text-amber-400">'{{ config('app.url') }}/api/v2/accounts'</span>, {
        <span class="text-cyan-400">method</span>: <span class="text-amber-400">'POST'</span>,
        <span class="text-cyan-400">headers</span>: {
            <span class="text-amber-400">'Authorization'</span>: <span class="text-amber-400">'Bearer YOUR_API_KEY'</span>,
            <span class="text-amber-400">'Content-Type'</span>: <span class="text-amber-400">'application/json'</span>
        },
        <span class="text-cyan-400">body</span>: <span class="text-white">JSON</span>.<span class="text-green-400">stringify</span>({
            <span class="text-cyan-400">customer_id</span>: <span class="text-amber-400">'cust_123456'</span>,
            <span class="text-cyan-400">currency</span>: <span class="text-amber-400">'USD'</span>,
            <span class="text-cyan-400">initial_balance</span>: <span class="text-pink-400">1000.00</span>,
            <span class="text-cyan-400">account_type</span>: <span class="text-amber-400">'checking'</span>
        })
    });
    
    <span class="text-purple-400">const</span> <span class="text-white">account</span> = <span class="text-purple-400">await</span> <span class="text-white">response</span>.<span class="text-green-400">json</span>();
    <span class="text-white">console</span>.<span class="text-green-400">log</span>(<span class="text-amber-400">'Account created:'</span>, <span class="text-white">account</span>);
};</code></pre>
                        </div>
                    </div>

                    <!-- Transfer Funds Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-slate-900">Transfer Funds</h3>
                            <p class="text-slate-500 text-sm mt-1">Move money between two accounts.</p>
                        </div>
                        <div class="code-container">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>Python</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-transfer')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <pre class="p-4 code-block"><code id="code-transfer"><span class="text-purple-400">import</span> <span class="text-amber-400">requests</span>

<span class="text-purple-400">def</span> <span class="text-blue-400">transfer_funds</span>(<span class="text-white">from_account</span>, <span class="text-white">to_account</span>, <span class="text-white">amount</span>):
    <span class="text-white">url</span> = <span class="text-amber-400">"{{ config('app.url') }}/api/v2/transfers"</span>
    <span class="text-white">headers</span> = {
        <span class="text-amber-400">"Authorization"</span>: <span class="text-amber-400">"Bearer YOUR_API_KEY"</span>,
        <span class="text-amber-400">"Content-Type"</span>: <span class="text-amber-400">"application/json"</span>
    }
    
    <span class="text-white">payload</span> = {
        <span class="text-amber-400">"from_account_id"</span>: <span class="text-white">from_account</span>,
        <span class="text-amber-400">"to_account_id"</span>: <span class="text-white">to_account</span>,
        <span class="text-amber-400">"amount"</span>: <span class="text-white">amount</span>,
        <span class="text-amber-400">"currency"</span>: <span class="text-amber-400">"USD"</span>,
        <span class="text-amber-400">"description"</span>: <span class="text-amber-400">"Payment transfer"</span>
    }
    
    <span class="text-white">response</span> = <span class="text-white">requests</span>.<span class="text-green-400">post</span>(<span class="text-white">url</span>, <span class="text-cyan-400">json</span>=<span class="text-white">payload</span>, <span class="text-cyan-400">headers</span>=<span class="text-white">headers</span>)
    <span class="text-purple-400">return</span> <span class="text-white">response</span>.<span class="text-green-400">json</span>()

<span class="text-gray-400"># Execute transfer</span>
<span class="text-white">result</span> = <span class="text-green-400">transfer_funds</span>(<span class="text-amber-400">"acc_123"</span>, <span class="text-amber-400">"acc_456"</span>, <span class="text-pink-400">250.00</span>)
<span class="text-green-400">print</span>(<span class="text-amber-400">f"Transfer ID: </span>{<span class="text-white">result</span>[<span class="text-amber-400">'transfer_id'</span>]}<span class="text-amber-400">"</span>)</code></pre>
                        </div>
                    </div>

                    <!-- GCU Exchange Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-slate-900">Read the Global Currency Unit Demo Basket</h3>
                            <p class="text-slate-500 text-sm mt-1">This request reads the simulated basket of the Global Currency Unit demo. The unit has no monetary value and is not issued or sold.</p>
                        </div>
                        <div class="code-container">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>PHP</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-gcu-exchange')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <pre class="p-4 code-block"><code id="code-gcu-exchange"><span class="text-purple-400">&lt;?php</span>
<span class="text-blue-400">$endpoint</span> = <span class="text-amber-400">'{{ config('app.url') }}/api/v2/gcu/composition'</span>;

<span class="text-blue-400">$ch</span> = <span class="text-green-400">curl_init</span>(<span class="text-blue-400">$endpoint</span>);
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_RETURNTRANSFER</span>, <span class="text-purple-400">true</span>);
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_HTTPHEADER</span>, [<span class="text-amber-400">'Accept: application/json'</span>]);

<span class="text-blue-400">$response</span> = <span class="text-green-400">curl_exec</span>(<span class="text-blue-400">$ch</span>);
<span class="text-blue-400">$result</span> = <span class="text-green-400">json_decode</span>(<span class="text-blue-400">$response</span>, <span class="text-purple-400">true</span>);

<span class="text-purple-400">foreach</span> (<span class="text-blue-400">$result</span>[<span class="text-amber-400">'data'</span>][<span class="text-amber-400">'composition'</span>] <span class="text-purple-400">as</span> <span class="text-blue-400">$component</span>) {
    <span class="text-purple-400">echo</span> <span class="text-blue-400">$component</span>[<span class="text-amber-400">'asset_code'</span>] . <span class="text-amber-400">': '</span> . <span class="text-blue-400">$component</span>[<span class="text-amber-400">'weight'</span>] . <span class="text-amber-400">"\n"</span>;
}</code></pre>
                        </div>
                    </div>

                    <!-- Webhook Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-slate-900">Handle Webhooks</h3>
                            <p class="text-slate-500 text-sm mt-1">Check the signature, then handle each transaction event.</p>
                        </div>
                        <div class="code-container">
                            <div class="code-header">
                                <div class="flex items-center gap-2">
                                    <span class="terminal-dot bg-red-500"></span>
                                    <span class="terminal-dot bg-yellow-500"></span>
                                    <span class="terminal-dot bg-green-500"></span>
                                    <span>Node.js</span>
                                </div>
                                <button class="copy-button" onclick="copyCode(this, 'code-webhook')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <pre class="p-4 code-block"><code id="code-webhook"><span class="text-purple-400">const</span> <span class="text-white">express</span> = <span class="text-green-400">require</span>(<span class="text-amber-400">'express'</span>);
<span class="text-purple-400">const</span> <span class="text-white">crypto</span> = <span class="text-green-400">require</span>(<span class="text-amber-400">'crypto'</span>);

<span class="text-purple-400">const</span> <span class="text-white">app</span> = <span class="text-green-400">express</span>();
<span class="text-white">app</span>.<span class="text-green-400">use</span>(<span class="text-white">express</span>.<span class="text-green-400">json</span>());

<span class="text-white">app</span>.<span class="text-green-400">post</span>(<span class="text-amber-400">'/webhooks'</span>, (<span class="text-white">req</span>, <span class="text-white">res</span>) <span class="text-blue-400">=></span> {
    <span class="text-gray-400">// Verify webhook signature</span>
    <span class="text-purple-400">const</span> <span class="text-white">signature</span> = <span class="text-white">req</span>.<span class="text-cyan-400">headers</span>[<span class="text-amber-400">'x-webhook-signature'</span>];
    <span class="text-purple-400">const</span> <span class="text-white">payload</span> = <span class="text-white">JSON</span>.<span class="text-green-400">stringify</span>(<span class="text-white">req</span>.<span class="text-cyan-400">body</span>);
    <span class="text-purple-400">const</span> <span class="text-white">secret</span> = <span class="text-white">process</span>.<span class="text-cyan-400">env</span>.<span class="text-cyan-400">WEBHOOK_SECRET</span>;
    
    <span class="text-purple-400">const</span> <span class="text-white">expectedSignature</span> = <span class="text-white">crypto</span>
        .<span class="text-green-400">createHmac</span>(<span class="text-amber-400">'sha256'</span>, <span class="text-white">secret</span>)
        .<span class="text-green-400">update</span>(<span class="text-white">payload</span>)
        .<span class="text-green-400">digest</span>(<span class="text-amber-400">'hex'</span>);
    
    <span class="text-purple-400">if</span> (<span class="text-white">signature</span> !== <span class="text-white">expectedSignature</span>) {
        <span class="text-purple-400">return</span> <span class="text-white">res</span>.<span class="text-green-400">status</span>(<span class="text-pink-400">401</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'Invalid signature'</span>);
    }
    
    <span class="text-gray-400">// Process the webhook</span>
    <span class="text-purple-400">const</span> { <span class="text-white">event</span>, <span class="text-white">data</span> } = <span class="text-white">req</span>.<span class="text-cyan-400">body</span>;
    
    <span class="text-purple-400">switch</span> (<span class="text-white">event</span>) {
        <span class="text-purple-400">case</span> <span class="text-amber-400">'transaction.completed'</span>:
            <span class="text-white">console</span>.<span class="text-green-400">log</span>(<span class="text-amber-400">`Transaction </span>${<span class="text-white">data</span>.<span class="text-cyan-400">id</span>}<span class="text-amber-400"> completed`</span>);
            <span class="text-gray-400">// Handle completed transaction</span>
            <span class="text-purple-400">break</span>;
        <span class="text-purple-400">case</span> <span class="text-amber-400">'account.created'</span>:
            <span class="text-white">console</span>.<span class="text-green-400">log</span>(<span class="text-amber-400">`New account created: </span>${<span class="text-white">data</span>.<span class="text-cyan-400">account_id</span>}<span class="text-amber-400">`</span>);
            <span class="text-gray-400">// Handle new account</span>
            <span class="text-purple-400">break</span>;
    }
    
    <span class="text-white">res</span>.<span class="text-green-400">status</span>(<span class="text-pink-400">200</span>).<span class="text-green-400">send</span>(<span class="text-amber-400">'OK'</span>);
});</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

@endsection