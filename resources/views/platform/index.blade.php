<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Platform - Enterprise financial infrastructure with modular services. Start with GCU, add Exchange, Lending, Stablecoins, and Treasury as needed.">
        <meta name="keywords" content="FinAegis platform, financial infrastructure, banking platform, modular banking, GCU, crypto exchange, P2P lending, stablecoins, treasury management">
        
        <!-- Open Graph -->
        <meta property="og:title" content="FinAegis Platform - Enterprise Financial Infrastructure">
        <meta property="og:description" content="One platform, multiple solutions. Start with GCU for secure banking, add advanced services as you grow.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/platform') }}">

        <title>Platform Overview - FinAegis</title>

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
            .platform-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            .platform-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .platform-card.gcu {
                border-color: #667eea;
                background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            }
            .integration-line {
                background: linear-gradient(90deg, transparent 0%, #667eea 50%, transparent 100%);
                height: 2px;
                animation: flow 3s ease-in-out infinite;
            }
            @keyframes flow {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            .architecture-bg {
                background-image: radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                                  radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                                  radial-gradient(circle at 40% 20%, rgba(102, 126, 234, 0.05) 0%, transparent 50%);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        The FinAegis Platform
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        Enterprise financial infrastructure designed for the modern world. Start simple with GCU, scale infinitely with our modular architecture.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Start Building
                        </a>
                        <a href="#architecture" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                            Explore Architecture
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

        <!-- Platform Architecture Section -->
        <section id="architecture" class="py-20 bg-white architecture-bg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Modular Financial Architecture</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Our platform is built on a modular architecture that lets you start with what you need and add services as you grow
                    </p>
                </div>

                <!-- Platform Visual -->
                <div class="relative max-w-5xl mx-auto">
                    <!-- Core Platform -->
                    <div class="bg-gray-50 rounded-2xl p-8 shadow-lg mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4 text-center">FinAegis Core Platform</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="bg-white rounded-lg p-4 shadow">
                                <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                <p class="font-semibold text-gray-700">Security Engine</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 shadow">
                                <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="font-semibold text-gray-700">Payment Rails</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 shadow">
                                <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <p class="font-semibold text-gray-700">Bank Integration</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 shadow">
                                <svg class="w-8 h-8 text-indigo-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <p class="font-semibold text-gray-700">Compliance</p>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Lines -->
                    <div class="hidden md:block">
                        <div class="absolute left-1/2 transform -translate-x-1/2 w-px h-16 bg-gradient-to-b from-gray-300 to-transparent -mt-8"></div>
                    </div>

                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- GCU (Primary) -->
                        <div class="md:col-span-3 mb-8">
                            <div class="platform-card gcu rounded-xl p-8 shadow-lg">
                                <div class="flex items-center justify-between mb-6">
                                    <h3 class="text-3xl font-bold text-gray-900">Global Currency Unit (GCU)</h3>
                                    <span class="bg-indigo-600 text-white px-4 py-2 rounded-full text-sm font-semibold">Flagship Product</span>
                                </div>
                                <p class="text-lg text-gray-700 mb-6">
                                    The foundation of the FinAegis platform. Democratic multi-currency basket with real bank backing and government deposit insurance.
                                </p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">6+</div>
                                        <p class="text-sm text-gray-600">Currencies</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">100%</div>
                                        <p class="text-sm text-gray-600">Bank Backed</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">Monthly</div>
                                        <p class="text-sm text-gray-600">Voting</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-indigo-600">24/7</div>
                                        <p class="text-sm text-gray-600">Available</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <a href="{{ route('gcu') }}" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                        Learn more about GCU
                                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Sub-Products -->
                        <div class="md:col-span-3">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Optional Sub-Products</h3>
                            <p class="text-lg text-gray-600 mb-8 text-center">Enable only what you need. All services integrate seamlessly with GCU.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <!-- Exchange -->
                                <div class="platform-card bg-white rounded-xl p-6 shadow-md hover:border-purple-500">
                                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-xl font-semibold mb-2">Exchange</h4>
                                    <p class="text-gray-600 text-sm mb-4">
                                        Professional trading platform for crypto and fiat currencies
                                    </p>
                                    <ul class="text-sm text-gray-500 space-y-1 mb-4">
                                        <li>• Spot & derivatives</li>
                                        <li>• Institutional custody</li>
                                        <li>• Advanced order types</li>
                                    </ul>
                                    <a href="{{ route('sub-products.show', 'exchange') }}" class="text-purple-600 font-medium text-sm hover:text-purple-700">
                                        Explore Exchange →
                                    </a>
                                </div>

                                <!-- Lending -->
                                <div class="platform-card bg-white rounded-xl p-6 shadow-md hover:border-green-500">
                                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-xl font-semibold mb-2">Lending</h4>
                                    <p class="text-gray-600 text-sm mb-4">
                                        P2P lending marketplace connecting capital with opportunity
                                    </p>
                                    <ul class="text-sm text-gray-500 space-y-1 mb-4">
                                        <li>• SME financing</li>
                                        <li>• Automated scoring</li>
                                        <li>• Yield generation</li>
                                    </ul>
                                    <a href="{{ route('sub-products.show', 'lending') }}" class="text-green-600 font-medium text-sm hover:text-green-700">
                                        Explore Lending →
                                    </a>
                                </div>

                                <!-- Stablecoins -->
                                <div class="platform-card bg-white rounded-xl p-6 shadow-md hover:border-yellow-500">
                                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-xl font-semibold mb-2">Stablecoins</h4>
                                    <p class="text-gray-600 text-sm mb-4">
                                        Issue and manage stable tokens backed by real assets
                                    </p>
                                    <ul class="text-sm text-gray-500 space-y-1 mb-4">
                                        <li>• Multi-chain support</li>
                                        <li>• Full reserves</li>
                                        <li>• Instant redemption</li>
                                    </ul>
                                    <a href="{{ route('sub-products.show', 'stablecoins') }}" class="text-yellow-600 font-medium text-sm hover:text-yellow-700">
                                        Explore Stablecoins →
                                    </a>
                                </div>

                                <!-- Treasury -->
                                <div class="platform-card bg-white rounded-xl p-6 shadow-md hover:border-blue-500">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-xl font-semibold mb-2">Treasury</h4>
                                    <p class="text-gray-600 text-sm mb-4">
                                        Advanced cash management across multiple banks
                                    </p>
                                    <ul class="text-sm text-gray-500 space-y-1 mb-4">
                                        <li>• Multi-bank allocation</li>
                                        <li>• Risk optimization</li>
                                        <li>• Auto-rebalancing</li>
                                    </ul>
                                    <a href="{{ route('sub-products.show', 'treasury') }}" class="text-blue-600 font-medium text-sm hover:text-blue-700">
                                        Explore Treasury →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Integration Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-4xl font-bold text-gray-900 mb-6">Seamless Integration</h2>
                        <p class="text-lg text-gray-600 mb-8">
                            All sub-products integrate seamlessly with GCU and each other. Enable features through your dashboard, and they're instantly available across the platform.
                        </p>
                        
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Instant Activation</h3>
                                    <p class="text-gray-600">Enable any sub-product with a single click. No complex setup or migration required.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Unified Dashboard</h3>
                                    <p class="text-gray-600">Manage all your services from a single, intuitive dashboard. One login, complete control.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0 mr-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold mb-2">Pay As You Grow</h3>
                                    <p class="text-gray-600">Start with GCU for free. Add sub-products only when you need them, with transparent pricing.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl p-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6">Platform Capabilities</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center pb-4 border-b">
                                <span class="text-gray-700">Supported Currencies</span>
                                <span class="font-semibold text-gray-900">15+</span>
                            </div>
                            <div class="flex justify-between items-center pb-4 border-b">
                                <span class="text-gray-700">Banking Partners</span>
                                <span class="font-semibold text-gray-900">6</span>
                            </div>
                            <div class="flex justify-between items-center pb-4 border-b">
                                <span class="text-gray-700">API Endpoints</span>
                                <span class="font-semibold text-gray-900">100+</span>
                            </div>
                            <div class="flex justify-between items-center pb-4 border-b">
                                <span class="text-gray-700">Transaction Speed</span>
                                <span class="font-semibold text-gray-900">< 1s</span>
                            </div>
                            <div class="flex justify-between items-center pb-4 border-b">
                                <span class="text-gray-700">Uptime SLA</span>
                                <span class="font-semibold text-gray-900">99.99%</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Compliance Standards</span>
                                <span class="font-semibold text-gray-900">PSD2, MiCA</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Use Cases Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Built for Every Use Case</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        From individual users to enterprise treasury teams, our platform adapts to your needs
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Individual Users -->
                    <div class="bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Individual Users</h3>
                        <p class="text-gray-600 mb-6">
                            Start with a GCU account for secure, democratic banking. Your money stays in real banks with deposit insurance.
                        </p>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Multi-currency wallet</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Democratic voting rights</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Optional crypto trading</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Businesses -->
                    <div class="bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Businesses</h3>
                        <p class="text-gray-600 mb-6">
                            Complete financial operations platform. Accept payments, manage treasury, and access working capital.
                        </p>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Multi-bank treasury</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>P2P lending access</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Stablecoin payments</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Developers -->
                    <div class="bg-gray-50 rounded-xl p-8">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Developers</h3>
                        <p class="text-gray-600 mb-6">
                            Build on enterprise-grade infrastructure. White-label solutions and comprehensive APIs available.
                        </p>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>RESTful APIs</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Webhook events</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Banking-as-a-Service</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Start Building on FinAegis</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Join the future of finance. Start with what you need, grow at your own pace.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        Open Free Account
                    </a>
                    <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white hover:text-indigo-600 transition inline-block">
                        View Documentation
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
                            <li><a href="/gcu" class="hover:text-white transition">GCU</a></li>
                            <li><a href="/sub-products/exchange" class="hover:text-white transition">Exchange</a></li>
                            <li><a href="/sub-products/lending" class="hover:text-white transition">Lending</a></li>
                            <li><a href="/sub-products/stablecoins" class="hover:text-white transition">Stablecoins</a></li>
                            <li><a href="/sub-products/treasury" class="hover:text-white transition">Treasury</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Developers</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                            <li><a href="/developers/api" class="hover:text-white transition">API Reference</a></li>
                            <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
                            <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2">
                            <li><a href="/about" class="hover:text-white transition">About Us</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                            <li><a href="/support/contact" class="hover:text-white transition">Contact</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2">
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms of Service</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy Policy</a></li>
                            <li><a href="/legal/cookies" class="hover:text-white transition">Cookie Policy</a></li>
                            <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Built with Laravel v{{ Illuminate\Foundation\Application::VERSION }}</p>
                </div>
            </div>
        </footer>
    </body>
</html>