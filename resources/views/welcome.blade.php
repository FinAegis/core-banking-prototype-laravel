<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis - The Enterprise Financial Platform Powering the Future of Banking. Experience the Global Currency Unit (GCU) with democratic governance and real bank integration.">
        <meta name="keywords" content="FinAegis, banking platform, GCU, global currency unit, democratic banking, fintech, multi-asset, enterprise banking">
        
        <!-- Open Graph -->
        <meta property="og:title" content="FinAegis - Enterprise Financial Platform">
        <meta property="og:description" content="Experience the Global Currency Unit (GCU) with democratic governance. One platform, multiple financial solutions - all optional, all integrated.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/') }}">

        <title>FinAegis - The Enterprise Financial Platform</title>

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
            .feature-card {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
            .stat-number {
                font-size: 3rem;
                font-weight: 800;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .sub-product-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            .sub-product-card:hover {
                border-color: #667eea;
                transform: scale(1.02);
            }
            .gcu-highlight {
                background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                border: 2px solid #667eea;
            }
            .floating-invest {
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                z-index: 40;
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: .8;
                    transform: scale(1.05);
                }
            }
            @media (max-width: 768px) {
                .floating-invest {
                    bottom: 1rem;
                    right: 1rem;
                }
            }
        </style>
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        The Enterprise Financial Platform<br/>
                        Powering Democratic Banking
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        Experience the Global Currency Unit - where your money stays in real banks while you control the currency composition through democratic voting
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Open GCU Account
                        </a>
                        <a href="#platform" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                            Explore Platform
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

        <!-- Investment CTA Section -->
        <section class="py-16 bg-gradient-to-r from-indigo-50 to-purple-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="grid md:grid-cols-2">
                        <!-- Left Side - Investment Info -->
                        <div class="p-12 bg-gradient-to-br from-indigo-600 to-purple-700 text-white">
                            <div class="mb-4">
                                <span class="inline-block px-4 py-2 bg-white/20 backdrop-blur rounded-full text-sm font-semibold">
                                    🚀 Alpha Testing Phase - Limited Opportunity
                                </span>
                            </div>
                            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                                Invest in the Future of Democratic Banking
                            </h2>
                            <p class="text-lg mb-6 text-indigo-100">
                                Be part of the financial revolution. FinAegis is currently in alpha testing, offering early investors a unique opportunity to shape the future of global finance.
                            </p>
                            <div class="space-y-4 mb-8">
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Early access to revolutionary GCU technology</span>
                                </div>
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Democratic governance participation rights</span>
                                </div>
                                <div class="flex items-start">
                                    <svg class="w-6 h-6 text-green-400 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Preferential terms for alpha investors</span>
                                </div>
                            </div>
                            <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                                <p class="text-sm">
                                    <strong>Note:</strong> This is an alpha testing phase. Investment opportunities are limited and subject to regulatory compliance in your jurisdiction.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Right Side - Investment Details -->
                        <div class="p-12">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6">Continuous Growth Offering (CGO)</h3>
                            
                            <div class="space-y-6 mb-8">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-2">Investment Tiers</h4>
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                            <span class="font-medium">Bronze Tier</span>
                                            <span class="text-indigo-600 font-semibold">€1,000 - €9,999</span>
                                        </div>
                                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                            <span class="font-medium">Silver Tier</span>
                                            <span class="text-indigo-600 font-semibold">€10,000 - €49,999</span>
                                        </div>
                                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                            <span class="font-medium">Gold Tier</span>
                                            <span class="text-indigo-600 font-semibold">€50,000+</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-2">Key Benefits</h4>
                                    <ul class="space-y-2 text-gray-600">
                                        <li class="flex items-center">
                                            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-3"></span>
                                            Revenue sharing from platform operations
                                        </li>
                                        <li class="flex items-center">
                                            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-3"></span>
                                            Priority access to new features
                                        </li>
                                        <li class="flex items-center">
                                            <span class="w-2 h-2 bg-indigo-600 rounded-full mr-3"></span>
                                            Governance voting rights
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="{{ route('cgo') }}" class="flex-1 text-center bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-xl">
                                    Learn About CGO
                                </a>
                                <a href="{{ route('register') }}" class="flex-1 text-center border-2 border-indigo-600 text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-indigo-50 transition">
                                    Start Investing
                                </a>
                            </div>
                            
                            <p class="text-sm text-gray-500 mt-6 text-center">
                                Investment subject to terms and conditions. 
                                <a href="{{ route('cgo.terms') }}" class="text-indigo-600 hover:underline">Read full terms</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Platform Overview Section -->
        <section id="platform" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">One Platform, Multiple Solutions</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Start with GCU for secure, democratic banking. Add advanced services as you need them - all optional, all integrated.
                    </p>
                </div>

                <!-- Platform Visual -->
                <div class="relative">
                    <!-- Core Platform -->
                    <div class="gcu-highlight rounded-2xl p-8 mb-8">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">FinAegis Core Platform</h3>
                            <p class="text-gray-600">Enterprise-grade infrastructure powering all services</p>
                        </div>
                        
                        <!-- GCU as Primary Product -->
                        <div class="bg-white rounded-xl p-8 shadow-lg mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-3xl font-bold text-indigo-600">Global Currency Unit (GCU)</h4>
                                <span class="bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-semibold">Active</span>
                            </div>
                            <p class="text-lg text-gray-700 mb-6">
                                The world's first democratically governed basket currency. Your money stays in real banks with government insurance while you vote on currency composition.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-5xl font-bold text-indigo-600 mb-2">Ǥ</div>
                                    <p class="text-gray-600">Official Symbol</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-900 mb-2">6 Assets</div>
                                    <p class="text-gray-600">USD, EUR, GBP, CHF, JPY, XAU</p>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-gray-900 mb-2">3 Banks</div>
                                    <p class="text-gray-600">Your choice of partner banks</p>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Sub-Products -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Exchange -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">FinAegis Exchange</h5>
                                <p class="text-sm text-gray-600 mb-3">Trade crypto and fiat currencies</p>
                                <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-semibold">Coming Soon</span>
                            </div>

                            <!-- Lending -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">FinAegis Lending</h5>
                                <p class="text-sm text-gray-600 mb-3">P2P lending marketplace</p>
                                <span class="inline-block bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-semibold">Coming Soon</span>
                            </div>

                            <!-- Stablecoins -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">FinAegis Stablecoins</h5>
                                <p class="text-sm text-gray-600 mb-3">EUR-pegged stable tokens</p>
                                <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-semibold">Coming Soon</span>
                            </div>

                            <!-- Treasury -->
                            <div class="sub-product-card bg-white rounded-lg p-6 shadow">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mb-4">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <h5 class="font-semibold text-gray-900 mb-2">FinAegis Treasury</h5>
                                <p class="text-sm text-gray-600 mb-3">Multi-bank cash management</p>
                                <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-semibold">Coming Soon</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-center text-gray-600 italic">
                        All sub-products are optional. Use only what you need.
                    </p>
                </div>
            </div>
        </section>

        <!-- GCU Focus Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Global Currency Unit - The Future of Money</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Experience a new kind of currency that combines stability, transparency, and democratic control
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Democratic -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Democratic</h3>
                        <p class="text-gray-600 mb-4">
                            Vote on currency basket composition monthly. Your GCU holdings determine your voting power.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Monthly governance polls
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Transparent voting results
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Community-driven decisions
                            </li>
                        </ul>
                    </div>

                    <!-- Secure -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Secure</h3>
                        <p class="text-gray-600 mb-4">
                            Your money stays in real banks with government deposit insurance up to regulatory limits.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Real bank accounts
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Government insurance
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Multi-bank diversification
                            </li>
                        </ul>
                    </div>

                    <!-- Global -->
                    <div class="bg-white rounded-xl p-8 shadow-md text-center">
                        <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">Global</h3>
                        <p class="text-gray-600 mb-4">
                            Spend anywhere in the world with optimized exchange rates and instant settlements.
                        </p>
                        <ul class="text-left text-gray-700 space-y-2">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                6 major currencies
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Instant global transfers
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Optimized exchange rates
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust Signals Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h3 class="text-2xl font-bold text-gray-900">Trusted by Leading Financial Institutions</h3>
                </div>
                <div class="bg-gray-50 rounded-2xl p-12 text-center">
                    <h3 class="text-xl font-semibold mb-2 flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Partner with FinAegis
                    </h3>
                    <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
                        We're building partnerships with banks and financial institutions to provide real asset backing for the Global Currency Unit. Join us in creating the future of democratic banking.
                    </p>
                    <a href="{{ route('financial-institutions.apply') }}" class="inline-flex items-center justify-center bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                        Apply to Become a Partner Institution
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Progressive Feature Discovery -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Ready for More?</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Enhance your financial capabilities with our optional sub-products. Enable only what you need, when you need it.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <!-- Exchange -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">FinAegis Exchange</h3>
                        <p class="text-gray-600 mb-4">Professional trading for crypto and fiat currencies</p>
                        <ul class="text-sm text-gray-700 space-y-2 mb-6">
                            <li>• Multi-asset trading</li>
                            <li>• Institutional custody</li>
                            <li>• Advanced order types</li>
                            <li>• Real-time settlement</li>
                        </ul>
                        <a href="{{ route('subproducts.exchange') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Lending -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition">
                        <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">FinAegis Lending</h3>
                        <p class="text-gray-600 mb-4">Connect capital with opportunity</p>
                        <ul class="text-sm text-gray-700 space-y-2 mb-6">
                            <li>• P2P marketplace</li>
                            <li>• SME focus</li>
                            <li>• 8-15% returns</li>
                            <li>• Automated servicing</li>
                        </ul>
                        <a href="{{ route('subproducts.lending') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Stablecoins -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition">
                        <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">FinAegis Stablecoins</h3>
                        <p class="text-gray-600 mb-4">Stable value, real backing</p>
                        <ul class="text-sm text-gray-700 space-y-2 mb-6">
                            <li>• EUR-pegged tokens</li>
                            <li>• 1:1 backing</li>
                            <li>• Instant redemption</li>
                            <li>• MiCA compliant</li>
                        </ul>
                        <a href="{{ route('subproducts.stablecoins') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Learn more →
                        </a>
                    </div>

                    <!-- Treasury -->
                    <div class="bg-white rounded-xl p-8 shadow-md hover:shadow-lg transition">
                        <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">FinAegis Treasury</h3>
                        <p class="text-gray-600 mb-4">Optimize cash, minimize risk</p>
                        <ul class="text-sm text-gray-700 space-y-2 mb-6">
                            <li>• Multi-bank allocation</li>
                            <li>• FX optimization</li>
                            <li>• Risk diversification</li>
                            <li>• Corporate tools</li>
                        </ul>
                        <a href="{{ route('subproducts.treasury') }}" class="text-indigo-600 font-semibold hover:text-indigo-700">
                            Learn more →
                        </a>
                    </div>
                </div>

                <p class="text-center text-gray-600 italic mt-8">
                    All services are optional - use only what you need
                </p>
            </div>
        </section>

        <!-- Developer Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                    <div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-4">Built for Developers</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Integrate FinAegis into your applications with our comprehensive APIs and SDKs. Build the future of finance with enterprise-grade infrastructure.
                        </p>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">RESTful APIs</h4>
                                    <p class="text-gray-600">Well-documented endpoints for all banking operations</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">SDKs & Libraries</h4>
                                    <p class="text-gray-600">PHP, JavaScript, Python, and more</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Webhooks</h4>
                                    <p class="text-gray-600">Real-time event notifications for your systems</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8">
                            <a href="/developers" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                Visit Developer Hub
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="mt-10 lg:mt-0">
                        <div class="bg-gray-900 rounded-lg p-6 text-gray-300 font-mono text-sm">
                            <div class="mb-2">
                                <span class="text-gray-500"># Initialize FinAegis SDK</span>
                            </div>
                            <div class="mb-4">
                                <span class="text-purple-400">$finaegis</span> = <span class="text-blue-400">new</span> <span class="text-green-400">FinAegis</span>([<br/>
                                &nbsp;&nbsp;<span class="text-gray-400">'api_key'</span> => <span class="text-yellow-400">'your_api_key'</span>,<br/>
                                &nbsp;&nbsp;<span class="text-gray-400">'environment'</span> => <span class="text-yellow-400">'production'</span><br/>
                                ]);
                            </div>
                            <div class="mb-2">
                                <span class="text-gray-500"># Create GCU transaction</span>
                            </div>
                            <div>
                                <span class="text-purple-400">$transaction</span> = <span class="text-purple-400">$finaegis</span>-><span class="text-blue-400">gcu</span>()-><span class="text-green-400">transfer</span>([<br/>
                                &nbsp;&nbsp;<span class="text-gray-400">'to'</span> => <span class="text-yellow-400">'recipient_id'</span>,<br/>
                                &nbsp;&nbsp;<span class="text-gray-400">'amount'</span> => <span class="text-orange-400">100.00</span>,<br/>
                                &nbsp;&nbsp;<span class="text-gray-400">'currency'</span> => <span class="text-yellow-400">'GCU'</span><br/>
                                ]);
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Ready to Experience Democratic Banking?</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Join thousands of users who have already discovered the power of the Global Currency Unit. Start with a free account today.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                        Create Account
                    </a>
                    <a href="/support/contact" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Contact Sales
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        @include('partials.footer')

        <!-- Floating Investment Button -->
        <div class="floating-invest">
            <a href="{{ route('cgo') }}" class="flex items-center bg-gradient-to-r from-indigo-600 to-purple-700 text-white px-6 py-3 rounded-full shadow-2xl hover:shadow-3xl transition-all duration-300 group">
                <svg class="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
                <span class="font-semibold">Invest Now</span>
                <span class="ml-2 bg-white/20 px-2 py-1 rounded text-xs">Alpha</span>
            </a>
        </div>
    </body>
</html>