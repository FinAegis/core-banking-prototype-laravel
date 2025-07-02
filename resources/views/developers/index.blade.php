<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Developer Documentation - Build on FinAegis platform. Open source, API-first, and designed for developers.">
        <meta name="keywords" content="FinAegis, developer, API, documentation, SDK, integration">
        
        <title>Developer Documentation - FinAegis</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .dev-gradient {
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
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
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section with Animated Background -->
        <section class="pt-16 dev-gradient text-white relative overflow-hidden">
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
                        <span>v2.0 Documentation</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-white to-blue-200">
                        Built for Developers
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-4xl mx-auto mb-12">
                        Open source banking infrastructure with comprehensive APIs, SDKs, and documentation.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#quickstart" class="group bg-white text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl inline-flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Quick Start
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="group border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-gray-900 transition inline-flex items-center justify-center">
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
        <section class="py-6 bg-amber-50 border-b border-amber-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-center text-amber-800">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">Alpha Version:</span>
                    <span class="ml-2">Limited API endpoints available. Full API coming soon.</span>
                </div>
            </div>
        </section>

        <!-- Quick Start Section -->
        <section id="quickstart" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Quick Start Guide</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Get up and running with FinAegis in three simple steps
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="flex flex-col">
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto shadow-lg">1</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Clone Repository</h3>
                            <p class="text-gray-600">Get the source code from GitHub</p>
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
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">git</span> <span class="text-blue-400">clone</span> <span class="text-yellow-400">https://github.com/FinAegis/core-banking-prototype-laravel.git</span></div>
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
                            <p class="text-gray-600">Set up your development environment</p>
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
                            <p class="text-gray-600">Create your first API request</p>
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
                                    <div><span class="text-gray-500">$</span> <span class="text-green-400">curl</span> <span class="text-blue-400">-X</span> <span class="text-purple-400">GET</span> <span class="text-yellow-400">"http://localhost:8000/api/v1/accounts"</span> <span class="text-gray-400">\</span></div>
                                    <div>  <span class="text-blue-400">-H</span> <span class="text-yellow-400">"Authorization: Bearer YOUR_API_KEY"</span> <span class="text-gray-400">\</span></div>
                                    <div>  <span class="text-blue-400">-H</span> <span class="text-yellow-400">"Accept: application/json"</span></div>
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
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">API Overview</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        RESTful API built on modern standards with comprehensive documentation
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
                            <p class="text-gray-600 mb-6">
                                Secure API authentication using Bearer tokens. Get your API key from the dashboard after registration.
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
                                        <div><span class="text-purple-400">fetch</span><span class="text-gray-400">(</span><span class="text-yellow-400">'https://api.finaegis.org/v1/accounts'</span><span class="text-gray-400">,</span> <span class="text-gray-400">{</span></div>
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
                            <p class="text-gray-600 mb-6">
                                API requests are limited to ensure fair usage and platform stability.
                            </p>
                            <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700 font-medium">Per Hour</span>
                                    <span class="text-2xl font-bold text-indigo-600">1,000</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700 font-medium">Per Day</span>
                                    <span class="text-2xl font-bold text-indigo-600">10,000</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700 font-medium">Burst Rate</span>
                                    <span class="text-2xl font-bold text-indigo-600">100/min</span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-4">
                                Rate limit headers are included in all API responses for monitoring.
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
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">API Versioning</h4>
                        <p class="text-gray-600 text-sm">
                            All endpoints are versioned (v1, v2) to ensure backward compatibility as we evolve the API.
                        </p>
                    </div>

                    <!-- Webhooks -->
                    <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-200">
                        <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Webhooks</h4>
                        <p class="text-gray-600 text-sm">
                            Real-time event notifications for transactions, account updates, and system events.
                        </p>
                    </div>

                    <!-- SDKs -->
                    <div class="bg-gradient-to-br from-pink-50 to-orange-50 rounded-xl p-6 border border-pink-200">
                        <div class="w-10 h-10 bg-pink-600 rounded-lg flex items-center justify-center text-white mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Official SDKs</h4>
                        <p class="text-gray-600 text-sm">
                            Native SDKs for JavaScript, Python, PHP, and more coming soon.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Resources -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Developer Resources</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Everything you need to build amazing financial applications
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
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
                            <p class="text-gray-600 mb-4">Complete reference for all API endpoints</p>
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
                                SDKs & Libraries
                            </h3>
                            <p class="text-gray-600 mb-4">Official SDKs for popular languages</p>
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
                            <p class="text-gray-600 mb-4">Real-world integration examples</p>
                            <span class="text-green-600 font-medium group-hover:text-green-700">See Examples →</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="py-20 bg-indigo-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">{{ config('platform.statistics.api_endpoints') }}</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            API Endpoints
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">3</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                            SDKs Coming
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">MIT</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Open Source
                        </p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">24/7</div>
                        <p class="text-indigo-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Support
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">Ready to Build?</h2>
                <p class="text-xl text-gray-600 mb-8">
                    Join our developer community and start building the future of finance
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl">
                        Get API Key
                    </a>
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" target="_blank" class="border-2 border-indigo-600 text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-indigo-50 transition">
                        View on GitHub
                    </a>
                </div>
            </div>
        </section>

        <!-- Code Examples Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Code Examples</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Real-world examples to get you started quickly
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Create Account Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Create a New Account</h3>
                            <p class="text-gray-600 text-sm mt-1">Initialize a new bank account with initial deposit</p>
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
    <span class="text-purple-400">const</span> <span class="text-white">response</span> = <span class="text-purple-400">await</span> <span class="text-green-400">fetch</span>(<span class="text-amber-400">'https://api.finaegis.org/v1/accounts'</span>, {
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
                            <h3 class="text-lg font-semibold text-gray-900">Transfer Funds</h3>
                            <p class="text-gray-600 text-sm mt-1">Execute a transfer between two accounts</p>
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
    <span class="text-white">url</span> = <span class="text-amber-400">"https://api.finaegis.org/v1/transfers"</span>
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
                            <h3 class="text-lg font-semibold text-gray-900">Exchange to GCU</h3>
                            <p class="text-gray-600 text-sm mt-1">Convert traditional currency to Global Currency Units</p>
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
<span class="text-blue-400">$api_key</span> = <span class="text-amber-400">'YOUR_API_KEY'</span>;
<span class="text-blue-400">$endpoint</span> = <span class="text-amber-400">'https://api.finaegis.org/v1/gcu/exchange'</span>;

<span class="text-blue-400">$data</span> = [
    <span class="text-amber-400">'from_currency'</span> => <span class="text-amber-400">'USD'</span>,
    <span class="text-amber-400">'amount'</span> => <span class="text-pink-400">1000.00</span>,
    <span class="text-amber-400">'to_currency'</span> => <span class="text-amber-400">'GCU'</span>
];

<span class="text-blue-400">$ch</span> = <span class="text-green-400">curl_init</span>(<span class="text-blue-400">$endpoint</span>);
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_RETURNTRANSFER</span>, <span class="text-purple-400">true</span>);
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_POST</span>, <span class="text-purple-400">true</span>);
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_POSTFIELDS</span>, <span class="text-green-400">json_encode</span>(<span class="text-blue-400">$data</span>));
<span class="text-green-400">curl_setopt</span>(<span class="text-blue-400">$ch</span>, <span class="text-cyan-400">CURLOPT_HTTPHEADER</span>, [
    <span class="text-amber-400">'Authorization: Bearer '</span> . <span class="text-blue-400">$api_key</span>,
    <span class="text-amber-400">'Content-Type: application/json'</span>
]);

<span class="text-blue-400">$response</span> = <span class="text-green-400">curl_exec</span>(<span class="text-blue-400">$ch</span>);
<span class="text-blue-400">$result</span> = <span class="text-green-400">json_decode</span>(<span class="text-blue-400">$response</span>, <span class="text-purple-400">true</span>);

<span class="text-purple-400">echo</span> <span class="text-amber-400">"You will receive: "</span> . <span class="text-blue-400">$result</span>[<span class="text-amber-400">'gcu_amount'</span>] . <span class="text-amber-400">" GCU"</span>;
<span class="text-purple-400">echo</span> <span class="text-amber-400">"Exchange rate: 1 USD = "</span> . <span class="text-blue-400">$result</span>[<span class="text-amber-400">'rate'</span>] . <span class="text-amber-400">" GCU"</span>;</code></pre>
                        </div>
                    </div>

                    <!-- Webhook Example -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Handle Webhooks</h3>
                            <p class="text-gray-600 text-sm mt-1">Process real-time transaction notifications</p>
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

<span class="text-white">app</span>.<span class="text-green-400">post</span>(<span class="text-amber-400">'/webhooks/finaegis'</span>, (<span class="text-white">req</span>, <span class="text-white">res</span>) <span class="text-blue-400">=></span> {
    <span class="text-gray-400">// Verify webhook signature</span>
    <span class="text-purple-400">const</span> <span class="text-white">signature</span> = <span class="text-white">req</span>.<span class="text-cyan-400">headers</span>[<span class="text-amber-400">'x-finaegis-signature'</span>];
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

        @include('partials.footer')
    </body>
</html>