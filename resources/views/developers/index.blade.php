<x-app-layout>
    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        </div>
        
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    Developer Documentation
                </h1>
                <p class="text-xl md:text-2xl text-gray-300 max-w-4xl mx-auto mb-8">
                    Build on FinAegis platform. Open source, API-first, and designed for developers.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#quickstart" class="inline-flex items-center justify-center px-8 py-4 bg-white text-gray-900 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                        Quick Start
                    </a>
                    <a href="{{ route('developers.show', 'api-docs') }}" class="inline-flex items-center justify-center px-8 py-4 bg-gray-800 text-white rounded-lg font-semibold hover:bg-gray-700 transition-all border border-gray-700">
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
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Quick Start Guide</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Get up and running with FinAegis in three simple steps
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div>
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto">1</div>
                        <h3 class="text-xl font-semibold mt-4 mb-2">Clone Repository</h3>
                        <p class="text-gray-600">Get the source code from GitHub</p>
                    </div>
                    <div class="relative bg-gray-900 rounded-lg p-4 group">
                        <button class="absolute top-2 right-2 p-2 text-gray-400 hover:text-white transition opacity-0 group-hover:opacity-100" onclick="copyCode(this)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>git clone https://github.com/FinAegis/core-banking-prototype-laravel.git</code></pre>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div>
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto">2</div>
                        <h3 class="text-xl font-semibold mt-4 mb-2">Install & Configure</h3>
                        <p class="text-gray-600">Set up your development environment</p>
                    </div>
                    <div class="relative bg-gray-900 rounded-lg p-4 group">
                        <button class="absolute top-2 right-2 p-2 text-gray-400 hover:text-white transition opacity-0 group-hover:opacity-100" onclick="copyCode(this)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>composer install
cp .env.example .env
php artisan key:generate</code></pre>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div>
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto">3</div>
                        <h3 class="text-xl font-semibold mt-4 mb-2">Start Building</h3>
                        <p class="text-gray-600">Create your first API request</p>
                    </div>
                    <div class="relative bg-gray-900 rounded-lg p-4 group">
                        <button class="absolute top-2 right-2 p-2 text-gray-400 hover:text-white transition opacity-0 group-hover:opacity-100" onclick="copyCode(this)">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                        <pre class="text-green-400 text-sm overflow-x-auto"><code>php artisan serve</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Documentation Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Documentation</h2>
                <p class="text-xl text-gray-600">Everything you need to build with FinAegis</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- API Reference -->
                <a href="{{ route('developers.show', 'api-docs') }}" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-indigo-500">
                    <div class="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-indigo-200 transition-colors">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">API Reference</h3>
                    <p class="text-gray-600 mb-4">Complete API documentation with {{ config('platform.statistics.api_endpoints') }} endpoints</p>
                    <span class="text-indigo-600 font-semibold group-hover:text-indigo-700">Browse API →</span>
                </a>

                <!-- SDKs -->
                <a href="{{ route('developers.show', 'sdks') }}" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-purple-500 relative">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">Coming Soon</div>
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-purple-200 transition-colors">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">SDKs & Libraries</h3>
                    <p class="text-gray-600 mb-4">Client libraries for popular languages (planned)</p>
                    <span class="text-purple-600 font-semibold group-hover:text-purple-700">View SDKs →</span>
                </a>

                <!-- Examples -->
                <a href="{{ route('developers.show', 'examples') }}" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-green-500">
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-green-200 transition-colors">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Code Examples</h3>
                    <p class="text-gray-600 mb-4">Sample implementations and tutorials</p>
                    <span class="text-green-600 font-semibold group-hover:text-green-700">View Examples →</span>
                </a>

                <!-- Webhooks -->
                <a href="{{ route('developers.show', 'webhooks') }}" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-yellow-500 relative">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">Planned</div>
                    <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-yellow-200 transition-colors">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Webhooks</h3>
                    <p class="text-gray-600 mb-4">Real-time event notifications (coming soon)</p>
                    <span class="text-yellow-600 font-semibold group-hover:text-yellow-700">Learn More →</span>
                </a>

                <!-- Postman -->
                <a href="{{ route('developers.show', 'postman') }}" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-orange-500">
                    <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-orange-200 transition-colors">
                        <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Postman Collection</h3>
                    <p class="text-gray-600 mb-4">Import our API collection for testing</p>
                    <span class="text-orange-600 font-semibold group-hover:text-orange-700">Download →</span>
                </a>

                <!-- GitHub -->
                <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="group bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-1 border-2 border-transparent hover:border-gray-700">
                    <div class="w-14 h-14 bg-gray-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-gray-200 transition-colors">
                        <svg class="w-8 h-8 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">GitHub</h3>
                    <p class="text-gray-600 mb-4">Source code and issue tracking</p>
                    <span class="text-gray-700 font-semibold group-hover:text-gray-900">View Repo →</span>
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
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900">Authentication</h4>
                                <p class="text-gray-600">Bearer token authentication (API keys coming soon)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900">Versioning</h4>
                                <p class="text-gray-600">API versioning through URL path (/api/v1)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900">Rate Limiting</h4>
                                <p class="text-gray-600">60 requests per minute (configurable)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="relative bg-gray-900 rounded-lg p-6 group">
                    <button class="absolute top-4 right-4 p-2 text-gray-400 hover:text-white transition" onclick="copyCode(this)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                    <div class="text-gray-400 mb-2"># Example API Request</div>
                    <pre class="text-green-400 overflow-x-auto"><code>GET /api/v1/accounts/{id}

{
  "data": {
    "id": "acc_123456",
    "name": "Main Account",
    "currency": "EUR",
    "balance": 10000.00,
    "created_at": "2025-01-01T00:00:00Z"
  }
}</code></pre>
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
                <div class="bg-white rounded-xl p-6 text-center shadow-lg">
                    <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-3.586l4.293-4.293A6 6 0 0119 9z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">API Keys</h3>
                    <p class="text-sm text-gray-600">Secure API key management</p>
                </div>
                
                <div class="bg-white rounded-xl p-6 text-center shadow-lg">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">SDKs</h3>
                    <p class="text-sm text-gray-600">PHP, Python, JS libraries</p>
                </div>
                
                <div class="bg-white rounded-xl p-6 text-center shadow-lg">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Webhooks</h3>
                    <p class="text-sm text-gray-600">Real-time event delivery</p>
                </div>
                
                <div class="bg-white rounded-xl p-6 text-center shadow-lg">
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
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Start Building Today</h2>
            <p class="text-xl mb-8 text-gray-300">
                Join our developer community and help shape the future of finance
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="inline-flex items-center justify-center px-8 py-4 bg-white text-gray-900 rounded-lg font-semibold hover:bg-gray-100 transition-all transform hover:scale-105 shadow-lg">
                    View on GitHub
                </a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-4 bg-gray-800 text-white rounded-lg font-semibold hover:bg-gray-700 transition-all border border-gray-700">
                    Create Account
                </a>
            </div>
        </div>
    </section>

    @include('partials.footer')

    <script>
        function copyCode(button) {
            const codeBlock = button.parentElement.querySelector('code');
            const text = codeBlock.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalContent = button.innerHTML;
                button.innerHTML = '<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                
                setTimeout(() => {
                    button.innerHTML = originalContent;
                }, 2000);
            });
        }
    </script>
</x-app-layout>