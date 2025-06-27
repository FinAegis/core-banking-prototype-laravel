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
            .gradient-text {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .glass-effect {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            .module-card {
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            .module-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, transparent 0%, rgba(102, 126, 234, 0.1) 100%);
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .module-card:hover::before {
                opacity: 1;
            }
            .module-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .architecture-line {
                stroke-dasharray: 5,5;
                animation: dash 20s linear infinite;
            }
            @keyframes dash {
                to {
                    stroke-dashoffset: -100;
                }
            }
            .floating {
                animation: float 6s ease-in-out infinite;
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-20px); }
            }
        </style>
    </head>
    <body class="antialiased bg-gray-50">
        <x-alpha-banner />
        
        <!-- Spacer for fixed banner -->
        <div class="h-12"></div>
        
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 pb-20 gradient-bg text-white relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full mb-6">
                        <span class="text-sm font-medium">🚀 Building the Future of Finance</span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-bold mb-6">
                        The FinAegis Platform
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        Enterprise financial infrastructure designed for the modern world. Start simple with GCU, scale infinitely with our modular architecture.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl transform hover:scale-105">
                            Start Building
                        </a>
                        <a href="#modules" class="glass-effect text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white/20 transition">
                            Explore Modules
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Platform Overview -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">One Platform, Endless Possibilities</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Built on modern microservices architecture with bank-grade security and compliance at its core
                    </p>
                </div>

                <!-- Architecture Visualization -->
                <div class="relative">
                    <!-- Core Platform -->
                    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-3xl p-8 mb-12 border border-indigo-100">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Core Infrastructure</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold text-gray-800">Security</h4>
                                <p class="text-sm text-gray-600 mt-1">Bank-grade encryption</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold text-gray-800">Payments</h4>
                                <p class="text-sm text-gray-600 mt-1">Multi-currency rails</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold text-gray-800">Banking</h4>
                                <p class="text-sm text-gray-600 mt-1">{{ config('platform.statistics.banking_partners') }} partner banks</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold text-gray-800">Compliance</h4>
                                <p class="text-sm text-gray-600 mt-1">PSD2 & MiCA ready</p>
                            </div>
                        </div>
                    </div>

                    <!-- Connection SVG -->
                    <svg class="absolute left-1/2 transform -translate-x-1/2 -mt-6 w-2" height="60">
                        <line x1="1" y1="0" x2="1" y2="60" stroke="#667eea" stroke-width="2" class="architecture-line"/>
                    </svg>
                </div>
            </div>
        </section>

        <!-- Modules Section -->
        <section id="modules" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Modular Financial Services</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Start with GCU, our flagship product. Add modules as your needs grow.
                    </p>
                </div>

                <!-- GCU Featured -->
                <div class="mb-12">
                    <div class="module-card bg-white rounded-3xl shadow-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-1">
                            <div class="bg-white p-8">
                                <div class="flex flex-col lg:flex-row items-center gap-8">
                                    <div class="flex-1">
                                        <div class="inline-flex items-center px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-semibold mb-4">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                            Flagship Product
                                        </div>
                                        <h3 class="text-3xl font-bold text-gray-900 mb-4">Global Currency Unit (GCU)</h3>
                                        <p class="text-lg text-gray-600 mb-6">
                                            Democratic multi-currency basket with real bank backing. Your gateway to modern finance.
                                        </p>
                                        <div class="grid grid-cols-2 gap-4 mb-6">
                                            <div class="flex items-center text-gray-700">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span>{{ config('platform.statistics.supported_currencies') }} currencies supported</span>
                                            </div>
                                            <div class="flex items-center text-gray-700">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span>Bank deposit insurance</span>
                                            </div>
                                            <div class="flex items-center text-gray-700">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span>Democratic voting</span>
                                            </div>
                                            <div class="flex items-center text-gray-700">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span>Instant transfers</span>
                                            </div>
                                        </div>
                                        <a href="{{ route('gcu') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition transform hover:scale-105">
                                            Explore GCU
                                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    </div>
                                    <div class="lg:w-64 floating">
                                        <div class="text-8xl font-bold gradient-text">Ǥ</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Optional Modules Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Exchange Module -->
                    <div class="module-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            @if(config('platform.sub_products.exchange.enabled'))
                                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-semibold">Active</span>
                            @else
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold">{{ config('platform.sub_products.exchange.launch_date') }}</span>
                            @endif
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Exchange</h3>
                        <p class="text-gray-600 mb-4">Professional trading platform for digital and traditional assets.</p>
                        <ul class="space-y-2 text-sm text-gray-700 mb-6">
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-purple-600 rounded-full mr-2"></span>
                                Spot trading (planned)
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-purple-600 rounded-full mr-2"></span>
                                Institutional custody
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-purple-600 rounded-full mr-2"></span>
                                API trading
                            </li>
                        </ul>
                        <a href="{{ route('sub-products.show', 'exchange') }}" class="text-purple-600 font-semibold hover:text-purple-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Lending Module -->
                    <div class="module-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold">{{ config('platform.sub_products.lending.launch_date') }}</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Lending</h3>
                        <p class="text-gray-600 mb-4">P2P lending marketplace for businesses and investors.</p>
                        <ul class="space-y-2 text-sm text-gray-700 mb-6">
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                                SME financing
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                                Automated scoring
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                                Yield optimization
                            </li>
                        </ul>
                        <a href="{{ route('sub-products.show', 'lending') }}" class="text-green-600 font-semibold hover:text-green-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Stablecoins Module -->
                    <div class="module-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold">{{ config('platform.sub_products.stablecoins.launch_date') }}</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Stablecoins</h3>
                        <p class="text-gray-600 mb-4">Issue and manage stable digital currencies.</p>
                        <ul class="space-y-2 text-sm text-gray-700 mb-6">
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-yellow-600 rounded-full mr-2"></span>
                                1:1 backing
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-yellow-600 rounded-full mr-2"></span>
                                Multi-chain
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-yellow-600 rounded-full mr-2"></span>
                                MiCA compliant
                            </li>
                        </ul>
                        <a href="{{ route('sub-products.show', 'stablecoins') }}" class="text-yellow-600 font-semibold hover:text-yellow-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Treasury Module -->
                    <div class="module-card bg-white rounded-2xl p-8 shadow-lg border border-gray-100">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-semibold">{{ config('platform.sub_products.treasury.launch_date') }}</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Treasury</h3>
                        <p class="text-gray-600 mb-4">Enterprise cash management across multiple banks.</p>
                        <ul class="space-y-2 text-sm text-gray-700 mb-6">
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-2"></span>
                                Multi-bank mgmt
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-2"></span>
                                Auto-rebalancing
                            </li>
                            <li class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-2"></span>
                                Risk optimization
                            </li>
                        </ul>
                        <a href="{{ route('sub-products.show', 'treasury') }}" class="text-blue-600 font-semibold hover:text-blue-700">
                            Learn more →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Platform Stats -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Platform Capabilities</h2>
                    <p class="text-xl text-gray-600">Current alpha testing statistics</p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <div class="text-center">
                        <div class="text-5xl font-bold gradient-text mb-2">{{ config('platform.statistics.supported_currencies') }}</div>
                        <p class="text-gray-600">Supported Currencies</p>
                    </div>
                    <div class="text-center">
                        <div class="text-5xl font-bold gradient-text mb-2">{{ config('platform.statistics.banking_partners') }}</div>
                        <p class="text-gray-600">Banking Partners</p>
                    </div>
                    <div class="text-center">
                        <div class="text-5xl font-bold gradient-text mb-2">{{ config('platform.statistics.api_endpoints') }}</div>
                        <p class="text-gray-600">API Endpoints</p>
                    </div>
                    <div class="text-center">
                        <div class="text-5xl font-bold gradient-text mb-2">{{ config('platform.statistics.uptime_sla') }}</div>
                        <p class="text-gray-600">Target Uptime</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Start Building on FinAegis</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Join the alpha testing program. Start with GCU, grow with modules.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block transform hover:scale-105">
                        Open Alpha Account
                    </a>
                    <a href="{{ route('developers') }}" class="glass-effect text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/20 transition inline-block">
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