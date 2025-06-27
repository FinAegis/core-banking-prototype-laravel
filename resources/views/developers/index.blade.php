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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .code-bg {
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            }
        </style>
        
        <script>
            function copyCode(button) {
                const pre = button.parentElement.querySelector('pre');
                const code = pre.querySelector('code').textContent;
                navigator.clipboard.writeText(code);
                
                // Change icon to checkmark
                button.innerHTML = '<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                
                // Reset after 2 seconds
                setTimeout(() => {
                    button.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
                }, 2000);
            }
        </script>
    </head>
    <body class="antialiased">
        <x-alpha-banner />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 code-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Developer Documentation
                    </h1>
                    <p class="text-xl md:text-2xl text-gray-300 max-w-4xl mx-auto mb-8">
                        Build on FinAegis platform. Open source, API-first, and designed for developers.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="#quickstart" class="bg-white text-gray-900 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Quick Start
                        </a>
                        <a href="{{ route('developers.show', 'api-docs') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-gray-900 transition">
                            API Reference
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
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
                        <x-code-block language="bash">git clone https://github.com/FinAegis/core-banking-prototype-laravel.git</x-code-block>
                    </div>
                    
                    <!-- Step 2 -->
                    <div>
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto">2</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Install & Configure</h3>
                            <p class="text-gray-600">Set up your development environment</p>
                        </div>
                        <x-code-block language="bash">composer install
cp .env.example .env
php artisan key:generate</x-code-block>
                    </div>
                    
                    <!-- Step 3 -->
                    <div>
                        <div class="text-center mb-6">
                            <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto">3</div>
                            <h3 class="text-xl font-semibold mt-4 mb-2">Start Building</h3>
                            <p class="text-gray-600">Create your first API request</p>
                        </div>
                        <x-code-block language="bash">curl -X GET "http://localhost:8000/api/accounts" \
  -H "Authorization: Bearer YOUR_API_KEY"</x-code-block>
                    </div>
                </div>
            </div>
        </section>

        <!-- API Overview -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">API Overview</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        RESTful API built on modern standards with comprehensive documentation
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <h3 class="text-xl font-semibold mb-4">Authentication</h3>
                        <p class="text-gray-600 mb-6">
                            Secure API authentication using Bearer tokens. Get your API key from the dashboard after registration.
                        </p>
                        <x-code-block language="javascript">const headers = {
    'Authorization': 'Bearer YOUR_API_KEY',
    'Content-Type': 'application/json'
};</x-code-block>
                    </div>

                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <h3 class="text-xl font-semibold mb-4">Rate Limiting</h3>
                        <p class="text-gray-600 mb-6">
                            API requests are limited to ensure fair usage. Current limits during alpha testing:
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                1,000 requests per hour
                            </li>
                            <li class="flex items-center">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                10,000 requests per day
                            </li>
                        </ul>
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
                            <h3 class="text-xl font-semibold mb-2">API Documentation</h3>
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
                            <h3 class="text-xl font-semibold mb-2">SDKs & Libraries</h3>
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
                            <h3 class="text-xl font-semibold mb-2">Code Examples</h3>
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
                        <p class="text-indigo-200">API Endpoints</p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">3</div>
                        <p class="text-indigo-200">SDKs Coming</p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">MIT</div>
                        <p class="text-indigo-200">Open Source</p>
                    </div>
                    <div>
                        <div class="text-4xl md:text-5xl font-bold mb-2">24/7</div>
                        <p class="text-indigo-200">Support</p>
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
                    <a href="https://github.com/FinAegis" target="_blank" class="border-2 border-indigo-600 text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-indigo-50 transition">
                        View on GitHub
                    </a>
                </div>
            </div>
        </section>

        @include('partials.footer')
    </body>
</html>