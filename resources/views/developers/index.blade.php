<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Developer Documentation - Build financial applications with our open source banking platform API.">
        <meta name="keywords" content="FinAegis API, developer docs, banking API, financial API, open source banking">
        
        <title>Developer Documentation - FinAegis</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            }
            .code-block {
                background: #1f2937;
                border-radius: 8px;
                overflow-x: auto;
            }
            .doc-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            .doc-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.1);
                border-color: #667eea;
            }
        </style>
    </head>
    <body class="antialiased bg-gray-50">
        <x-alpha-banner />
        
        <!-- Spacer for fixed banner -->
        <div class="h-12"></div>
        
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 pb-20 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Developer Documentation
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-gray-300 max-w-4xl mx-auto">
                        Build on FinAegis platform. Open source, API-first, and designed for developers.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#quickstart" class="bg-white text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                            Quick Start
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/10 transition">
                            API Reference
                        </a>
                    </div>
                </div>
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
        <section id="quickstart" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Quick Start Guide</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Get up and running with FinAegis in three simple steps
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">1</div>
                        <h3 class="text-xl font-semibold mb-3">Clone Repository</h3>
                        <p class="text-gray-600 mb-4">Get the source code from GitHub</p>
                        <div class="code-block p-4 text-sm">
                            <code class="text-green-400">git clone https://github.com/FinAegis/core-banking-prototype-laravel.git</code>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">2</div>
                        <h3 class="text-xl font-semibold mb-3">Install & Configure</h3>
                        <p class="text-gray-600 mb-4">Set up your development environment</p>
                        <div class="code-block p-4 text-sm text-left">
                            <code class="text-green-400">composer install<br>cp .env.example .env<br>php artisan key:generate</code>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">3</div>
                        <h3 class="text-xl font-semibold mb-3">Start Building</h3>
                        <p class="text-gray-600 mb-4">Create your first API request</p>
                        <div class="code-block p-4 text-sm">
                            <code class="text-green-400">php artisan serve</code>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Documentation Grid -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Documentation</h2>
                    <p class="text-xl text-gray-600">Everything you need to build with FinAegis</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- API Reference -->
                    <a href="{{ route('developers.show', 'api-docs') }}" class="doc-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">API Reference</h3>
                        <p class="text-gray-600 mb-4">Complete API documentation with {{ config('platform.statistics.api_endpoints') }} endpoints</p>
                        <span class="text-indigo-600 font-semibold">Browse API →</span>
                    </a>

                    <!-- SDKs -->
                    <a href="{{ route('developers.show', 'sdks') }}" class="doc-card bg-white rounded-xl p-8 shadow-lg relative">
                        <div class="absolute top-4 right-4 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">Coming Soon</div>
                        <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">SDKs & Libraries</h3>
                        <p class="text-gray-600 mb-4">Client libraries for popular languages (planned)</p>
                        <span class="text-purple-600 font-semibold">View SDKs →</span>
                    </a>

                    <!-- Examples -->
                    <a href="{{ route('developers.show', 'examples') }}" class="doc-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Code Examples</h3>
                        <p class="text-gray-600 mb-4">Sample implementations and tutorials</p>
                        <span class="text-green-600 font-semibold">View Examples →</span>
                    </a>

                    <!-- Webhooks -->
                    <a href="{{ route('developers.show', 'webhooks') }}" class="doc-card bg-white rounded-xl p-8 shadow-lg relative">
                        <div class="absolute top-4 right-4 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">Planned</div>
                        <div class="w-14 h-14 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Webhooks</h3>
                        <p class="text-gray-600 mb-4">Real-time event notifications (coming soon)</p>
                        <span class="text-yellow-600 font-semibold">Learn More →</span>
                    </a>

                    <!-- Postman -->
                    <a href="{{ route('developers.show', 'postman') }}" class="doc-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-orange-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Postman Collection</h3>
                        <p class="text-gray-600 mb-4">Import our API collection for testing</p>
                        <span class="text-orange-600 font-semibold">Download →</span>
                    </a>

                    <!-- GitHub -->
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="doc-card bg-white rounded-xl p-8 shadow-lg">
                        <div class="w-14 h-14 bg-gray-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-7 h-7 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">GitHub</h3>
                        <p class="text-gray-600 mb-4">Source code and issue tracking</p>
                        <span class="text-gray-700 font-semibold">View Repo →</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- API Overview -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-6">RESTful API Design</h2>
                        <p class="text-lg text-gray-600 mb-8">
                            Our API follows REST principles with predictable resource-oriented URLs, accepts JSON request bodies, and returns JSON responses.
                        </p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Authentication</h4>
                                    <p class="text-gray-600">Bearer token authentication (API keys coming soon)</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Versioning</h4>
                                    <p class="text-gray-600">API versioning through URL path (/api/v1)</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Rate Limiting</h4>
                                    <p class="text-gray-600">{{ config('platform.api.rate_limit') }} (configurable)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="code-block p-6 text-sm">
                        <div class="text-gray-400 mb-2"># Example API Request</div>
                        <div class="text-green-400">GET /api/v1/accounts/{id}</div>
                        <div class="text-gray-300 mt-4">
                            {<br>
                            &nbsp;&nbsp;"data": {<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"id": "acc_123456",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"name": "Main Account",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"currency": "EUR",<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"balance": 10000.00,<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;"created_at": "2025-01-01T00:00:00Z"<br>
                            &nbsp;&nbsp;}<br>
                            }
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Coming Soon Features -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Coming Soon</h2>
                    <p class="text-xl text-gray-600">Features we're working on for developers</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-lg p-6 text-center">
                        <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-3.586l4.293-4.293A6 6 0 0119 9z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">API Keys</h3>
                        <p class="text-sm text-gray-600">Secure API key management</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-6 text-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">SDKs</h3>
                        <p class="text-sm text-gray-600">PHP, Python, JS libraries</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-6 text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Webhooks</h3>
                        <p class="text-sm text-gray-600">Real-time event delivery</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-6 text-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Sandbox</h3>
                        <p class="text-sm text-gray-600">Test environment</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-gray-900 text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Start Building Today</h2>
                <p class="text-xl mb-8 text-gray-300">
                    Join our developer community and help shape the future of finance
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="bg-white text-gray-900 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        View on GitHub
                    </a>
                    <a href="{{ route('register') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/10 transition inline-block">
                        Create Account
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Platform</h4>
                        <ul class="space-y-2">
                            <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                            <li><a href="/gcu" class="hover:text-white transition">GCU</a></li>
                            <li><a href="/sub-products" class="hover:text-white transition">Modules</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Developers</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                            <li><a href="/developers/api-docs" class="hover:text-white transition">API Reference</a></li>
                            <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
                            <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Resources</h4>
                        <ul class="space-y-2">
                            <li><a href="/support" class="hover:text-white transition">Support</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                            <li><a href="/about" class="hover:text-white transition">About</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2">
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                            <li><a href="/legal/cookies" class="hover:text-white transition">Cookies</a></li>
                            <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Open Source Project.</p>
                </div>
            </div>
        </footer>
    </body>
</html>