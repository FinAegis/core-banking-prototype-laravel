<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('brand.name', 'Zelta') }} Sub-Products - Optional demo modules of the open-source software: Exchange, Lending, Stablecoin, and Treasury reference implementations.">
        <meta name="keywords" content="{{ config('brand.name', 'Zelta') }} sub-products, exchange module, lending module, stablecoin module, treasury module, open-source modules">
        
        <!-- Open Graph -->
        <meta property="og:title" content="{{ config('brand.name', 'Zelta') }} Sub-Products - Optional Software Modules">
        <meta property="og:description" content="Optional open-source modules: exchange, lending, stablecoin and treasury reference implementations.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/sub-products') }}">

        <title>Sub-Products - Optional Modules | {{ config('brand.name', 'Zelta') }}</title>

        @include('partials.favicon')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .product-card {
                transition: all 0.3s ease;
                border: 2px solid transparent;
                position: relative;
                overflow: hidden;
            }
            .product-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .product-card.exchange:hover {
                border-color: #8b5cf6;
            }
            .product-card.lending:hover {
                border-color: #10b981;
            }
            .product-card.stablecoins:hover {
                border-color: #f59e0b;
            }
            .product-card.treasury:hover {
                border-color: #3b82f6;
            }
            .coming-soon-badge {
                position: absolute;
                top: 20px;
                right: -30px;
                background: #ef4444;
                color: white;
                padding: 5px 40px;
                transform: rotate(45deg);
                font-size: 12px;
                font-weight: bold;
            }
            .feature-icon {
                transition: transform 0.3s ease;
            }
            .product-card:hover .feature-icon {
                transform: scale(1.1);
            }
        </style>
    </head>
    <body class="antialiased">
        <x-platform-banners />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 bg-fa-navy text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Optional Sub-Products
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-slate-400 max-w-4xl mx-auto">
                        Optional demo modules of the open-source software. Each module is an open-source component you can enable in your own deployment.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('dashboard') }}" class="btn-primary px-8 py-4 text-lg">
                            Explore the Sandbox
                        </a>
                        <a href="#products" class="btn-outline px-8 py-4 text-lg">
                            Explore Modules
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

        <!-- Products Overview Section -->
        <section id="products" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-4xl font-bold text-slate-900 mb-4">Demo Modules</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        Each sub-product is a reference implementation of the open-source software, shown in the sandbox with test data only.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Exchange -->
                    <div class="product-card exchange bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-semibold">Demo module</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">{{ config('brand.name', 'Zelta') }} Exchange</h3>
                        <p class="text-slate-500 mb-6">
                            Exchange module demo: order matching for crypto and fiat currency pairs (sandbox, test data only).
                        </p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Order matching engine</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Limit & market orders</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>API access</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'exchange') }}" class="text-purple-600 font-semibold hover:text-purple-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Sandbox demo</span>
                        </div>
                    </div>

                    <!-- Lending -->
                    <div class="product-card lending bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">Demo module</span>
                        </div>
                        
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">{{ config('brand.name', 'Zelta') }} Lending</h3>
                        <p class="text-slate-500 mb-6">
                            P2P lending module demo: loan origination, credit scoring and repayment workflows (sandbox, test data only).
                        </p>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>SME loan workflows</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Invoice financing workflows</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Automated credit scoring</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'lending') }}" class="text-green-600 font-semibold hover:text-green-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Sandbox demo</span>
                        </div>
                    </div>

                    <!-- Stablecoins -->
                    <div class="product-card stablecoins bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-sm font-semibold">Demo module</span>
                        </div>

                        <h3 class="text-2xl font-bold text-slate-900 mb-4">{{ config('brand.name', 'Zelta') }} Stablecoins</h3>
                        <p class="text-slate-500 mb-6">
                            Stablecoin module: model issuance, redemption and reserve tracking (sandbox, test data only). Reference implementation; no token is issued or offered.
                        </p>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Mint/redeem workflows</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Reserve tracking</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Cross-chain bridge adapters</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'stablecoins') }}" class="text-yellow-600 font-semibold hover:text-yellow-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Sandbox demo</span>
                        </div>
                    </div>

                    <!-- Treasury -->
                    <div class="product-card treasury bg-gray-50 rounded-2xl p-8 shadow-lg">
                        <div class="flex items-start justify-between mb-6">
                            <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center feature-icon">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">Demo module</span>
                        </div>

                        <h3 class="text-2xl font-bold text-slate-900 mb-4">{{ config('brand.name', 'Zelta') }} Treasury</h3>
                        <p class="text-slate-500 mb-6">
                            Treasury module demo: multi-bank cash-position modelling and automated portfolio rebalancing (sandbox, test data only).
                        </p>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Multi-bank allocation modelling</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Automated rebalancing</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Risk optimization</span>
                            </div>
                            <div class="flex items-center text-slate-600">
                                <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Corporate controls</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('sub-products.show', 'treasury') }}" class="text-blue-600 font-semibold hover:text-blue-700">
                                Learn more →
                            </a>
                            <span class="text-sm text-gray-500">Sandbox demo</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-dot-pattern"></div>
            <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Optional sub-product modules</h2>
                <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                    Each module is an open-source component you can enable in your own deployment.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                        Get Started Free
                    </a>
                    <a href="{{ route('platform') }}" class="btn-outline px-8 py-4 text-lg">
                        Platform Overview
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Sub-Products</h4>
                        <ul class="space-y-2">
                            <li><a href="/sub-products/exchange" class="hover:text-white transition">Exchange</a></li>
                            <li><a href="/sub-products/lending" class="hover:text-white transition">Lending</a></li>
                            <li><a href="/sub-products/stablecoins" class="hover:text-white transition">Stablecoins</a></li>
                            <li><a href="/sub-products/treasury" class="hover:text-white transition">Treasury</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Platform</h4>
                        <ul class="space-y-2">
                            <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                            <li><a href="/gcu" class="hover:text-white transition">GCU demo</a></li>
                            <li><a href="/features" class="hover:text-white transition">Features</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Resources</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Developers</a></li>
                            <li><a href="/support" class="hover:text-white transition">Support</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/status" class="hover:text-white transition">Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2">
                            <li><a href="/about" class="hover:text-white transition">About</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Bank Connectors</a></li>
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p class="text-xs text-gray-500 max-w-3xl mx-auto mb-3">FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.</p>
                    <p class="text-xs text-gray-500 max-w-3xl mx-auto mb-4">The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.</p>
                    <p>&copy; {{ date('Y') }} {{ config('brand.name', 'Zelta') }}. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </body>
</html>